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
];

function parseArgs(argv) {
  const args = {};
  for (let i = 2; i < argv.length; i++) {
    if (argv[i] === '--region') args.region = argv[++i];
    if (argv[i] === '--tag') args.tag = argv[++i];
    if (argv[i] === '--verbose' || argv[i] === '-v') args.verbose = true;
  }
  return args;
}

async function setupSession(page, regionCfg) {
  await page.evaluate(() => {
    document.cookie.split(';').forEach(c => {
      const n = c.split('=')[0].trim();
      document.cookie = `${n}=;expires=Thu, 01 Jan 1970;path=/`;
      document.cookie = `${n}=;expires=Thu, 01 Jan 1970;path=/;domain=${location.hostname}`;
    });
    localStorage.clear();
    sessionStorage.clear();
  });
  await page.goto(STAGING_URL + regionCfg.path + '?_t=' + Date.now(), { waitUntil: 'load' });
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
  await page.waitForTimeout(28000);
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
  return { pass: missing.length === 0 && banned.length === 0, missing, banned };
}

async function main() {
  const args = parseArgs(process.argv);
  const filtered = TESTS.filter(t => (!args.region || t.region === args.region) && (!args.tag || t.tag === args.tag));
  console.log(`Running ${filtered.length} tests (of ${TESTS.length})\n`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ httpCredentials: HTTP_AUTH });
  const page = await context.newPage();

  const results = [];
  let currentRegion = null;
  for (const test of filtered) {
    if (currentRegion !== test.region) {
      currentRegion = test.region;
      await setupSession(page, REGIONS[test.region]);
    }
    const transcript = await ask(page, test.q);
    const reply = lastBotReply(transcript, test.q);
    const result = evaluateExpectations(reply, test);
    results.push({ test, reply, result });
    const status = result.pass ? '✓ PASS' : '✗ FAIL';
    console.log(`${status}  ${test.id}`);
    if (!result.pass || args.verbose) {
      if (result.missing.length) console.log(`   missing: ${result.missing.join(' | ')}`);
      if (result.banned.length)  console.log(`   banned:  ${result.banned.join(' | ')}`);
      if (args.verbose) console.log(`   reply (last 300 chars): ${reply.slice(-300)}\n`);
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
