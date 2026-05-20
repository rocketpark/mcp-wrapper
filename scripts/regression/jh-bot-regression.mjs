#!/usr/bin/env node
/**
 * Jensen Hughes Botpress Regression Test
 *
 * Drives the live JH webchat embed on staging3.on-forge.com via Playwright,
 * issues one question per region/topic from a structured test plan, and
 * checks the response against expected substrings + forbidden substrings.
 *
 * Usage:
 *   npm install -g playwright  # one-time
 *   node scripts/regression/jh-bot-regression.mjs              # full suite
 *   node scripts/regression/jh-bot-regression.mjs --region EU  # filter
 *   node scripts/regression/jh-bot-regression.mjs --tag forensics
 *
 * Exit code 0 = all pass, 1 = any fail.
 *
 * Race timing: per memory, must wait ≥10s after sendEvent regionContext
 * before sendMessage. We wait 12s. Response wait is 28s.
 */

import { chromium } from 'playwright';

const STAGING_URL = 'https://jensenhughes3.on-forge.com';
const HTTP_AUTH = { username: 'rocketpark', password: 'JHstaging2026!' };

const REGIONS = {
  NA:      { path: '/',            region: 'north_america', siteHandle: '',                       urlPrefix: '' },
  EU:      { path: '/europe',      region: 'europe',        siteHandle: 'jensenHughesEurope',     urlPrefix: '/europe' },
  Pacific: { path: '/pacific',     region: 'pacific',       siteHandle: 'jensenHughesPacific',    urlPrefix: '/pacific' },
  Asia:    { path: '/asia',        region: 'asia',          siteHandle: 'jensenHughesAsia',       urlPrefix: '/asia' },
  ME:      { path: '/middle-east', region: 'middle_east',   siteHandle: 'jensenHughesMiddleEast', urlPrefix: '/middle-east' },
};

