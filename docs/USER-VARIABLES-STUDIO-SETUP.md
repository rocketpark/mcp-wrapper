# User Variables — Studio Setup for Region Persistence

**Goal:** Replace the integration's `bpClient.listEvents` global-scoped fallback with native Botpress User Variables. Each visitor gets their own `user.userRegion` that persists across messages + conversations. Closes the cross-user leak risk documented in `botpress-integration/src/index.ts:217-354`.

**Audit doc reference:** Tier 2 item #5 in `JH-BOT-AUDIT-2026-05-20.md`.

**Audience:** Liz performing Studio clicks. No integration code changes required.

---

## Why this matters

Today's region detection flow:

1. Twig footer fires `sendEvent({type:'regionContext', region, ...})` on webchat ready / opened / messageSent.
2. Botpress bot's Standard1 node reads `user.data.region` into `workflow.region`.
3. Bot calls `queryContent({region: workflow.region})`. Integration uses it directly. ✓ happy path.

**Where it breaks:** If `workflow.region` is empty (Standard1 ran before the regionContext event arrived, or bot is on message 2+ where Standard1 doesn't re-run), the integration falls back to `bpClient.listEvents({})` and picks **the most recent regionContext event in the entire workspace**.

Under concurrent traffic from different regions, that "most recent" might belong to a different visitor. Bot replies with the wrong region.

**The fix:** Make `user.userRegion` a **persistent user variable** that's set once (by a Trigger card listening for the `regionContext` custom event) and read forever. The integration always sees the right region for the calling user via `input.region`. listEvents fallback effectively never fires.

---

## Step 1 — Add a Trigger card for the regionContext event

In Studio's bot flow editor:

1. Open the main flow (the one containing AutonomousNode).
2. Add a **new node** at the very top of the flow.
3. Add a **Trigger card** to that node:
   - Trigger type: **Custom Event**
   - Event name: `regionContext`
   - Listen on every conversation (not just start)
   - **Event Filter (CRITICAL — TRUE PATH verified live 2026-05-20 from Botpress Events panel):**
     ```
     {{event.payload?.payload?.data?.type === "regionContext"}}
     ```
     The Twig footer calls `sendEvent({data:{type:'regionContext',...}})`. The webchat SDK wraps that as a `webchat:trigger` event with structure `event.payload = {origin, conversationId, userId, payload: {data: {type, region, siteHandle, urlPrefix, language}}}`. The actual `regionContext` payload lives at `event.payload.payload.data` — note the nested `payload` key, NOT a nested `data` key. Earlier doc versions (and the original Studio Trigger1 filter) used `event.payload.data.type` and `event.payload.data.data.type` — both wrong, no `data` key at top of `event.payload`. Filter that doesn't match the true path silently never fires for webchat events. See `botpress-integration/src/index.ts:277-310` for the integration's matching defensive `readEventRegion()` function (v1.0.18 added the `payload.payload.data` path as primary).
4. After the Trigger card, add an **Execute Code** card named `SetUserRegion` with this code (mirrors integration code's defensive priority order):
   ```js
   const r = event.payload?.payload?.data?.region
          || event.payload?.data?.region
          || event.payload?.data?.data?.region
   if (r) {
     user.userRegion = r
   }
   ```

**Variable name MUST be `userRegion`, not `region`.** Botpress blocks duplicate variable names across scopes — CONVERSATION + WORKFLOW already declare `region`, so the USER-scope copy must use a distinct name (`userRegion` was chosen).

**Why `user` scope, not `workflow`:** `user.*` persists across messages AND conversations for the same anonymous webchat user. `workflow.*` only lasts the current conversation. Region is a stable per-user property — must be user-scoped.

## Step 2 — Update Standard1 (or wherever region is read into workflow.region)

The bot probably has a node that reads region at conversation start and writes to `workflow.region`. Update it:

**Before:**
```
workflow.region = user.data.region ?? 'north_america'
```

**After:**
```
workflow.region = user.userRegion ?? user.data.region ?? 'north_america'
```

(Order matters — `user.userRegion` from Step 1 is the new persistent source; `user.data.region` is the Twig updateUser legacy fallback; `'north_america'` is the final default.)

The V6.20 AutonomousNode prompt's Rule 0 also reads `user.userRegion` first directly (resolution order: `user.userRegion` → `workflow.region` → `user.data.region` → default `north_america`), so it doesn't strictly require Standard1 to forward — but keeping Standard1 in sync gives belt-and-suspenders fallback for any node that reads `workflow.region`.

## Step 3 — Save + Publish

Standard Studio save + publish.

## Step 4 — Verify in webchat

1. Open `/europe` page in incognito. Wait for webchat ready.
2. Open webchat. Send a question.
3. Bot reply should use EU URLs ✓ (same as before — no regression)
4. Send a SECOND question in the same conversation. Reply should still be EU ✓ (this is the path that used to potentially fail).
5. Open Studio → Conversations panel → click your test conversation → check `user.userRegion` is set to `europe`.

**Concrete known-good scenario (validated 2026-05-20 evening):** Pacific page → restart conversation → ask "Do you offer security risk consulting?" → bot must reply "Security Risk + Public Safety is not currently available in the Pacific. For more info, contact info@jensenhughes.com." (Rule 4 Pacific-not-available). If bot leaks to "in North America" + NA URL, region resolution chain is broken — diagnose Trigger1 filter first (see Step 1).

## Step 5 — Confirm listEvents fallback no longer fires

In Botpress runtime logs (Studio → Logs), watch for the integration's log line:

```
No regionContext event found in recent events; using default site.
```

With User Variables wired correctly, this should appear **only on the very first message of a brand-new user** (before the Trigger card has had a chance to fire). On every subsequent call, the bot passes `region` via input + integration uses it directly. The fallback path is dormant.

If you keep seeing the fallback fire after Step 1-4: either the Trigger card isn't subscribed correctly, or `user.region` isn't being passed by the bot. Check the Conversations panel `user.region` value first.

---

## What the integration code does (no changes today, but documented)

`botpress-integration/src/index.ts:148-276` already handles the user-scoped path correctly:

- **Strategy 1 (preferred):** Bot passes `region` via `input.region`. Integration maps via `REGION_TO_SITE`. Reliable.
- **Strategy 2 (fallback):** `bpClient.listEvents({})` global scan. Race-prone.

After this Studio setup, Strategy 1 covers ~all queryContent calls. Strategy 2 becomes the no-history-yet edge case.

A future integration v1.0.15 could:
1. Add a `userId` filter to listEvents to make Strategy 2 user-scoped too (defense-in-depth).
2. Surface a deprecation warning if Strategy 2 fires after the first message (signals Studio config drift).

Both are non-urgent once Step 1-4 are in place.

---

## Rollback

If something breaks:

1. Studio → flow editor → disable the new Trigger card from Step 1.
2. Republish. Bot reverts to the V6.7 / current behavior.
3. The integration code is unchanged; nothing to revert on the mcp-wrapper side.

---

## Cross-reference: pain points this closes

| Pain | Source | After Step 1-4 |
|---|---|---|
| Race: 1st message arrives before regionContext event populates workflow.region | `botpress-integration/src/index.ts:217-354` | Closed — `user.userRegion` is set on the regionContext event, persists, queryContent reads it from input |
| Cross-user leak: under concurrent traffic, "most recent regionContext event" might belong to a different visitor | listEvents fallback comment at src/index.ts:260-276 | Closed — `user.userRegion` is per-user, not workspace-global |
| Bot answers with NA on message 2+ even though message 1 was correctly EU | Memory `project_jh_botpress_region.md` | Closed — `user.userRegion` persists across messages |
| Trigger filter that only checks `event.payload.data.type` silently never fires for webchat events | Studio Trigger1 config (this doc, Step 1) | Closed — defensive both-shapes filter matches `payload.data.type` OR `payload.data.data.type` |
