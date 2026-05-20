#!/usr/bin/env node
//
// import-faq-table-to-botpress.mjs
//
// Imports data/botpress-faq-table-seed.csv into a Botpress Table named
// jh_faq_v1 inside the JH bot. Lets Liz skip the Studio Tables UI for
// bulk edits — re-run this whenever the CSV changes.
//
// Usage:
//   node scripts/import-faq-table-to-botpress.mjs              # safe — fails if table exists
//   node scripts/import-faq-table-to-botpress.mjs --recreate   # wipes existing rows + recreates
//   node scripts/import-faq-table-to-botpress.mjs --dry-run    # parse CSV, no API calls
//   node scripts/import-faq-table-to-botpress.mjs --table foo  # override table name
//
// Env:
//   BOTPRESS_PAT          falls back to ~/.botpress/profiles.json::default.token
//   BOTPRESS_WORKSPACE_ID falls back to ~/.botpress/profiles.json::default.workspaceId
//   BOTPRESS_BOT_ID       defaults to JH bot id
//
// Exit codes:
//   0 = imported (or dry-run printed)
//   1 = API or parse error
//   2 = config / env error

import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const REPO_ROOT = path.resolve(__dirname, '..')
const DEFAULT_CSV = path.join(REPO_ROOT, 'data', 'botpress-faq-table-seed.csv')
const DEFAULT_BOT_ID = '208ffbe5-a209-4a10-a52c-d79de4577f45'
const API_BASE = process.env.BOTPRESS_API_URL || 'https://api.botpress.cloud'