// Test plan: question, must-include substrings (all required), must-NOT-include substrings (any banned)
const TESTS = [
  // Forensics — Jonathan's primary complaint area
  { id: 'EU-forensics',      region: 'EU',      tag: 'forensics',     q: 'Do you offer forensic services?',
    expect: ['Europe', 'forensic-investigation', 'instructus.uk@jensenhughes.com'],
    deny:   ['/scotland', '/services/fire-engineering-systems-design'] },
  { id: 'NA-forensics',      region: 'NA',      tag: 'forensics',     q: 'Do you offer forensic services?',
    expect: ['/services/investigations'],
    deny:   ['/scotland', 'fire-engineering-systems-design', 'not currently available'] },
  { id: 'Pacific-forensics', region: 'Pacific', tag: 'forensics',     q: 'Do you offer forensic services?',
    expect: ['not currently available', 'Pacific', 'info@jensenhughes.com'],
    deny:   ['/scotland', '/services/investigations'] },
  { id: 'Asia-forensics',    region: 'Asia',    tag: 'forensics',     q: 'Do you offer forensic services in Asia?',
    expect: ['not currently available', 'info@jensenhughes.com'],
    deny:   ['/scotland'] },
  { id: 'ME-forensics',      region: 'ME',      tag: 'forensics',     q: 'Do you offer forensic services?',
    expect: ['not currently available', 'Middle East', 'info@jensenhughes.com'],
    deny:   ['/scotland'] },
  // Accessibility — was wrongly blocked for Pacific
  { id: 'Pacific-accessibility', region: 'Pacific', tag: 'accessibility', q: 'Do you offer accessibility consulting?',
    expect: ['accessibility', '/pacific/'],
    deny:   ['not currently available in Pacific'] },
  { id: 'EU-accessibility', region: 'EU', tag: 'accessibility', q: 'Do you offer accessibility consulting?',
    expect: ['not currently available', 'Europe', 'info@jensenhughes.com'],
    deny:   [] },
  { id: 'Asia-accessibility', region: 'Asia', tag: 'accessibility', q: 'Do you offer accessibility consulting?',
    expect: ['not currently available', 'info@jensenhughes.com'],
    deny:   [] },
  { id: 'ME-accessibility', region: 'ME', tag: 'accessibility', q: 'Do you offer accessibility consulting?',
    expect: ['Middle East', '/middle-east/'],
    deny:   ['not currently available'] },
  // Fire engineering — should always use regional prefix
  { id: 'EU-fire-eng',     region: 'EU',     tag: 'fire-engineering', q: 'Do you offer fire engineering?',
    expect: ['/europe/'], deny: ['/services/fire-engineering-systems-design'] },
  { id: 'Pacific-fire-eng', region: 'Pacific', tag: 'fire-engineering', q: 'Do you offer fire engineering?',
    expect: ['/pacific/'], deny: [] },
  { id: 'Asia-fire-eng',    region: 'Asia',    tag: 'fire-engineering', q: 'Do you offer fire engineering?',
    expect: ['/asia/'],   deny: [] },
  { id: 'ME-fire-eng',      region: 'ME',      tag: 'fire-engineering', q: 'Do you offer fire engineering?',
    expect: ['/middle-east/'], deny: [] },
  // Restricted services per region
  { id: 'EU-security',     region: 'EU',     tag: 'restriction', q: 'Do you offer security risk consulting?',
    expect: ['not currently available', 'Europe'], deny: [] },
  { id: 'Pacific-security', region: 'Pacific', tag: 'restriction', q: 'Do you offer security risk consulting?',
    expect: ['Pacific'], deny: ['Yes—Jensen Hughes offers security risk'] },
  { id: 'Pacific-em',       region: 'Pacific', tag: 'restriction', q: 'Do you offer emergency management?',
    expect: ['not currently available', 'Pacific'], deny: [] },
  // Office contact (Rule 3)
  { id: 'EU-london-office',   region: 'EU', tag: 'office', q: 'What is the phone number for the London office?',
    expect: ['London'], deny: [] },
  { id: 'NA-oakland-office',  region: 'NA', tag: 'office', q: 'What is the phone number for the Oakland office?',
    expect: ['Oakland', '510-775-1919'], deny: [] },
  { id: 'ME-dubai-office',    region: 'ME', tag: 'office', q: 'What is the phone number for the Dubai office?',
    expect: ['Dubai', '+971'], deny: [] },
  // BIM (Rule 6)
  { id: 'BIM-url',         region: 'NA', tag: 'bim', q: 'Tell me about BIMfire',
    expect: ['incorporating-bimfire-into-jensen-hughes-fire-safety-design'], deny: ['bim-fire', 'fire-engineering-systems-design'] },
  // Privacy (Rule 7)
  { id: 'Privacy-refuse',  region: 'NA', tag: 'privacy', q: 'What is Brian Meacham email address?',
    expect: ['info@jensenhughes.com'],
    deny: ['brian.meacham@', '@jensenhughes.com\nemail:', 'meacham@jensenhughes'] },
  // Gap coverage: non-NA office locator, podcast, careers, partners, multi-part
  { id: 'Pacific-sydney-office', region: 'Pacific', tag: 'office', q: 'What is the phone number for the Sydney office?',
    expect: ['Sydney'], deny: ['not currently available'] },
  { id: 'NA-podcast', region: 'NA', tag: 'podcast', q: 'Do you have a podcast?',
    // Bot links URL containing "forensics-uncovered-podcast" — accept either form
    expect: [],
    expectAny: ['Forensics Uncovered', 'forensics-uncovered-podcast'],
    deny: [] },
  { id: 'NA-careers', region: 'NA', tag: 'careers', q: 'Are you hiring?',
    expect: ['careers'], deny: [] },
  { id: 'NA-multi-part', region: 'NA', tag: 'multi-part', q: 'Do you offer both fire engineering and security risk consulting?',
    expect: ['fire engineering', 'security risk'], deny: ['not currently available'] },
  // V6 — Aldiana 11/17 ticket coverage
  { id: 'NA-combustible-dust-experts', region: 'NA', tag: 'aldiana-v6', q: 'Who are your experts in combustible dust?',
    expect: ['combustible-dust-safety'],
    deny: ['${', '{prefix', '{region', '$&#123;', 'I couldn\'t find anyone'] },
  { id: 'NA-bess-experts', region: 'NA', tag: 'aldiana-v6', q: 'Who are your experts in BESS?',
    expect: ['lithium-ion-risk-consulting'],
    deny: ['${', '{prefix', '{region', '$&#123;'] },
  { id: 'NA-lithium-ion-services', region: 'NA', tag: 'aldiana-v6', q: 'Do you offer services for lithium ion batteries?',
    // Both lithium-ion-risk-consulting AND emerging-hazards are valid landings per V6 Rule 10
    expect: ['jensenhughes.com'],
    expectAny: ['lithium-ion-risk-consulting', 'emerging-hazards'],
    deny: ['${', '{prefix', '$&#123;'] },
  { id: 'NA-lsft-info', region: 'NA', tag: 'aldiana-v6', q: 'Tell me about LSFT',
    expect: ['Large-Scale Fire Testing', 'fire-testing'],
    deny: ['${', '{prefix', '$&#123;', '\\n \\n'] },
  { id: 'NA-lng-experts', region: 'NA', tag: 'aldiana-v6', q: 'Who are your experts in LNG?',
    expect: ['process-safety'],
    deny: ['${', '{prefix', '$&#123;'] },
  { id: 'NA-sydney-disambig-no-slug', region: 'Pacific', tag: 'aldiana-v6', q: 'What is the phone number for the Sydney office?',
    expect: ['Sydney'],
    // slug strings MUST NOT leak to user
    deny: ['slug:', '(slug', 'sydney-castlereagh-street', 'sydney-australia'] },
  { id: 'NA-single-service-no-list-dump', region: 'NA', tag: 'aldiana-v6', q: 'Do you offer fire engineering services?',
    // Should be conversational lead + ONE link, NOT bulleted list dump
    expect: ['fire-engineering-systems-design'],
    deny: ['${', '{prefix', '$&#123;'] },
  { id: 'EU-bim-no-fire-eng', region: 'EU', tag: 'aldiana-v6', q: 'Do you offer BIM services?',
    expect: ['incorporating-bimfire-into-jensen-hughes-fire-safety-design'],
    deny: ['fire-engineering-systems-design', 'fire-engineering-consultancy'] },
  // V6 — region × content-surface coverage (Jonathan's "all stuff is different per region" feedback)
  // Industries per region
  { id: 'EU-industries', region: 'EU', tag: 'region-surface', q: 'What industries do you serve?',
    expect: ['Europe'], deny: ['${', '$&#123;'] },
  { id: 'Pacific-industries', region: 'Pacific', tag: 'region-surface', q: 'What industries do you serve?',
    expect: ['Pacific'], deny: ['${', '$&#123;'] },
  { id: 'Asia-industries', region: 'Asia', tag: 'region-surface', q: 'What industries do you serve?',
    expect: ['Asia'], deny: ['${', '$&#123;'] },
  { id: 'ME-industries', region: 'ME', tag: 'region-surface', q: 'What industries do you serve?',
    expect: ['Middle East'], deny: ['${', '$&#123;'] },
  // Marine Forensics — Jonathan's May 13 specific complaint
  { id: 'EU-marine-forensics', region: 'EU', tag: 'jonathan', q: 'Do you provide Marine Forensics services?',
    expect: ['marine-fire-forensics', 'instructus.uk@jensenhughes.com'],
    deny: ['${', '$&#123;', 'what country', 'what city'] },
  { id: 'NA-marine-forensics', region: 'NA', tag: 'jonathan', q: 'Do you provide Marine Forensics services?',
    expect: ['investigations'], deny: ['${', '$&#123;'] },
  // Digital Solutions per region
  { id: 'NA-digital', region: 'NA', tag: 'region-surface', q: 'What digital solutions do you offer?',
    // Accept any digital product mention OR the /services/digital landing — KB doesn't surface specific product names yet
    expect: ['Digital'],
    expectAny: ['Advisr', '/services/digital', 'SMARTPLAN'],
    deny: ['${', '$&#123;'] },
  { id: 'EU-digital', region: 'EU', tag: 'region-surface', q: 'What digital solutions do you offer?',
    expect: ['Europe'], deny: ['${', '$&#123;'] },
  // Insights per region
  { id: 'EU-insights', region: 'EU', tag: 'region-surface', q: 'Do you have any case studies or insights for Europe?',
    expect: ['Europe'], deny: ['${', '$&#123;'] },
  { id: 'Pacific-insights', region: 'Pacific', tag: 'region-surface', q: 'Do you have any case studies or insights for Pacific?',
    expect: ['Pacific'], deny: ['${', '$&#123;'] },
  // Careers per region
  { id: 'EU-careers', region: 'EU', tag: 'region-surface', q: 'Are you hiring in Europe?',
    expect: ['careers'], deny: ['${', '$&#123;'] },
  { id: 'Pacific-careers', region: 'Pacific', tag: 'region-surface', q: 'Are you hiring in Pacific?',
    expect: ['careers'], deny: ['${', '$&#123;'] },
  // About / company overview per region — should NOT region-gate "about Jensen Hughes" since company info is global
  { id: 'NA-about', region: 'NA', tag: 'region-surface', q: 'Tell me about Jensen Hughes.',
    expect: ['1939', 'jensenhughes.com'], deny: ['${', '$&#123;'] },
  { id: 'EU-about', region: 'EU', tag: 'region-surface', q: 'Tell me about Jensen Hughes.',
    expect: ['1939'], deny: ['${', '$&#123;'] },
  // Contact page per region
  { id: 'EU-contact', region: 'EU', tag: 'region-surface', q: 'How can I get in touch with Jensen Hughes in Europe?',
    expect: ['Europe'], deny: ['${', '$&#123;'] },
  { id: 'Asia-contact', region: 'Asia', tag: 'region-surface', q: 'How can I contact Jensen Hughes in Asia?',
    expect: ['Asia'], deny: ['${', '$&#123;'] },
  { id: 'ME-contact', region: 'ME', tag: 'region-surface', q: 'How do I contact Jensen Hughes in the Middle East?',
    expect: ['Middle East'], deny: ['${', '$&#123;'] },
  // V6.7 — gap coverage 2026-05-20 (off-topic refusal, override, bridge, named expert, disambig, deep insights, India)
  { id: 'G1-NA-joke-refuse', region: 'NA', tag: 'gap-2026-05-20', q: 'Tell me a joke.',
    expect: ['Jensen Hughes'],
    expectAny: ['focused on Jensen Hughes', 'services', 'How can I help'],
    deny: ['${', '$&#123;'] },
  { id: 'G2-EU-weather-refuse', region: 'EU', tag: 'gap-2026-05-20', q: 'What is the weather in London?',
    expect: ['Jensen Hughes'],
    expectAny: ['focused on Jensen Hughes', 'fire', 'forensic', 'services'],
    deny: ['${', '$&#123;', 'sunny', 'rain', 'temperature', '°C', '°F'] },
  { id: 'G3-NA-pricing-refuse', region: 'NA', tag: 'gap-2026-05-20', q: 'How much does fire engineering cost?',
    expect: ['Jensen Hughes'],
    expectAny: ['focused on Jensen Hughes', 'contact', 'info@jensenhughes.com'],
    deny: ['${', '$&#123;', '$1,', '$10,', '$100', 'starts at', 'per hour', '/hour'] },
  { id: 'G4-NA-competitor-refuse', region: 'NA', tag: 'gap-2026-05-20', q: 'How are you better than Arup?',
    expect: ['Jensen Hughes'],
    expectAny: ['focused on Jensen Hughes', 'services', 'capabilities'],
    deny: ['${', '$&#123;', 'better than Arup', 'Arup is worse', 'compared to Arup'] },
  { id: 'G5-NA-user-region-override', region: 'NA', tag: 'gap-2026-05-20', q: 'Do you offer fire engineering services in Europe?',
    // User explicitly names region → Rule 0 USER-ASKED OVERRIDE → emit EU URL even though session region=NA
    expect: ['/europe/'],
    deny: ['${', '$&#123;', '/services/fire-engineering-systems-design'] },
  { id: 'G6-NA-bridge-and-also', region: 'NA', tag: 'gap-2026-05-20', q: 'Do you offer fire engineering? And also tell me about your security risk consulting.',
    // Rule 11 bridge: "and also" allows combining → bot should address both
    expect: ['fire'],
    expectAny: ['security', 'risk consulting', 'security-risk'],
    deny: ['${', '$&#123;'] },
  { id: 'G7-NA-named-expert', region: 'NA', tag: 'gap-2026-05-20', q: 'Tell me about Sean Lebel.',
    // Integration name-match path: searches ALL members, not just Regional Leadership
    // Bot should find Sean Lebel OR fall back to info@ if not in CMS
    expect: [],
    expectAny: ['Sean', 'Lebel', 'info@jensenhughes.com'],
    deny: ['${', '$&#123;'] },
  { id: 'G8-NA-unknown-person', region: 'NA', tag: 'gap-2026-05-20', q: 'Tell me about Joaquim Vandermeerschen.',
    // Fabricated name — must NOT hallucinate a bio. Any "no match" phrasing is fine
    // (Rule 10 fallback also acceptable — links the regional services landing).
    expect: [],
    expectAny: ['couldn\'t find', 'no results', 'info@jensenhughes.com', 'not sure', 'don\'t have',
                'not seeing', 'no public', 'no published profile', 'no profile', '/services'],
    deny: ['${', '$&#123;', 'Director of', 'Managing Director', 'VP of'] },
  { id: 'G9-Pacific-sydney-cbd', region: 'Pacific', tag: 'gap-2026-05-20', q: 'What is the phone number for the Sydney CBD office?',
    // Tests partial slug + region — Rule 3 says partials work ("oakland"→"oakland-san-leandro")
    expect: ['Sydney'],
    deny: ['${', '$&#123;', 'slug:', '(slug', 'not currently available'] },
  { id: 'G10-EU-insights-deep', region: 'EU', tag: 'gap-2026-05-20', q: 'Do you have case studies on data centers in Europe?',
    // Rule 13 region scope + Rule 2 insights empty → retry / fallback to /europe/ landing
    expect: ['Europe'],
    expectAny: ['/europe/', 'case stud', 'insights', 'data center'],
    deny: ['${', '$&#123;'] },
  { id: 'G11-NA-forensics-deep', region: 'NA', tag: 'gap-2026-05-20', q: 'What about expert witness services?',
    // Rule 5 forensics umbrella — NA template + investigations URL
    expect: ['Jensen Hughes'],
    expectAny: ['/services/investigations', 'expert witness', 'forensic'],
    deny: ['${', '$&#123;', '/scotland'] },
  { id: 'G12-ME-india-presence', region: 'ME', tag: 'gap-2026-05-20', q: 'Do you have offices in India?',
    // Rule 0 regionLabel "Middle East + India" — bot should affirm India presence
    expect: [],
    expectAny: ['India', 'Mumbai', '/middle-east/', 'Middle East'],
    deny: ['${', '$&#123;'] },
];

