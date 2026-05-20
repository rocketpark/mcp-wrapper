# User Variables — Studio Setup for Region Persistence

**Goal:** Replace the integration's `bpClient.listEvents` global-scoped fallback with native Botpress User Variables. Each visitor gets their own `user.region` that persists across messages + conversations. Closes the cross-user leak risk documented in `botpress-integration/src/index.ts:202-206`.

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

**The fix:** Make `user.region` a **persistent user variable** that's set once and read forever. The integration always sees the right region for the calling user via `input.region`. listEvents fallback effectively never fires.

---

## Step 1 — Add a Trigger card for the regionContext event

In Studio's bot flow editor:

1. Open the main flow (the one containing AutonomousNode).
2. Add a **new node** at the very top of the flow.
3. Add a **Trigger card** to that node:
   - Trigger type: **Custom Event**
   - Event name: `regionContext`
   - Listen on every conversation (not just start)
4. After the Trigger card, add a **Set Variable** card (or "Execute Code" card if your studio uses that):
   - Variable: `user.region`
   - Value: `event.payload.data.region`
   - (and optionally: `user.siteHandle = event.payload.data.siteHandle`, `user.urlPrefix = event.payload.data.urlPrefix`, `user.language = event.payload.data.language`)

**Why `user` not `workflow`:** `user.*` persists across messages and conversations for the same anonymous webchat user. `workflow.*` only lasts the current conversation. Region is a stable per-user property — should be user-scoped.

## Step 2 — Update Standard1 (or wherever region is read into workflow.region)

The bot probably has a node that reads region at conversation start and writes to `workflow.region`. Update it:

**Before:**
```
workflow.region = user.data.region ?? 'north_america'
```

**After:**
```
workflow.region = user.region ?? user.data.region ?? 'north_america'
```

(Order matters — `user.region` from Step 1 is the new persistent source; `user.data.region` is the Twig updateUser legacy fallback; `'north_america'` is the final default.)

## Step 3 — Save + Publish

Standard Studio save + publish.

## Step 4 — Verify in webchat

1. Open `/europe` page in incognito. Wait for webchat ready.
2. Open webchat. Send a question.
3. Bot reply should use EU URLs ✓ (same as before — no regression)
4. Send a SECOND question in the same conversation. Reply should still be EU ✓ (this is the path that used to potentially fail).
5. Open Studio → Conversations panel → click your test conversation → check `user.region` is set to `europe`.

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
| Race: 1st message arrives before regionContext event populates workflow.region | `botpress-integration/src/index.ts:202-206` | Closed — user.region is set on the regionContext event, persists, queryContent reads it from input |
| Cross-user leak: under concurrent traffic, "most recent regionContext event" might belong to a different visitor | listEvents fallback comment | Closed — user.region is per-user, not workspace-global |
| Bot answers with NA on message 2+ even though message 1 was correctly EU | Memory `project_jh_botpress_region.md` | Closed — user.region persists across messages |
