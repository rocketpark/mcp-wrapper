#!/usr/bin/env node
/**
 * Analyze Jensen Hughes Content Freshness
 *
 * Queries all major sections via MCP and reports on update frequency.
 *
 * Usage: node scripts/analyze-content-freshness.js
 */

const https = require('https');

const MCP_ENDPOINT = process.env.MCP_ENDPOINT || 'https://staging3.jensenhughes.com/mcp/ai';
const agent = new https.Agent({ rejectUnauthorized: false });

function makeRequest(url, body) {
  return new Promise((resolve, reject) => {
    const parsedUrl = new URL(url);
    const options = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      agent: agent
    };

    const req = https.request(url, options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch {
          resolve({ error: data });
        }
      });
    });

    req.on('error', reject);
    req.write(JSON.stringify(body));
    req.end();
  });
}

async function querySection(section, limit = 100) {
  const response = await makeRequest(MCP_ENDPOINT, {
    jsonrpc: '2.0',
    id: 1,
    method: 'tools/call',
    params: {
      name: 'craft_search_entries',
      arguments: { section, limit, orderBy: 'dateUpdated DESC' }
    }
  });

  const content = response?.result?.content?.[0]?.text;
  if (!content) return [];

  try {
    const parsed = JSON.parse(content);
    return parsed?.data?.entries || [];
  } catch {
    return [];
  }
}

function daysSince(dateStr) {
  if (!dateStr) return Infinity;
  const date = new Date(dateStr);
  const now = new Date();
  return Math.floor((now - date) / (1000 * 60 * 60 * 24));
}

function categorizeAge(days) {
  if (days <= 7) return '< 1 week';
  if (days <= 30) return '< 1 month';
  if (days <= 90) return '< 3 months';
  if (days <= 180) return '< 6 months';
  if (days <= 365) return '< 1 year';
  return '> 1 year';
}

async function analyzeSection(sectionName, section) {
  console.log(`\nAnalyzing ${sectionName}...`);
  const entries = await querySection(section);

  if (entries.length === 0) {
    console.log(`  No entries found or section doesn't exist`);
    return null;
  }

  const ages = entries.map(e => ({
    title: e.title,
    slug: e.slug,
    dateUpdated: e.dateUpdated,
    dateCreated: e.dateCreated,
    daysAgo: daysSince(e.dateUpdated)
  }));

  // Sort by most stale first
  ages.sort((a, b) => b.daysAgo - a.daysAgo);

  // Calculate stats
  const avgDays = Math.round(ages.reduce((sum, e) => sum + e.daysAgo, 0) / ages.length);
  const oldestUpdate = ages[0];
  const newestUpdate = ages[ages.length - 1];

  // Group by age category
  const byAge = {};
  for (const entry of ages) {
    const cat = categorizeAge(entry.daysAgo);
    byAge[cat] = (byAge[cat] || 0) + 1;
  }

  return {
    section: sectionName,
    totalEntries: entries.length,
    avgDaysSinceUpdate: avgDays,
    oldest: oldestUpdate,
    newest: newestUpdate,
    distribution: byAge,
    entries: ages
  };
}

async function main() {
  console.log('========================================');
  console.log('JENSEN HUGHES CONTENT FRESHNESS ANALYSIS');
  console.log('========================================');
  console.log(`Date: ${new Date().toISOString().split('T')[0]}`);
  console.log(`Endpoint: ${MCP_ENDPOINT}`);

  const sections = [
    ['Office Locations', 'officeLocations'],
    ['Services', 'services'],
    ['Industries', 'industries'],
    ['Our Team', 'ourTeam'],
    ['Leadership', 'leadershipTeam'],
    ['Insights/Blog', 'insights'],
    ['Events', 'events'],
    ['Careers', 'careers'],
    ['Webinars', 'webinars'],
    ['Podcasts', 'podcasts'],
    ['Certified Companies', 'certifiedCompanies'],
  ];

  const results = [];

  for (const [name, handle] of sections) {
    try {
      const result = await analyzeSection(name, handle);
      if (result) results.push(result);
    } catch (err) {
      console.log(`  Error: ${err.message}`);
    }
  }

  // Summary Report
  console.log('\n\n========================================');
  console.log('SUMMARY: CONTENT FRESHNESS BY SECTION');
  console.log('========================================\n');

  // Sort by average staleness
  results.sort((a, b) => b.avgDaysSinceUpdate - a.avgDaysSinceUpdate);

  console.log('┌─────────────────────────┬────────┬──────────────┬───────────────────────────┐');
  console.log('│ Section                 │ Count  │ Avg Days Old │ Recommendation            │');
  console.log('├─────────────────────────┼────────┼──────────────┼───────────────────────────┤');

  for (const r of results) {
    const rec = r.avgDaysSinceUpdate > 180 ? 'STATIC KB ✓' :
                r.avgDaysSinceUpdate > 30 ? 'KB (monthly sync)' :
                'MCP (real-time)';
    console.log(`│ ${r.section.padEnd(23)} │ ${String(r.totalEntries).padStart(6)} │ ${String(r.avgDaysSinceUpdate).padStart(12)} │ ${rec.padEnd(25)} │`);
  }

  console.log('└─────────────────────────┴────────┴──────────────┴───────────────────────────┘');

  // Detailed breakdown for most stable sections
  console.log('\n\n========================================');
  console.log('MOST STABLE CONTENT (Best for KB)');
  console.log('========================================');

  const stableSections = results.filter(r => r.avgDaysSinceUpdate > 90);
  for (const r of stableSections) {
    console.log(`\n## ${r.section}`);
    console.log(`   Total: ${r.totalEntries} entries`);
    console.log(`   Average age: ${r.avgDaysSinceUpdate} days`);
    console.log(`   Oldest: "${r.oldest.title}" (${r.oldest.daysAgo} days ago)`);
    console.log(`   Newest: "${r.newest.title}" (${r.newest.daysAgo} days ago)`);
    console.log(`   Distribution:`);
    for (const [cat, count] of Object.entries(r.distribution)) {
      console.log(`     ${cat}: ${count} entries`);
    }
  }

  // Dynamic content
  console.log('\n\n========================================');
  console.log('MOST DYNAMIC CONTENT (Use MCP)');
  console.log('========================================');

  const dynamicSections = results.filter(r => r.avgDaysSinceUpdate <= 90);
  for (const r of dynamicSections) {
    console.log(`\n## ${r.section}`);
    console.log(`   Total: ${r.totalEntries} entries`);
    console.log(`   Average age: ${r.avgDaysSinceUpdate} days`);
    console.log(`   Most recent updates:`);
    for (const entry of r.entries.slice(-5).reverse()) {
      console.log(`     - "${entry.title}" (${entry.daysAgo} days ago)`);
    }
  }

  console.log('\n\nAnalysis complete!');
}

main().catch(console.error);