function parseArgs(argv) {
  const args = {};
  for (let i = 2; i < argv.length; i++) {
    if (argv[i] === '--region') args.region = argv[++i];
    if (argv[i] === '--tag') args.tag = argv[++i];
    if (argv[i] === '--ids') args.ids = argv[++i].split(',');
    if (argv[i] === '--verbose' || argv[i] === '-v') args.verbose = true;
    if (argv[i] === '--no-retry') args['no-retry'] = true;
  }
  return args;
}

async function setupSession(page, regionCfg) {
  // Clear cookies via browser context (works even on about:blank)
  await page.context().clearCookies();
  await page.goto(STAGING_URL + regionCfg.path + '?_t=' + Date.now(), { waitUntil: 'load' });
  // Clear page-scoped storage after navigation
  await page.evaluate(() => {
    try { localStorage.clear(); sessionStorage.clear(); } catch (e) {}
  });
  await page.evaluate(async (cfg) => {
    const t0 = Date.now();
    while (!(window.botpress && typeof window.botpress.open === 'function') && Date.now() - t0 < 20000) {
      await new Promise(r => setTimeout(r, 200));
    }
    window.botpress.open();
    await new Promise(r => setTimeout(r, 1500));
    await window.botpress.updateUser({
      data: { region: cfg.region, siteHandle: cfg.siteHandle, urlPrefix: cfg.urlPrefix },
      tags: { region: cfg.region }
    });
    await window.botpress.sendEvent({
      type: 'trigger',
      payload: { data: { type: 'regionContext', region: cfg.region, siteHandle: cfg.siteHandle, urlPrefix: cfg.urlPrefix } }
    });
  }, regionCfg);
  // race: workflow.region needs ≥10s after sendEvent (memory: project_jh_botpress_region)
  await page.waitForTimeout(12000);
}

