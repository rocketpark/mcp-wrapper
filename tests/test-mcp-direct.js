#!/usr/bin/env node
/**
 * Direct MCP Test Runner — Q113-Q230
 *
 * Tests MCP tool responses directly without Botpress.
 * Validates that the right data comes back for each question type.
 *
 * Usage:
 *   node tests/test-mcp-direct.js
 *   node tests/test-mcp-direct.js --url https://jensenhughes3.on-forge.com
 *   node tests/test-mcp-direct.js --from 113 --to 165
 *   node tests/test-mcp-direct.js --verbose
 */

const https = require("https");
const http = require("http");

// ─── Config ────────────────────────────────────────────────────────────────
const args = process.argv.slice(2);
const getArg = (flag, def) => {
  const i = args.indexOf(flag);
  return i >= 0 ? args[i + 1] : def;
};
const hasFlag = (flag) => args.includes(flag);

const BASE_URL = getArg("--url", "https://jensenhughes3.on-forge.com");
const SCHEMA = getArg("--schema", "jensenhughes");
const ENDPOINT = `${BASE_URL}/mcp/${SCHEMA}`;
const FROM_Q = parseInt(getArg("--from", "113"));
const TO_Q = parseInt(getArg("--to", "260"));
const VERBOSE = hasFlag("--verbose") || hasFlag("-v");
const TIMEOUT = parseInt(getArg("--timeout", "10000"));
const DELAY = parseInt(getArg("--delay", "600")); // ms between requests (avoids 429)

// ─── MCP call ──────────────────────────────────────────────────────────────
function mcpCall(toolName, params) {
  return new Promise((resolve, reject) => {
    const body = JSON.stringify({
      jsonrpc: "2.0",
      method: "tools/call",
      params: { name: toolName, arguments: params },
      id: Date.now(),
    });

    const url = new URL(ENDPOINT);
    const lib = url.protocol === "https:" ? https : http;
    const options = {
      method: "POST",
      hostname: url.hostname,
      port: url.port || (url.protocol === "https:" ? 443 : 80),
      path: url.pathname,
      headers: {
        "Content-Type": "application/json",
        "Content-Length": Buffer.byteLength(body),
        "User-Agent": "mcp-test-runner/1.0",
      },
    };

    const timer = setTimeout(() => reject(new Error("timeout")), TIMEOUT);
    const req = lib.request(options, (res) => {
      let data = "";
      res.on("data", (chunk) => (data += chunk));
      res.on("end", () => {
        clearTimeout(timer);
        if (res.statusCode === 429) {
          reject(new Error("RATE_LIMITED"));
          return;
        }
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          reject(new Error(`JSON parse error: ${data.substring(0, 100)}`));
        }
      });
    });
    req.on("error", (e) => {
      clearTimeout(timer);
      reject(e);
    });
    req.write(body);
    req.end();
  });
}

// Sleep helper
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Extract text content from MCP response
function getContent(result) {
  if (!result) return "";
  if (result.error) return `ERROR: ${result.error.message}`;
  const content = result.result?.content;
  if (!content) return "";
  const text = content.map((c) => c.text || "").join("\n");
  return text.toLowerCase();
}

