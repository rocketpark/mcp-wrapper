#!/usr/bin/env node
/**
 * Sync Jensen Hughes Office Locations to Botpress Knowledge Base
 *
 * This script fetches all office locations from the MCP endpoint and uploads
 * them as a searchable document to the Botpress Knowledge Base via Files API.
 *
 * Usage:
 *   node scripts/sync-offices-to-botpress.js
 *
 * Environment Variables Required:
 *   BOTPRESS_PAT          - Personal Access Token from Botpress
 *   BOTPRESS_BOT_ID       - Bot ID (from bot URL or settings)
 *   BOTPRESS_KB_ID        - Knowledge Base ID (from KB settings)
 *   MCP_ENDPOINT          - MCP endpoint URL (default: https://jensenhughes3.on-forge.com/mcp/ai)
 *
 * Optional:
 *   DRY_RUN=true          - Preview output without uploading
 */

const https = require('https');
const http = require('http');

// Allow self-signed certs for staging environments
const agent = new https.Agent({ rejectUnauthorized: false });

const fs = require('fs');
const path = require('path');

// Configuration from environment
const config = {
  botpressPat: process.env.BOTPRESS_PAT,
  botpressBotId: process.env.BOTPRESS_BOT_ID,
  botpressKbId: process.env.BOTPRESS_KB_ID,
  mcpEndpoint: process.env.MCP_ENDPOINT || 'https://jensenhughes3.on-forge.com/mcp/ai',
  dryRun: process.env.DRY_RUN === 'true',
  useLocalData: process.env.USE_LOCAL_DATA === 'true',  // For testing without MCP access
  saveLocalData: process.env.SAVE_LOCAL_DATA === 'true',  // Save fetched data for later testing
};

// Validate required config
function validateConfig() {
  const missing = [];
  if (!config.botpressPat) missing.push('BOTPRESS_PAT');
  if (!config.botpressBotId) missing.push('BOTPRESS_BOT_ID');
  if (!config.botpressKbId) missing.push('BOTPRESS_KB_ID');

  if (missing.length > 0 && !config.dryRun) {
    console.error('❌ Missing required environment variables:');
    missing.forEach(v => console.error(`   - ${v}`));
    console.error('\nSee BOTPRESS-KB-SYNC-SETUP.md for setup instructions.');
    process.exit(1);
  }
}

// Make HTTP request (works with both http and https)
function makeRequest(url, options, body = null) {
  return new Promise((resolve, reject) => {
    const parsedUrl = new URL(url);
    const client = parsedUrl.protocol === 'https:' ? https : http;

    // Add agent for HTTPS to handle self-signed certs
    if (parsedUrl.protocol === 'https:') {
      options.agent = agent;
    }

    const req = client.request(url, options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode, data: JSON.parse(data) });
        } catch {
          resolve({ status: res.statusCode, data });
        }
      });
    });

    req.on('error', reject);
    if (body) req.write(body);
    req.end();
  });
}

// Load office data from local JSON file (for testing)
function loadLocalOfficeData() {
  const dataPath = path.join(__dirname, '../data/office-locations.json');
  if (!fs.existsSync(dataPath)) {
    throw new Error(`Local data file not found: ${dataPath}\nRun with MCP_ENDPOINT set to fetch from server first.`);
  }
  const data = JSON.parse(fs.readFileSync(dataPath, 'utf8'));
  console.log(`   Loaded ${data.length} offices from local file`);
  return data;
}

// Fetch all office locations via MCP endpoint
async function fetchOfficeLocations() {
  // Use local data if configured (for testing without MCP access)
  if (config.useLocalData) {
    console.log('📍 Loading office locations from local data file...');
    return loadLocalOfficeData();
  }

  console.log('📍 Fetching office locations from MCP endpoint...');

  // First, get list of all offices
  const listResponse = await makeRequest(config.mcpEndpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
  }, JSON.stringify({
    jsonrpc: '2.0',
    id: 1,
    method: 'tools/call',
    params: {
      name: 'query_officeLocations',
      arguments: { limit: 100 }
    }
  }));

  if (listResponse.status !== 200) {
    throw new Error(`Failed to fetch office list: ${listResponse.status}`);
  }

  const result = listResponse.data?.result;
  if (!result) {
    console.error('   Response data:', JSON.stringify(listResponse.data, null, 2));
    throw new Error(`MCP error: No result in response`);
  }
  if (result.isError) {
    throw new Error(`MCP error: ${JSON.stringify(result)}`);
  }

  // Parse the content - it's in result.content[0].text
  const contentText = result.content?.[0]?.text;
  if (!contentText) {
    throw new Error('No content in MCP response');
  }

  const officeData = JSON.parse(contentText);
  const offices = officeData.data?.officeLocations || [];

  console.log(`   Found ${offices.length} offices`);

  // Now fetch detailed contact info for each office
  const detailedOffices = [];

  for (const office of offices) {
    try {
      const detailResponse = await makeRequest(config.mcpEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
      }, JSON.stringify({
        jsonrpc: '2.0',
        id: 1,
        method: 'tools/call',
        params: {
          name: 'craft_get_office_contact_info',
          arguments: { slug: office.slug }
        }
      }));

      if (detailResponse.status === 200) {
        const detailResult = detailResponse.data?.result;
        const detailText = detailResult?.content?.[0]?.text;
        if (detailText) {
          const detail = JSON.parse(detailText);
          if (detail.success && detail.data?.contactInfo) {
            detailedOffices.push(detail.data.contactInfo);
          }
        }
      }
    } catch (err) {
      console.warn(`   ⚠️ Could not fetch details for ${office.slug}: ${err.message}`);
      // Still include basic info
      detailedOffices.push({
        slug: office.slug,
        title: office.title,
        city: office.city,
        country: office.country,
      });
    }
  }

  // Optionally save data locally for testing
  if (config.saveLocalData) {
    const dataDir = path.join(__dirname, '../data');
    if (!fs.existsSync(dataDir)) {
      fs.mkdirSync(dataDir, { recursive: true });
    }
    const dataPath = path.join(dataDir, 'office-locations.json');
    fs.writeFileSync(dataPath, JSON.stringify(detailedOffices, null, 2));
    console.log(`   Saved office data to ${dataPath}`);
  }

  return detailedOffices;
}

