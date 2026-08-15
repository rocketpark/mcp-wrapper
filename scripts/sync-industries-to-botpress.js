#!/usr/bin/env node
/**
 * Sync Jensen Hughes Industries to Botpress Knowledge Base
 *
 * This script fetches all industries from the MCP endpoint and uploads
 * them as a searchable document to the Botpress Knowledge Base via Files API.
 *
 * Usage:
 *   node scripts/sync-industries-to-botpress.js
 *
 * Environment Variables Required:
 *   BOTPRESS_PAT          - Personal Access Token from Botpress
 *   BOTPRESS_BOT_ID       - Bot ID (from bot URL or settings)
 *   BOTPRESS_KB_ID        - Knowledge Base ID (from KB settings)
 *   MCP_ENDPOINT          - MCP endpoint URL (default: https://jensenhughes3.on-forge.com/mcp/ai)
 *
 * Optional:
 *   DRY_RUN=true          - Preview output without uploading
 *   USE_LOCAL_DATA=true   - Use local JSON file instead of MCP
 *   SAVE_LOCAL_DATA=true  - Save fetched data to local file
 */

const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

// Allow self-signed certs for staging environments
const agent = new https.Agent({ rejectUnauthorized: false });

// Configuration from environment
const config = {
  botpressPat: process.env.BOTPRESS_PAT,
  botpressBotId: process.env.BOTPRESS_BOT_ID,
  botpressKbId: process.env.BOTPRESS_KB_ID,
  mcpEndpoint: process.env.MCP_ENDPOINT || 'https://jensenhughes3.on-forge.com/mcp/ai',
  dryRun: process.env.DRY_RUN === 'true',
  useLocalData: process.env.USE_LOCAL_DATA === 'true',
  saveLocalData: process.env.SAVE_LOCAL_DATA === 'true',
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

// Load industry data from local JSON file (for testing)
function loadLocalIndustryData() {
  const dataPath = path.join(__dirname, '../data/industries.json');
  if (!fs.existsSync(dataPath)) {
    throw new Error(`Local data file not found: ${dataPath}\nRun with MCP_ENDPOINT set to fetch from server first.`);
  }
  const data = JSON.parse(fs.readFileSync(dataPath, 'utf8'));
  console.log(`   Loaded ${data.length} industries from local file`);
  return data;
}

// Fetch all industries via MCP endpoint
async function fetchIndustries() {
  if (config.useLocalData) {
    console.log('🏭 Loading industries from local data file...');
    return loadLocalIndustryData();
  }

  console.log('🏭 Fetching industries from MCP endpoint...');

  const response = await makeRequest(config.mcpEndpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
  }, JSON.stringify({
    jsonrpc: '2.0',
    id: 1,
    method: 'tools/call',
    params: {
      name: 'query_industries',
      arguments: { limit: 100 }
    }
  }));

  if (response.status !== 200) {
    throw new Error(`Failed to fetch industries: ${response.status}`);
  }

  const result = response.data?.result;
  if (!result) {
    console.error('   Response data:', JSON.stringify(response.data, null, 2));
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

  const industryData = JSON.parse(contentText);
  const industries = industryData.data?.industries || [];

  console.log(`   Found ${industries.length} industries`);

  // Optionally save data locally for testing
  if (config.saveLocalData) {
    const dataDir = path.join(__dirname, '../data');
    if (!fs.existsSync(dataDir)) {
      fs.mkdirSync(dataDir, { recursive: true });
    }
    const dataPath = path.join(dataDir, 'industries.json');
    fs.writeFileSync(dataPath, JSON.stringify(industries, null, 2));
    console.log(`   Saved industry data to ${dataPath}`);
  }

  return industries;
}

// Format industries as searchable text for Knowledge Base
function formatIndustriesForKB(industries) {
  const lines = [
    '# Jensen Hughes Industry Expertise',
    '',
    `Last updated: ${new Date().toISOString().split('T')[0]}`,
    `Industries served: ${industries.length}`,
    '',
    'Jensen Hughes provides safety, security, and risk-based engineering and consulting services across diverse industries worldwide.',
    '',
    '---',
    '',
  ];

  // Sort industries alphabetically
  const sortedIndustries = [...industries].sort((a, b) =>
    (a.title || '').localeCompare(b.title || '')
  );

  for (const industry of sortedIndustries) {
    formatSingleIndustry(industry, lines);
  }

  // Add footer
  lines.push('---');
  lines.push('');
  lines.push('## Work With Us');
  lines.push('');
  lines.push('Jensen Hughes has deep expertise across all these industries. To discuss how we can help with your specific needs:');
  lines.push('- Website: https://www.jensenhughes.com/industries');
  lines.push('- Email: info@jensenhughes.com');
  lines.push('');

  return lines.join('\n');
}

// Format a single industry entry
function formatSingleIndustry(industry, lines) {
  const title = industry.title || 'Untitled Industry';
  lines.push(`## ${title}`);

  // Description/summary
  if (industry.summary) {
    lines.push('');
    lines.push(stripHtml(industry.summary));
  } else if (industry.description) {
    lines.push('');
    lines.push(stripHtml(industry.description));
  } else if (industry.shortDescription) {
    lines.push('');
    lines.push(stripHtml(industry.shortDescription));
  }

  // Services offered for this industry
  if (industry.services && industry.services.length > 0) {
    const serviceNames = industry.services
      .map(s => s.title || s.name || s)
      .filter(Boolean);
    if (serviceNames.length > 0) {
      lines.push('');
      lines.push(`**Services for ${title}:** ${serviceNames.join(', ')}`);
    }
  }

  // Related services
  if (industry.relatedServices && industry.relatedServices.length > 0) {
    const relatedNames = industry.relatedServices
      .map(s => s.title || s.name || s)
      .filter(Boolean);
    if (relatedNames.length > 0) {
      lines.push('');
      lines.push(`**Related Services:** ${relatedNames.join(', ')}`);
    }
  }

  // Key capabilities or focus areas
  if (industry.capabilities && industry.capabilities.length > 0) {
    const capNames = industry.capabilities
      .map(c => c.title || c.name || c)
      .filter(Boolean);
    if (capNames.length > 0) {
      lines.push('');
      lines.push(`**Key Capabilities:** ${capNames.join(', ')}`);
    }
  }

  // Case studies or projects
  if (industry.caseStudies && industry.caseStudies.length > 0) {
    lines.push('');
    lines.push(`**Notable Projects:** ${industry.caseStudies.length} case studies available`);
  }

  // URL
  if (industry.url || industry.uri) {
    const industryUrl = industry.url || `https://www.jensenhughes.com/${industry.uri}`;
    lines.push('');
    lines.push(`**Learn More:** ${industryUrl}`);
  }

  lines.push('');
}

// Strip HTML tags from text
function stripHtml(html) {
  if (!html) return '';
  return html
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/\s+/g, ' ')
    .trim();
}

// Upload to Botpress Knowledge Base via Files API
async function uploadToBotpress(content) {
  console.log('📤 Uploading to Botpress Knowledge Base...');

  const fileKey = 'kb-sync/industries.txt';

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
    index: true,
    tags: {
      source: 'craft-cms-sync',
      kbId: config.botpressKbId,
      title: 'Jensen Hughes Industries',
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
  console.log('🔄 Jensen Hughes Industries → Botpress KB Sync');
  console.log('');

  validateConfig();

  if (config.dryRun) {
    console.log('🔍 DRY RUN MODE - No upload will occur');
    console.log('');
  }

  try {
    // Step 1: Fetch industries
    const industries = await fetchIndustries();

    // Step 2: Format for KB
    console.log('📝 Formatting industry data...');
    const content = formatIndustriesForKB(industries);
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