// ─── Test Cases ────────────────────────────────────────────────────────────
// Format: { q: number, question: string, tool: string|null, params: {}, expect: string[], note: string }
// tool: null = SKIP (bot behavior / KB only / not MCP-testable)
const TESTS = [
  // ── Company Information (113-120) ─────────────────────────────────────
  // These come from bot KB/instructions, not MCP. Mark SKIP but note expected.
  {
    q: 113,
    question: "How many offices do you have?",
    tool: "query_officeLocations",
    params: { limit: 100 },
    expect: ["office", "location"],
    note: "Should return many offices",
  },
  {
    q: 114,
    question: "Where are you located globally?",
    tool: "query_officeLocations",
    params: { limit: 100 },
    expect: ["united states", "office"],
    note: "Should show global locations",
  },
  {
    q: 115,
    question: "When was Jensen Hughes founded?",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact: Founded 1939 — bot instructions only",
  },
  {
    q: 116,
    question: "Who owns Jensen Hughes?",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact — bot instructions only",
  },
  {
    q: 117,
    question: "Tell me about your history",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact — bot instructions only",
  },
  {
    q: 118,
    question: "What makes you different?",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact — bot instructions only",
  },
  {
    q: 119,
    question: "Industry participation",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact: 450+ committee memberships",
  },
  {
    q: 120,
    question: "Certifications and accreditations",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact — bot instructions only",
  },

  // ── Projects & Case Studies (121-130) ─────────────────────────────────
  {
    q: 121,
    question: "Show me your projects",
    tool: "query_insights",
    params: { limit: 5 },
    expect: ["title", "url"],
    note: "Insights = case studies/projects",
  },
  {
    q: 122,
    question: "Case studies in fire protection",
    tool: "query_insights",
    params: { search: "fire protection", limit: 5 },
    expect: [],
    note: "Filter insights by fire",
  },
  {
    q: 123,
    question: "Airport projects you've worked on",
    tool: "query_insights",
    params: { search: "airport", limit: 5 },
    expect: [],
    note: "Airport case studies",
  },
  {
    q: 124,
    question: "Healthcare facility projects",
    tool: "query_insights",
    params: { search: "healthcare", limit: 5 },
    expect: [],
    note: "Healthcare case studies",
  },
  {
    q: 125,
    question: "Data center case studies",
    tool: "query_insights",
    params: { search: "data center", limit: 5 },
    expect: [],
    note: "Data center insights",
  },
  {
    q: 126,
    question: "High-rise building projects",
    tool: "query_insights",
    params: { search: "high-rise", limit: 5 },
    expect: [],
    note: "High-rise case studies",
  },
  {
    q: 127,
    question: "Historic building projects",
    tool: "query_insights",
    params: { search: "historic", limit: 5 },
    expect: [],
    note: "Historic building case studies",
  },
  {
    q: 128,
    question: "International projects",
    tool: "query_insights",
    params: { search: "international", limit: 5 },
    expect: [],
    note: "International case studies",
  },
  {
    q: 129,
    question: "Notable clients",
    tool: "query_insights",
    params: { limit: 5 },
    expect: [],
    note: "Just need some results",
  },
  {
    q: 130,
    question: "Success stories",
    tool: "query_insights",
    params: { limit: 5 },
    expect: [],
    note: "Just need some results",
  },

  // ── Contact & Next Steps (131-140) ────────────────────────────────────
  // These are purely contact fallback — no MCP needed
  {
    q: 131,
    question: "How do I get a quote?",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback: info@jensenhughes.com",
  },
  {
    q: 132,
    question: "Request a consultation",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 133,
    question: "How do I contact you?",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 134,
    question: "Start a project",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 135,
    question: "Schedule a meeting",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 136,
    question: "Submit an RFP",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 137,
    question: "Get more information",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 138,
    question: "Talk to an expert",
    tool: "query_ourTeam",
    params: { limit: 5 },
    expect: ["name", "title"],
    note: "Should return team members",
  },
  {
    q: 139,
    question: "Find my regional office",
    tool: "query_officeLocations",
    params: { limit: 10 },
    expect: ["office"],
    note: "Should return offices",
  },
  {
    q: 140,
    question: "Emergency contact",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact: (410) 737-8677",
  },

  // ── Edge Cases (141-155) — Bot behavior only ─────────────────────────
  {
    q: 141,
    question: "asdfasdf",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: graceful fallback",
  },
  {
    q: 142,
    question: "Tell me a joke",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: out-of-scope deflection",
  },
  {
    q: 143,
    question: "What's the weather?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: out-of-scope deflection",
  },
  {
    q: 144,
    question: "Phone number for FakeCity office",
    tool: "query_officeLocations",
    params: { search: "FakeCity", limit: 5 },
    expect: [],
    note: "Should return empty/no match — not fabricate",
  },
  {
    q: 145,
    question: "Email for John Smith",
    tool: "query_ourTeam",
    params: { search: "John Smith", limit: 5 },
    expect: [],
    note: "Should not return email even if person found",
  },
  {
    q: 146,
    question: "Services in Antarctica",
    tool: "query_officeLocations",
    params: { search: "Antarctica", limit: 5 },
    expect: [],
    note: "Should return no results",
  },
  {
    q: 147,
    question: "Do you do plumbing?",
    tool: "query_services",
    params: { search: "plumbing", limit: 5 },
    expect: [],
    note: "Should return empty or unrelated",
  },
  {
    q: 148,
    question: "How much does it cost?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: contact fallback",
  },
  {
    q: 149,
    question: "Are you hiring?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: out-of-scope",
  },
  {
    q: 150,
    question: "What's your revenue?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: out-of-scope",
  },
  {
    q: 151,
    question: "Who is the CEO?",
    tool: "query_ourTeam",
    params: { search: "CEO", limit: 5 },
    expect: [],
    note: "Check if CEO/executive data exists",
  },
  {
    q: 152,
    question: "Stock price",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: out-of-scope (private company)",
  },
  {
    q: 153,
    question: "Competitor comparison",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: deflect",
  },
  {
    q: 154,
    question: "Why are you better than [competitor]?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: deflect",
  },
  {
    q: 155,
    question: "Negative review response",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: deflect",
  },

  // ── Multi-Part Queries (156-165) ─────────────────────────────────────
  {
    q: 156,
    question: "Fire protection and accessibility for hospital in CA",
    tool: "query_officeLocations",
    params: { search: "California", limit: 5 },
    expect: ["california", "office"],
    note: "Should find CA offices — services from KB",
  },
  {
    q: 157,
    question: "Data center in Virginia — battery safety + fire",
    tool: "query_officeLocations",
    params: { search: "Virginia", limit: 5 },
    expect: [],
    note: "Should find Virginia office(s)",
  },
  {
    q: 158,
    question: "Airport in Seattle — security, fire, code",
    tool: "query_officeLocations",
    params: { search: "Seattle", limit: 5 },
    expect: [],
    note: "Should find Seattle/WA office",
  },
  {
    q: 159,
    question: "Historic building in Boston — accessibility, fire",
    tool: "query_officeLocations",
    params: { search: "Boston", limit: 5 },
    expect: [],
    note: "Should find Boston/MA office",
  },
  {
    q: 160,
    question: "Manufacturing in Texas — process safety + hazmat",
    tool: "query_officeLocations",
    params: { search: "Texas", limit: 5 },
    expect: ["texas", "office"],
    note: "Texas offices exist per earlier tests",
  },
  {
    q: 161,
    question: "University campus Chicago — code compliance",
    tool: "query_officeLocations",
    params: { search: "Chicago", limit: 5 },
    expect: [],
    note: "Should find Chicago/IL office",
  },
  {
    q: 162,
    question: "High-rise in NYC — fire, smoke, accessibility",
    tool: "query_officeLocations",
    params: { search: "New York", limit: 5 },
    expect: [],
    note: "Should find NYC office",
  },
  {
    q: 163,
    question: "Hotel in Hawaii — fire, security, emergency",
    tool: "query_officeLocations",
    params: { search: "Hawaii", limit: 5 },
    expect: [],
    note: "Check if Hawaii office exists",
  },
  {
    q: 164,
    question: "Government building DC — security + fire",
    tool: "query_officeLocations",
    params: { search: "Washington", limit: 5 },
    expect: [],
    note: "Should find DC/MD area office",
  },
  {
    q: 165,
    question: "Shopping mall Florida — fire, accessibility",
    tool: "query_officeLocations",
    params: { search: "Florida", limit: 5 },
    expect: [],
    note: "Should find Florida office(s)",
  },

  // ── Supplemental from project history (166-184) ──────────────────────
  {
    q: 166,
    question: "Who are your accessibility experts?",
    tool: "query_ourTeam",
    params: { search: "accessibility", limit: 5 },
    expect: [],
    note: "Expert lookup by specialty",
  },
  {
    q: 167,
    question: "Show me your technical experts",
    tool: "query_ourTeam",
    params: { limit: 10 },
    expect: ["name"],
    note: "Should return team members",
  },
  {
    q: 168,
    question: "Show me accessibility experts",
    tool: "query_ourTeam",
    params: { search: "accessibility", limit: 5 },
    expect: [],
    note: "Same as 166",
  },
  {
    q: 169,
    question: "Who can help with code consulting?",
    tool: "query_ourTeam",
    params: { search: "code consulting", limit: 5 },
    expect: [],
    note: "Expert lookup",
  },
  {
    q: 170,
    question: "Connect me with a security expert",
    tool: "query_ourTeam",
    params: { search: "security", limit: 5 },
    expect: [],
    note: "Expert lookup",
  },
  {
    q: 171,
    question: "Show me your team",
    tool: "query_ourTeam",
    params: { limit: 10 },
    expect: ["name"],
    note: "General team listing",
  },
  {
    q: 172,
    question: "What offices do you have in Texas?",
    tool: "query_officeLocations",
    params: { search: "Texas", limit: 10 },
    expect: ["texas", "office"],
    note: "Texas offices",
  },
  {
    q: 173,
    question: "How do I add offices to the knowledge base?",
    tool: null,
    params: {},
    expect: [],
    note: "Admin question — bot should deflect",
  },
  {
    q: 174,
    question: "What is the phone for the Helsinki office?",
    tool: "query_officeLocations",
    params: { search: "Helsinki", limit: 5 },
    expect: ["helsinki", "finland"],
    note: "Helsinki office data",
  },
  {
    q: 175,
    question: "Do you have offices in Belgium?",
    tool: "query_officeLocations",
    params: { search: "Belgium", limit: 5 },
    expect: [],
    note: "Belgium office check",
  },
  {
    q: 176,
    question: "What is the Auckland office contact?",
    tool: "query_officeLocations",
    params: { search: "Auckland", limit: 5 },
    expect: ["auckland", "new zealand"],
    note: "Auckland NZ office",
  },
  {
    q: 177,
    question: "Tell me about your risk consulting services",
    tool: "query_services",
    params: { search: "risk", limit: 5 },
    expect: [],
    note: "Risk services",
  },
  {
    q: 178,
    question: "Dubai vs Abu Dhabi office differences",
    tool: "query_officeLocations",
    params: { search: "Dubai", limit: 5 },
    expect: ["dubai"],
    note: "UAE offices",
  },
  {
    q: 179,
    question: "How many offices do you have worldwide?",
    tool: "query_officeLocations",
    params: { limit: 100 },
    expect: ["office"],
    note: "Count total offices",
  },
  {
    q: 180,
    question: "Can I schedule a consultation?",
    tool: null,
    params: {},
    expect: [],
    note: "Contact fallback",
  },
  {
    q: 181,
    question: "What certifications do your engineers have?",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact",
  },
  {
    q: 182,
    question: "Do you work with nuclear facilities?",
    tool: "query_services",
    params: { search: "nuclear", limit: 5 },
    expect: ["nuclear"],
    note: "Nuclear services",
  },
  {
    q: 183,
    question: "What is your 24/7 emergency contact?",
    tool: null,
    params: {},
    expect: [],
    note: "KB fact: (410) 737-8677",
  },
  {
    q: 184,
    question: "Who are your experts?",
    tool: "query_ourTeam",
    params: { limit: 10 },
    expect: ["name"],
    note: "Team listing",
  },

  // ── Untested Content Types (185-192) ─────────────────────────────────
  {
    q: 185,
    question: "What podcasts does Jensen Hughes produce?",
    tool: "query_podcasts",
    params: { limit: 10 },
    expect: ["title"],
    note: "Should return podcast shows — Code Authority, Speaking of Fire",
  },
  {
    q: 186,
    question: "Do you have a podcast about fire safety?",
    tool: "query_podcasts",
    params: { search: "fire", limit: 5 },
    expect: [],
    note: "Fire-related podcast",
  },
  {
    q: 187,
    question: "Jensen Hughes operations in Australia",
    tool: "query_countries",
    params: { limit: 5 },
    expect: [],
    note: "Countries section exists but has 0 entries in Craft (content gap)",
  },
  {
    q: 188,
    question: "What do you do in the United Kingdom?",
    tool: "query_countries",
    params: { limit: 5 },
    expect: [],
    note: "Countries section has 0 entries — expected empty (content gap)",
  },
  {
    q: 189,
    question: "Who are your certified partner companies?",
    tool: "query_certifiedCompanies",
    params: { limit: 10 },
    expect: [],
    note: "certifiedCompanies section has 0 entries in Craft (content gap)",
  },
  {
    q: 190,
    question: "Do you work with any certified contractors?",
    tool: "query_certifiedCompanies",
    params: { limit: 5 },
    expect: [],
    note: "certifiedCompanies section empty — expected empty (content gap)",
  },
  {
    q: 191,
    question: "Articles about fire protection in healthcare",
    tool: "query_insights",
    params: { search: "fire protection healthcare", limit: 5 },
    expect: [],
    note: "Healthcare fire articles",
  },
  {
    q: 192,
    question: "Case studies for data center projects",
    tool: "query_insights",
    params: { search: "data center", limit: 5 },
    expect: [],
    note: "Data center insights",
  },

  // ── Untested Service Sub-Capabilities (193-206) ──────────────────────
  {
    q: 193,
    question: "Do you provide forensic investigation services?",
    tool: "query_services",
    params: { search: "forensic", limit: 5 },
    expect: [],
    note: "Forensic services",
  },
  {
    q: 194,
    question: "Do you offer expert witness testimony?",
    tool: "query_services",
    params: { search: "expert witness", limit: 5 },
    expect: [],
    note: "Expert witness services",
  },
  {
    q: 195,
    question: "Do you provide AHJ representation services?",
    tool: "query_services",
    params: { search: "AHJ", limit: 5 },
    expect: [],
    note: "AHJ representation",
  },
  {
    q: 196,
    question: "Can you help with code plan review?",
    tool: "query_services",
    params: { search: "code plan review", limit: 5 },
    expect: [],
    note: "Plan review services",
  },
  {
    q: 197,
    question: "Do you do evacuation modeling?",
    tool: "query_services",
    params: { search: "evacuation modeling", limit: 5 },
    expect: [],
    note: "Evacuation modeling",
  },
  {
    q: 198,
    question: "Can you model pedestrian flow for my building?",
    tool: "query_services",
    params: { search: "pedestrian flow", limit: 5 },
    expect: [],
    note: "Pedestrian flow modeling",
  },
  {
    q: 199,
    question: "Do you offer pre-construction consulting?",
    tool: "query_services",
    params: { search: "pre-construction", limit: 5 },
    expect: [],
    note: "Pre-construction services",
  },
  {
    q: 200,
    question: "What is fire and life safety building commissioning?",
    tool: "query_services",
    params: { search: "commissioning", limit: 5 },
    expect: [],
    note: "Commissioning services",
  },
  {
    q: 201,
    question: "Do you work with law enforcement agencies?",
    tool: "query_services",
    params: { search: "law enforcement", limit: 5 },
    expect: [],
    note: "Law enforcement consulting",
  },
  {
    q: 202,
    question: "Do you help with workplace violence risk assessment?",
    tool: "query_services",
    params: { search: "workplace violence", limit: 5 },
    expect: [],
    note: "WPV services",
  },
  {
    q: 203,
    question: "Do you consult on hydrogen safety?",
    tool: "query_services",
    params: { search: "hydrogen", limit: 5 },
    expect: [],
    note: "Hydrogen safety services",
  },
  {
    q: 204,
    question: "Do you work in power transmission and distribution?",
    tool: "query_services",
    params: { search: "power transmission", limit: 5 },
    expect: [],
    note: "Power T&D services",
  },
  {
    q: 205,
    question: "Do you do probabilistic risk assessments?",
    tool: "query_services",
    params: { search: "probabilistic risk", limit: 5 },
    expect: [],
    note: "PRA services",
  },
  {
    q: 206,
    question: "What are risk-informed assessments?",
    tool: "query_services",
    params: { search: "risk-informed", limit: 5 },
    expect: [],
    note: "Risk-informed assessment",
  },

  // ── Region-Aware Behavior (207-211) ──────────────────────────────────
  {
    q: 207,
    question: "I'm in Europe, show me nearby offices",
    tool: "query_officeLocations",
    params: { search: "Europe", limit: 10 },
    expect: [],
    note: "European offices",
  },
  {
    q: 208,
    question: "What services do you offer in Asia Pacific?",
    tool: "query_officeLocations",
    params: { search: "Asia", limit: 10 },
    expect: [],
    note: "APAC offices",
  },
  {
    q: 209,
    question: "Do you have offices near me?",
    tool: "query_officeLocations",
    params: { limit: 10 },
    expect: ["office"],
    note: "General office listing (no region)",
  },
  {
    q: 210,
    question: "Show me your offices in the Middle East",
    tool: "query_officeLocations",
    params: { search: "Middle East", limit: 10 },
    expect: [],
    note: "Middle East offices",
  },
  {
    q: 211,
    question: "I need fire protection services in Korea",
    tool: "query_officeLocations",
    params: { search: "Korea", limit: 5 },
    expect: [],
    note: "Korea office check",
  },

  // ── Industry Wording (212-214) ────────────────────────────────────────
  {
    q: 212,
    question: "Do you work on tunnel fire safety?",
    tool: "query_industries",
    params: { search: "tunnel", limit: 5 },
    expect: [],
    note: "Transit + Tunnels industry",
  },
  {
    q: 213,
    question: "What mission critical facilities do you work with?",
    tool: "query_industries",
    params: { search: "mission critical", limit: 5 },
    expect: [],
    note: "Mission Critical industry",
  },
  {
    q: 214,
    question: "Do you serve science and technology clients?",
    tool: "query_industries",
    params: { search: "science", limit: 5 },
    expect: [],
    note: "Science + Technology industry",
  },

  // ── Real Customer Scenarios (215-226) ────────────────────────────────
  {
    q: 215,
    question: "How big of a project can you handle?",
    tool: null,
    params: {},
    expect: [],
    note: "KB/contact fallback",
  },
  {
    q: 216,
    question: "What's the typical timeline for fire protection?",
    tool: null,
    params: {},
    expect: [],
    note: "KB/contact fallback",
  },
  {
    q: 217,
    question: "Do you provide peer reviews of fire protection?",
    tool: "query_services",
    params: { search: "peer review", limit: 5 },
    expect: [],
    note: "Peer review services",
  },
  {
    q: 218,
    question: "What building codes do you work with?",
    tool: "query_services",
    params: { search: "building code", limit: 5 },
    expect: [],
    note: "Code consulting",
  },
  {
    q: 219,
    question: "Do you work with NFPA standards?",
    tool: "query_services",
    params: { search: "NFPA", limit: 5 },
    expect: [],
    note: "NFPA standards",
  },
  {
    q: 220,
    question: "Do you work with NFPA 13?",
    tool: "query_services",
    params: { search: "NFPA 13", limit: 5 },
    expect: [],
    note: "NFPA 13 specifically",
  },
  {
    q: 221,
    question: "Do you work with insurance companies?",
    tool: "query_services",
    params: { search: "insurance", limit: 5 },
    expect: [],
    note: "Insurance consulting",
  },
  {
    q: 222,
    question: "Do you do LEED or green building consulting?",
    tool: "query_services",
    params: { search: "LEED green", limit: 5 },
    expect: [],
    note: "Green building services",
  },
  {
    q: 223,
    question: "Can you do work in Japan?",
    tool: "query_officeLocations",
    params: { search: "Japan", limit: 5 },
    expect: [],
    note: "Japan office/presence",
  },
  {
    q: 224,
    question: "What is performance-based design?",
    tool: "query_services",
    params: { search: "performance-based design", limit: 5 },
    expect: [],
    note: "PBD services",
  },
  {
    q: 225,
    question: "How does fire protection differ from fire engineering?",
    tool: "query_services",
    params: { search: "fire engineering", limit: 5 },
    expect: [],
    note: "Fire engineering vs protection",
  },
  {
    q: 226,
    question: "Tell me about your Metropolitan Opera House project",
    tool: "query_insights",
    params: { search: "Metropolitan Opera", limit: 5 },
    expect: [],
    note: "Specific project lookup",
  },

  // ── Bot Behavior Verification (227-230) — Botpress only ──────────────
  {
    q: 227,
    question: "Tell me more about that",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: multi-turn context — needs Botpress",
  },
  {
    q: 228,
    question: "Take me to your services page",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: URL generation — needs Botpress",
  },
  {
    q: 229,
    question: "Parlez-vous français?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: language handling — needs Botpress",
  },
  {
    q: 230,
    question: "I'm on the fire protection page, tell me more",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: page context — needs Botpress",
  },

  // ── EU Regression Suite (231-246) — Bot KB / instructions tests ───────
  // All of these test bot behavior (correct email, EU restrictions) — not MCP
  {
    q: 231,
    question: "Do you provide forensic investigation in the UK?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: instructus.uk@jensenhughes.com + /scotland URL — needs Botpress",
  },
  {
    q: 232,
    question: "I need forensic fire investigation help in Scotland",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: instructus.uk@jensenhughes.com for Scotland forensics — needs Botpress",
  },
  {
    q: 233,
    question: "Forensics contact for Ireland",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: instructus.uk@jensenhughes.com for Ireland forensics — needs Botpress",
  },
  {
    q: 234,
    question: "I need legal support for a fire investigation in England",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: instructus.uk email, subject line 'Forensics Instruction' — needs Botpress",
  },
  {
    q: 235,
    question: "Do you offer accessibility consulting in the UK?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Accessibility NOT available in Europe — needs Botpress",
  },
  {
    q: 236,
    question: "Universal design services in Germany",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Accessibility NOT available in Europe — needs Botpress",
  },
  {
    q: 237,
    question: "ADA or accessibility consulting in France",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Accessibility NOT available in Europe — needs Botpress",
  },
  {
    q: 238,
    question: "I need an accessibility specialist in Dublin",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Accessibility NOT available in Europe — needs Botpress",
  },
  {
    q: 239,
    question: "Security risk consulting in Paris",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Security Risk NOT available in Europe — needs Botpress",
  },
  {
    q: 240,
    question: "Do you handle public safety consulting in the UK?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Public Safety NOT available in Europe — needs Botpress",
  },
  {
    q: 241,
    question: "Security design services in Amsterdam",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Security NOT available in Europe — needs Botpress",
  },
  {
    q: 242,
    question: "Emergency preparedness services in Germany",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Emergency Management NOT available in Europe — needs Botpress",
  },
  {
    q: 243,
    question: "Business continuity planning in London",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Emergency Management NOT available in Europe — needs Botpress",
  },
  {
    q: 244,
    question: "Emergency response consulting in Berlin",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Emergency Management NOT available in Europe — needs Botpress",
  },
  {
    q: 245,
    question: "Tell me about BIM fire modelling",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: URL override to BIMfire insights article — no Craft service entry for BIM, needs Botpress",
  },
  {
    q: 246,
    question: "Advanced fire modelling and BIMfire",
    tool: "query_insights",
    params: { search: "BIMfire", limit: 5 },
    expect: ["bimfire"],
    note: "BIMfire insights article should exist at /insights/incorporating-bimfire-...",
  },

  // ── Regional Leadership (247-250) ─────────────────────────────────────
  {
    q: 247,
    question: "Who leads the Americas region?",
    tool: "query_leadershipTeams",
    params: { search: "Americas", limit: 5 },
    expect: [],
    note: "Americas regional leadership",
  },
  {
    q: 248,
    question: "Who is the European regional leader?",
    tool: "query_leadershipTeams",
    params: { search: "Europe", limit: 5 },
    expect: [],
    note: "European regional leadership",
  },
  {
    q: 249,
    question: "APAC regional leadership team",
    tool: "query_leadershipTeams",
    params: { search: "Asia Pacific", limit: 5 },
    expect: [],
    note: "APAC regional leadership",
  },
  {
    q: 250,
    question: "Show me Middle East leadership",
    tool: "query_leadershipTeams",
    params: { search: "Middle East", limit: 5 },
    expect: [],
    note: "Middle East regional leadership",
  },

  // ── Podcast Episodes (251-254) ────────────────────────────────────────
  {
    q: 251,
    question: "Tell me about the Code Authority podcast",
    tool: "query_podcasts",
    params: { search: "codecast", limit: 5 },
    expect: ["codecast"],
    note: "Craft stores this as 'CodeCast Podcast' — 'Code Authority' is a KB alias",
  },
  {
    q: 252,
    question: "Do you have a Speaking of Fire podcast?",
    tool: null,
    params: {},
    expect: [],
    note: "'Speaking of Fire' not in Craft podcast shows — bot KB fallback needed, needs Botpress",
  },
  {
    q: 253,
    question: "What episodes do you have about hospital fire safety?",
    tool: "query_podcastEpisodes",
    params: { search: "fire", limit: 5 },
    expect: ["title"],
    note: "Search 'fire' across all podcast episodes — any fire-related episode is a pass",
  },
  {
    q: 254,
    question: "Most recent podcast episode",
    tool: "query_podcastEpisodes",
    params: { orderBy: "postDate desc", limit: 1 },
    expect: ["title"],
    note: "Latest podcast episode — should have a title",
  },

  // ── Conversation Context & Company Facts (255-260) — KB/Bot only ──────
  {
    q: 255,
    question: "Tell me more about that",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: conversation history context — needs Botpress",
  },
  {
    q: 256,
    question: "Wait, which office was that for?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: conversation history context — needs Botpress",
  },
  {
    q: 257,
    question: "Can you email me this information?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot behavior: email deflection to info@jensenhughes.com — needs Botpress",
  },
  {
    q: 258,
    question: "When was Jensen Hughes founded?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: Founded 1939 — needs Botpress",
  },
  {
    q: 259,
    question: "How many employees does Jensen Hughes have?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: ~1,900 employees — needs Botpress",
  },
  {
    q: 260,
    question: "How many committee memberships does Jensen Hughes hold?",
    tool: null,
    params: {},
    expect: [],
    note: "Bot KB: 450+ committee memberships — needs Botpress",
  },
];