// --- args ---
const args = {}
for (let i = 2; i < process.argv.length; i++) {
  const a = process.argv[i]
  if (a === '--recreate') args.recreate = true
  else if (a === '--dry-run') args.dryRun = true
  else if (a === '--table') args.table = process.argv[++i]
  else if (a === '--csv') args.csv = process.argv[++i]
  else if (a === '--help' || a === '-h') {
    console.log(fs.readFileSync(import.meta.url.replace('file://', ''), 'utf8').split('\n').slice(0, 25).join('\n').replace(/^\/\//gm, ''))
    process.exit(0)
  }
}
// Botpress naming rule: ≤30 chars, alphanumeric+underscore, must end with "Table".
const TABLE = args.table || 'JhFaqV1Table'
const CSV = args.csv || DEFAULT_CSV

// --- creds ---
function loadProfile() {
  const p = path.join(os.homedir(), '.botpress', 'profiles.json')
  if (!fs.existsSync(p)) return {}
  try {
    const j = JSON.parse(fs.readFileSync(p, 'utf8'))
    const k = Object.keys(j)[0]
    return j[k] || {}
  } catch {
    return {}
  }
}
const profile = loadProfile()
const PAT = process.env.BOTPRESS_PAT || profile.token
const WORKSPACE = process.env.BOTPRESS_WORKSPACE_ID || profile.workspaceId
const BOT_ID = process.env.BOTPRESS_BOT_ID || DEFAULT_BOT_ID

if (!args.dryRun) {
  if (!PAT) { console.error('ERROR: no Botpress PAT (set BOTPRESS_PAT or ~/.botpress/profiles.json)'); process.exit(2) }
  if (!WORKSPACE) { console.error('ERROR: no workspace id'); process.exit(2) }
}

// --- CSV parser (handles quoted fields with commas + pipe-separated patterns) ---
function parseCsv(text) {
  const rows = []
  const headers = []
  let i = 0
  let row = []
  let field = ''
  let inQuotes = false
  let isFirstRow = true

  while (i < text.length) {
    const c = text[i]
    if (inQuotes) {
      if (c === '"' && text[i + 1] === '"') { field += '"'; i += 2; continue }
      if (c === '"') { inQuotes = false; i++; continue }
      field += c; i++; continue
    }
    if (c === '"') { inQuotes = true; i++; continue }
    if (c === ',') { row.push(field); field = ''; i++; continue }
    if (c === '\n' || c === '\r') {
      if (field !== '' || row.length > 0) {
        row.push(field)
        if (isFirstRow) {
          headers.push(...row)
          isFirstRow = false
        } else if (row.some(v => v !== '')) {
          const obj = {}
          for (let j = 0; j < headers.length; j++) obj[headers[j]] = row[j] ?? ''
          rows.push(obj)
        }
      }
      row = []
      field = ''
      if (c === '\r' && text[i + 1] === '\n') i += 2
      else i++
      continue
    }
    field += c; i++
  }
  if (field !== '' || row.length > 0) {
    row.push(field)
    if (!isFirstRow && row.some(v => v !== '')) {
      const obj = {}
      for (let j = 0; j < headers.length; j++) obj[headers[j]] = row[j] ?? ''
      rows.push(obj)
    }
  }
  return { headers, rows }
}

// --- API helper ---
async function api(method, urlPath, body) {
  const headers = {
    'Authorization': `Bearer ${PAT}`,
    'x-workspace-id': WORKSPACE,
    'x-bot-id': BOT_ID,
    'Accept': 'application/json',
  }
  if (body) headers['Content-Type'] = 'application/json'
  const res = await fetch(`${API_BASE}${urlPath}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  })
  const text = await res.text()
  let json
  try { json = JSON.parse(text) } catch { json = { _raw: text } }
  if (!res.ok) {
    const err = new Error(`HTTP ${res.status}: ${json.message || text.slice(0, 300)}`)
    err.status = res.status
    err.body = json
    throw err
  }
  return json
}

// --- main ---
async function main() {
  if (!fs.existsSync(CSV)) {
    console.error(`ERROR: CSV not found at ${CSV}`)
    process.exit(2)
  }
  const text = fs.readFileSync(CSV, 'utf8')
  const { headers, rows } = parseCsv(text)
  console.log(`Parsed ${rows.length} rows from ${path.basename(CSV)}`)
  console.log(`Headers: ${headers.join(', ')}`)

  // Normalize rows for Botpress: enforce types per schema.
  const cleaned = rows.map((r, idx) => ({
    topic_pattern: String(r.topic_pattern || '').trim(),
    region: String(r.region || '').trim().toLowerCase(),
    answer_template: String(r.answer_template || '').trim(),
    url: String(r.url || '').trim(),
    priority: Number(r.priority || 99),
    notes: String(r.notes || '').trim(),
    _row: idx + 2,  // CSV line number for debugging (header is line 1)
  })).filter(r => r.topic_pattern && r.region && r.answer_template)

  if (cleaned.length === 0) {
    console.error('ERROR: no valid rows after normalization')
    process.exit(1)
  }

  // Validate region values.
  const validRegions = new Set(['na', 'europe', 'pacific', 'asia', 'middle_east', 'global'])
  const badRegions = cleaned.filter(r => !validRegions.has(r.region))
  if (badRegions.length) {
    console.error(`ERROR: ${badRegions.length} row(s) have invalid region. First: line ${badRegions[0]._row}, region="${badRegions[0].region}"`)
    process.exit(1)
  }

  console.log(`${cleaned.length} valid rows ready to insert.`)

  if (args.dryRun) {
    console.log('--dry-run: skipping API calls. Sample row:')
    console.log(JSON.stringify({ ...cleaned[0], _row: undefined }, null, 2))
    process.exit(0)
  }

  // --- Check if table exists ---
  const tables = await api('GET', '/v1/tables')
  const existing = tables.tables.find(t => t.name === TABLE)

  if (existing && !args.recreate) {
    console.error(`ERROR: table "${TABLE}" already exists (id=${existing.id}). Use --recreate to wipe + reimport.`)
    process.exit(1)
  }

  if (existing && args.recreate) {
    console.log(`Recreating: deleting existing table ${TABLE} (id=${existing.id})...`)
    try {
      await api('DELETE', `/v1/tables/${TABLE}`)
      console.log('  deleted.')
    } catch (e) {
      console.error(`ERROR deleting: ${e.message}`)
      process.exit(1)
    }
  }

  // --- Create table ---
  console.log(`Creating table ${TABLE}...`)
  // Botpress Tables schema gotcha: x-zui.index is a NUMBER (column display
  // order, 0..n-1), NOT a boolean. x-zui.searchable IS boolean. Verified
  // against RouterAgentTable shape 2026-05-20.
  const schema = {
    type: 'object',
    properties: {
      topic_pattern:   { type: 'string', 'x-zui': { index: 0, searchable: true } },
      region:          { type: 'string', 'x-zui': { index: 1, searchable: true } },
      answer_template: { type: 'string', 'x-zui': { index: 2, searchable: true } },
      url:             { type: 'string', 'x-zui': { index: 3 } },
      priority:        { type: 'number', 'x-zui': { index: 4 } },
      notes:           { type: 'string', 'x-zui': { index: 5 } },
    },
    required: ['topic_pattern', 'region', 'answer_template'],
  }
  try {
    await api('POST', '/v1/tables', {
      name: TABLE,
      schema,
      tags: { purpose: 'jh-faq-fast-path', source: 'data/botpress-faq-table-seed.csv', version: '1' },
    })
    console.log('  created.')
  } catch (e) {
    console.error(`ERROR creating table: ${e.message}`)
    if (e.body) console.error(JSON.stringify(e.body, null, 2))
    process.exit(1)
  }

  // --- Insert rows (chunked) ---
  const CHUNK = 25  // conservative; some Botpress endpoints cap at 50
  let inserted = 0
  for (let i = 0; i < cleaned.length; i += CHUNK) {
    const chunk = cleaned.slice(i, i + CHUNK).map(r => {
      const { _row, ...rest } = r
      return rest
    })
    try {
      const resp = await api('POST', `/v1/tables/${TABLE}/rows`, { rows: chunk })
      const got = resp.rows?.length ?? chunk.length
      inserted += got
      console.log(`  inserted chunk ${i + 1}-${i + chunk.length} (${got} rows). running total: ${inserted}/${cleaned.length}`)
    } catch (e) {
      console.error(`ERROR inserting chunk starting at row ${i + 1}: ${e.message}`)
      if (e.body) console.error(JSON.stringify(e.body, null, 2))
      console.error(`Aborting. Table ${TABLE} now has ${inserted} rows (partial state).`)
      process.exit(1)
    }
  }

  console.log('')
  console.log(`✓ Done. ${inserted} rows imported into table ${TABLE}.`)
  console.log(`  Next: in Studio, wire a Find Records card on ${TABLE} as the first card in AutonomousNode (see docs/FAQ-TABLES-STUDIO-SETUP.md Step 3).`)
}

main().catch(err => {
  console.error('Fatal:', err.message)
  if (err.stack) console.error(err.stack)
  process.exit(1)
})