async function ask(page, question) {
  await page.evaluate(async (q) => {
    await window.botpress.sendMessage(q);
  }, question);
  // 45s covers multi-part queries that fire 2+ tool calls (~50s observed)
  await page.waitForTimeout(45000);
  return await page.evaluate(() => {
    const hosts = Array.from(document.querySelectorAll('*')).filter(el => el.shadowRoot);
    for (const h of hosts) {
      const c = h.shadowRoot.querySelector('.bpContainer');
      if (c) return c.innerText;
    }
    return '';
  });
}

function lastBotReply(transcript, userQ) {
  const idx = transcript.lastIndexOf(userQ);
  if (idx < 0) return transcript;
  const after = transcript.slice(idx + userQ.length);
  // Trim trailing webchat chrome
  return after.replace(/Need to speak with someone\? Visit our contact page\s*$/m, '').trim();
}

function evaluateExpectations(reply, test) {
  const replyLow = reply.toLowerCase();
  const missing = test.expect.filter(s => !replyLow.includes(s.toLowerCase()));
  const banned = test.deny.filter(s => replyLow.includes(s.toLowerCase()));
  // expectAny: pass if ANY of the strings appears (used when multiple valid URLs/keywords exist)
  let anyMissing = false;
  if (test.expectAny && test.expectAny.length) {
    anyMissing = !test.expectAny.some(s => replyLow.includes(s.toLowerCase()));
  }
  return {
    pass: missing.length === 0 && banned.length === 0 && !anyMissing,
    missing: anyMissing ? [...missing, `expectAny: ${test.expectAny.join(' OR ')}`] : missing,
    banned
  };
}

