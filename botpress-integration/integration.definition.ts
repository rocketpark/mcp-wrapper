import { IntegrationDefinition, z } from '@botpress/sdk'

export default new IntegrationDefinition({
  name: 'craftcms-mcp',
  version: '1.0.6',
  title: 'Craft CMS via MCP',
  description: 'Query Craft CMS content through the Model Context Protocol (MCP) server',
  icon: 'icon.svg',
  readme: 'hub.md',
  
  configuration: {
    schema: z.object({
      mcpServerUrl: z.string().default('https://servicecurator.com').describe('Base URL of your Craft CMS MCP server'),
      schemaHandle: z.string().default('public').describe('The GraphQL schema handle to use (e.g., public, ai, jensenhughes)'),
    }),
  },

  actions: {
    listTools: {
      title: 'List Available MCP Tools',
      description: 'Retrieves all available content types (sections) from Craft CMS',
      input: {
        schema: z.object({}),
      },
      output: {
        schema: z.object({
          tools: z.array(z.object({
            name: z.string(),
            description: z.string(),
          })),
        }),
      },
    },

    queryContent: {
      title: 'Query Craft CMS Content',
      description: 'Query content from a specific Craft CMS section with full Craft query parameters',
      input: {
        schema: z.object({
          toolName: z.string().describe('Name of the MCP tool to call (e.g., query_news, query_topics, craft_get_office_contact_info)'),
          // Common filters
          search: z.string().optional().describe('Full-text search query'),
          title: z.string().optional().describe('Filter by entry title'),
          slug: z.union([z.string(), z.array(z.string())]).optional().describe('Single slug string (for craft_get_office_contact_info) or array of slugs (for filtering)'),
          id: z.array(z.number()).optional().describe('Filter by specific entry IDs'),
          // Status filters
          status: z.array(z.string()).optional().describe('Filter by status (live, pending, expired, disabled)'),
          // Date filters
          before: z.string().optional().describe('Filter entries posted before this date'),
          after: z.string().optional().describe('Filter entries posted after this date'),
          dateCreated: z.string().optional().describe('Filter by creation date'),
          dateUpdated: z.string().optional().describe('Filter by update date'),
          // Pagination & ordering
          limit: z.number().default(10).describe('Maximum number of entries to return (1-100)'),
          offset: z.number().default(0).describe('Number of entries to skip'),
          orderBy: z.string().optional().describe('Order results (e.g., "dateCreated DESC", "title ASC")'),
          // Region-aware queries: pass site handle to get URLs with correct regional prefix
          site: z.union([z.string(), z.array(z.string())]).optional().describe('Craft site handle for region-aware URLs (jensenHughesEurope, jensenHughesPacific, jensenHughesAsia, jensenHughesMiddleEast, jensenHughesDigital)'),
          siteId: z.number().optional().describe('Craft site ID (alternative to site handle)'),
          // Region shortcut: bot can pass the user region directly instead of computing the site handle.
          // If site is not provided and region is one of europe/pacific/asia/middle_east, the
          // integration auto-derives the matching Craft site handle server-side. This removes the
          // need for the bot to remember the site mapping and ensures regional URLs are always used.
          region: z.string().optional().describe('IMPORTANT: pass the value of {{user.data.region}} on every call so regional URLs are correct. Allowed values: europe, pacific, asia, middle_east, north_america. If omitted, the integration falls back to discovering the visitor region from Botpress user data (may race on the first call after page load).'),
          section: z.union([z.string(), z.array(z.string())]).optional().describe('Section handle for craft_search_entries (e.g., insights, services, industries, ourTeam)'),
        }),
      },
      output: {
        schema: z.object({
          content: z.array(z.any()).describe('Raw MCP response content. When tool returns a list of entries, content[0].formattedText is pre-built markdown link list ready for Message() pass-through.'),
        }),
      },
    },

    getEntry: {
      title: 'Get Specific Entry',
      description: 'Retrieve a specific Craft CMS entry by ID',
      input: {
        schema: z.object({
          toolName: z.string().describe('Name of the MCP tool to call'),
          id: z.string().describe('The ID of the entry to retrieve'),
        }),
      },
      output: {
        schema: z.object({
          entry: z.any().nullable().describe('The requested entry'),
        }),
      },
    },

    intelligentSearch: {
      title: 'Intelligent Search',
      description: 'Natural language search that automatically understands user intent and queries the right content',
      input: {
        schema: z.object({
          query: z.string().describe('Natural language query (e.g., "show me recent sustainability news")'),
        }),
      },
      output: {
        schema: z.object({
          results: z.array(z.any()).describe('Search results'),
          summary: z.string().describe('Human-readable summary'),
          searchDetails: z.object({
            extractedTerms: z.string(),
            contentTypes: z.array(z.string()),
            appliedFilters: z.record(z.any()),
          }).describe('What the search understood'),
        }),
      },
    },

    answerQuestion: {
      title: 'Answer Question with Knowledge Base',
      description: 'Answer questions using your Craft CMS content as the knowledge source',
      input: {
        schema: z.object({
          question: z.string().describe('User question to answer'),
          searchSections: z.array(z.string()).optional().describe('Which sections to search (default: all)'),
        }),
      },
      output: {
        schema: z.object({
          answer: z.string().describe('AI-generated answer'),
          sources: z.array(z.object({
            title: z.string(),
            url: z.string().optional(),
          })).describe('Content sources used'),
        }),
      },
    },
  },

  events: {},
  channels: {},
  user: {
    tags: {},
  },
})