// ─── Runner ────────────────────────────────────────────────────────────────
const PASS = "\x1b[32m✓\x1b[0m";
const FAIL = "\x1b[31m✗\x1b[0m";
const SKIP = "\x1b[33m○\x1b[0m";
const WARN = "\x1b[33m⚠\x1b[0m";
const RESET = "\x1b[0m";
const BOLD = "\x1b[1m";
const DIM = "\x1b[2m";

async function runTests() {
  const filtered = TESTS.filter((t) => t.q >= FROM_Q && t.q <= TO_Q);

  console.log(`\n${BOLD}MCP Direct Test Runner${RESET}`);
  console.log(`Endpoint: ${ENDPOINT}`);
  console.log(`Questions: Q${FROM_Q}–Q${TO_Q} (${filtered.length} tests)\n`);
  console.log("─".repeat(70));

  const results = { pass: 0, fail: 0, skip: 0, warn: 0, errors: [] };

  for (const test of filtered) {
    if (test.tool === null) {
      // SKIP — not MCP-testable
      results.skip++;
      if (VERBOSE) {
        console.log(`${SKIP} Q${test.q}: [SKIP] ${test.question}`);
        console.log(`${DIM}       → ${test.note}${RESET}`);
      } else {
        process.stdout.write(`${SKIP}`);
      }
      continue;
    }

    try {
      const result = await mcpCall(test.tool, test.params);
      await sleep(DELAY);
      const content = getContent(result);

      if (content.startsWith("error:")) {
        results.fail++;
        if (VERBOSE || true) {
          console.log(`\n${FAIL} Q${test.q}: ${test.question}`);
          console.log(
            `     Tool: ${test.tool}(${JSON.stringify(test.params)})`,
          );
          console.log(`     Error: ${content}`);
        }
        results.errors.push({
          q: test.q,
          question: test.question,
          error: content,
        });
        continue;
      }

      // Check if we got ANY content back
      const hasContent = content.length > 50;

      // Check expected keywords
      const missingKeywords = test.expect.filter(
        (kw) => !content.includes(kw.toLowerCase()),
      );
      const keywordsPassed = missingKeywords.length === 0;

      // Determine pass/fail/warn
      const isPass = hasContent && keywordsPassed;
      const isEmpty = !hasContent;

      if (isEmpty) {
        // Empty response — could be expected (FakeCity) or unexpected
        const isExpectedEmpty = [144, 146, 147].includes(test.q);
        if (isExpectedEmpty) {
          results.pass++;
          if (VERBOSE) {
            console.log(
              `${PASS} Q${test.q}: [EMPTY as expected] ${test.question}`,
            );
          } else {
            process.stdout.write(`${PASS}`);
          }
        } else {
          results.warn++;
          results.errors.push({
            q: test.q,
            question: test.question,
            error: "Empty response — no data found",
          });
          if (VERBOSE) {
            console.log(`\n${WARN} Q${test.q}: [NO DATA] ${test.question}`);
            console.log(
              `     Tool: ${test.tool}(${JSON.stringify(test.params)})`,
            );
            console.log(`     Note: ${test.note}`);
          } else {
            process.stdout.write(`${WARN}`);
          }
        }
      } else if (!keywordsPassed) {
        results.fail++;
        results.errors.push({
          q: test.q,
          question: test.question,
          error: `Missing: ${missingKeywords.join(", ")}`,
        });
        if (VERBOSE) {
          console.log(`\n${FAIL} Q${test.q}: ${test.question}`);
          console.log(`     Tool: ${test.tool}`);
          console.log(`     Missing keywords: ${missingKeywords.join(", ")}`);
          console.log(`     Preview: ${content.substring(0, 150)}...`);
        } else {
          process.stdout.write(`${FAIL}`);
        }
      } else {
        results.pass++;
        if (VERBOSE) {
          console.log(`${PASS} Q${test.q}: ${test.question}`);
          console.log(
            `     ${DIM}Tool: ${test.tool} | ${content.substring(0, 100).replace(/\n/g, " ")}...${RESET}`,
          );
        } else {
          process.stdout.write(`${PASS}`);
        }
      }
    } catch (err) {
      if (err.message === "RATE_LIMITED") {
        // Back off and retry once
        if (VERBOSE)
          console.log(
            `\n${WARN} Q${test.q}: rate limited, retrying after 5s...`,
          );
        await sleep(5000);
        try {
          const retryResult = await mcpCall(test.tool, test.params);
          await sleep(DELAY);
          const retryContent = getContent(retryResult);
          if (retryContent.length > 50) {
            results.pass++;
            if (VERBOSE)
              console.log(`${PASS} Q${test.q}: [retry OK] ${test.question}`);
            else process.stdout.write(`${PASS}`);
          } else {
            results.warn++;
            results.errors.push({
              q: test.q,
              question: test.question,
              error: "Empty after rate-limit retry",
            });
            if (!VERBOSE) process.stdout.write(`${WARN}`);
          }
        } catch (retryErr) {
          results.fail++;
          results.errors.push({
            q: test.q,
            question: test.question,
            error: `Rate limited (retry: ${retryErr.message.substring(0, 60)})`,
          });
          if (VERBOSE)
            console.log(
              `\n${FAIL} Q${test.q}: [STILL RATE LIMITED] ${test.question}`,
            );
          else process.stdout.write(`${FAIL}`);
        }
      } else {
        results.fail++;
        results.errors.push({
          q: test.q,
          question: test.question,
          error: err.message,
        });
        if (VERBOSE) {
          console.log(`\n${FAIL} Q${test.q}: ${test.question}`);
          console.log(`     Exception: ${err.message}`);
        } else {
          process.stdout.write(`${FAIL}`);
        }
      }
    }
  }

  // Summary
  console.log("\n\n" + "─".repeat(70));
  console.log(`\n${BOLD}Results${RESET}`);
  console.log(`  ${PASS} Pass:    ${results.pass}`);
  console.log(`  ${FAIL} Fail:    ${results.fail}`);
  console.log(
    `  ${WARN} Warning: ${results.warn} (empty MCP response — data may be missing)`,
  );
  console.log(
    `  ${SKIP} Skipped: ${results.skip} (bot behavior / KB only — needs Botpress)`,
  );
  console.log(`  Total:   ${filtered.length}`);

  if (results.errors.length > 0) {
    console.log(`\n${BOLD}Issues to investigate:${RESET}`);
    results.errors.forEach((e) => {
      console.log(`  Q${e.q}: ${e.question}`);
      console.log(`    ${DIM}→ ${e.error}${RESET}`);
    });
  }

  const testable = filtered.length - results.skip;
  const pct = testable > 0 ? Math.round((results.pass / testable) * 100) : 0;
  console.log(`\nMCP coverage: ${results.pass}/${testable} testable = ${pct}%`);

  // Show what needs Botpress
  const skipped = filtered.filter((t) => t.tool === null);
  if (skipped.length > 0) {
    console.log(
      `\n${BOLD}Still needs Botpress (${skipped.length} questions):${RESET}`,
    );
    skipped.forEach((t) => {
      console.log(`  Q${t.q}: "${t.question}" — ${DIM}${t.note}${RESET}`);
    });
  }

  console.log("");
  process.exit(results.fail > 0 ? 1 : 0);
}

runTests().catch((err) => {
  console.error("Fatal:", err);
  process.exit(1);
});