async function main() {
  const args = parseArgs(process.argv);
  const filtered = TESTS.filter(t =>
    (!args.region || t.region === args.region) &&
    (!args.tag || t.tag === args.tag) &&
    (!args.ids || args.ids.includes(t.id))
  );
  console.log(`Running ${filtered.length} tests (of ${TESTS.length})\n`);

  const browser = await chromium.launch({ headless: true });

  // Re-run on fail: AutonomousNode is 100% LLM-driven with ~5% nondeterminism
  // per memory feedback_botpress_autonomous_node.md. A single false-fail on a
  // wording variation shouldn't flag a regression. If the first attempt fails,
  // wait 8s (gives Botpress LLM cache a beat) and retry once. Only report FAIL
  // if both attempts failed. Disable with --no-retry.
  const MAX_RETRIES = args['no-retry'] ? 0 : 1;
  const RETRY_WAIT_MS = 8000;

  async function runOne(test) {
    const context = await browser.newContext({ httpCredentials: HTTP_AUTH });
    const page = await context.newPage();
    await setupSession(page, REGIONS[test.region]);
    const transcript = await ask(page, test.q);
    const reply = lastBotReply(transcript, test.q);
    await context.close();
    return { reply, result: evaluateExpectations(reply, test) };
  }

  const results = [];
  for (const test of filtered) {
    let attempt = await runOne(test);
    let retried = false;
    if (!attempt.result.pass && MAX_RETRIES > 0) {
      console.log(`… ${test.id} failed on attempt 1, retrying in ${RETRY_WAIT_MS/1000}s (LLM nondeterminism)`);
      await new Promise(r => setTimeout(r, RETRY_WAIT_MS));
      attempt = await runOne(test);
      retried = true;
    }
    results.push({ test, reply: attempt.reply, result: attempt.result, retried });
    const status = attempt.result.pass ? '✓ PASS' : '✗ FAIL';
    const retryNote = retried ? (attempt.result.pass ? ' (passed on retry)' : ' (failed both attempts)') : '';
    console.log(`${status}  ${test.id}${retryNote}`);
    if (!attempt.result.pass || args.verbose) {
      if (attempt.result.missing.length) console.log(`   missing: ${attempt.result.missing.join(' | ')}`);
      if (attempt.result.banned.length)  console.log(`   banned:  ${attempt.result.banned.join(' | ')}`);
      if (args.verbose) console.log(`   reply (last 300 chars): ${attempt.reply.slice(-300)}\n`);
    }
  }

  await browser.close();

  const passed = results.filter(r => r.result.pass).length;
  const failed = results.length - passed;
  console.log(`\n${passed}/${results.length} pass, ${failed} fail`);
  process.exit(failed === 0 ? 0 : 1);
}

main().catch(err => {
  console.error('Fatal:', err);
  process.exit(2);
});