// Format offices as searchable text for Knowledge Base
function formatOfficesForKB(offices) {
  const lines = [
    '# Jensen Hughes Office Locations',
    '',
    `Last updated: ${new Date().toISOString().split('T')[0]}`,
    `Total offices: ${offices.length}`,
    '',
    '---',
    '',
  ];

  // Group by country
  const byCountry = {};
  for (const office of offices) {
    const country = office.country || 'Unknown';
    if (!byCountry[country]) byCountry[country] = [];
    byCountry[country].push(office);
  }

  // Sort countries, US first
  const countries = Object.keys(byCountry).sort((a, b) => {
    if (a === 'United States') return -1;
    if (b === 'United States') return 1;
    return a.localeCompare(b);
  });

  for (const country of countries) {
    lines.push(`## ${country}`);
    lines.push('');

    // Sort offices by city/title
    const countryOffices = byCountry[country].sort((a, b) =>
      (a.city || a.title || '').localeCompare(b.city || b.title || '')
    );

    for (const office of countryOffices) {
      const title = office.title || office.city || office.slug;
      lines.push(`### ${title}`);

      if (office.address) {
        lines.push(`Address: ${office.address.replace(/\n/g, ', ')}`);
      } else if (office.city && office.state) {
        lines.push(`Location: ${office.city}, ${office.state}`);
      } else if (office.city) {
        lines.push(`Location: ${office.city}`);
      }

      if (office.phone) {
        lines.push(`Phone: ${office.phone}`);
      }

      if (office.contactFormUrl) {
        lines.push(`Contact Form: ${office.contactFormUrl}`);
      }

      if (office.officeDetailsUrl) {
        lines.push(`Details: ${office.officeDetailsUrl}`);
      }

      lines.push('');
    }
  }

  // Add footer with general contact
  lines.push('---');
  lines.push('');
  lines.push('## General Contact');
  lines.push('For general inquiries: info@jensenhughes.com');
  lines.push('Website: https://www.jensenhughes.com');
  lines.push('');

  return lines.join('\n');
}

// Upload to Botpress Knowledge Base via Files API
async function uploadToBotpress(content) {
  console.log('📤 Uploading to Botpress Knowledge Base...');

  const fileKey = 'kb-sync/office-locations.txt';

  const response = await makeRequest('https://api.botpress.cloud/v1/files', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${config.botpressPat}`,
      'x-bot-id': config.botpressBotId,
    },
  }, JSON.stringify({
    key: fileKey,
    content: content,
    index: true,  // Required for KB indexing
    tags: {
      source: 'craft-cms-sync',
      kbId: config.botpressKbId,
      title: 'Jensen Hughes Office Locations',
      syncedAt: new Date().toISOString(),
    }
  }));

  if (response.status >= 200 && response.status < 300) {
    console.log('   ✅ Upload successful!');
    console.log(`   File key: ${fileKey}`);
    return response.data;
  } else {
    throw new Error(`Upload failed: ${response.status} - ${JSON.stringify(response.data)}`);
  }
}

// Main sync function
async function main() {
  console.log('🔄 Jensen Hughes Office Locations → Botpress KB Sync');
  console.log('');

  validateConfig();

  if (config.dryRun) {
    console.log('🔍 DRY RUN MODE - No upload will occur');
    console.log('');
  }

  try {
    // Step 1: Fetch offices
    const offices = await fetchOfficeLocations();

    // Step 2: Format for KB
    console.log('📝 Formatting office data...');
    const content = formatOfficesForKB(offices);
    console.log(`   Generated ${content.length} bytes of content`);

    if (config.dryRun) {
      console.log('');
      console.log('=== PREVIEW (first 2000 chars) ===');
      console.log(content.substring(0, 2000));
      console.log('...');
      console.log('=== END PREVIEW ===');
      console.log('');
      console.log('✅ Dry run complete. Set DRY_RUN=false and provide credentials to upload.');
    } else {
      // Step 3: Upload to Botpress
      await uploadToBotpress(content);
      console.log('');
      console.log('✅ Sync complete!');
    }

  } catch (error) {
    console.error('❌ Sync failed:', error.message);
    process.exit(1);
  }
}

main();
