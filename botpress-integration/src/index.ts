import * as sdk from '@botpress/sdk'
import * as bp from '../.botpress'
import fetch from 'node-fetch'

/**
 * MCP Client for communicating with Craft CMS MCP server
 */
class MCPClient {
  constructor(
    private baseUrl: string,
    private schemaHandle: string,
    private logger: any
  ) {}

  /**
   * Make a JSON-RPC 2.0 request to the MCP server
   */
  private async makeRequest(method: string, params: any = {}): Promise<any> {
    const endpoint = `${this.baseUrl}/mcp/${this.schemaHandle}`
    
    const request = {
      jsonrpc: '2.0',
      id: Date.now(),
      method,
      params,
    }

    this.logger.forBot().info(`🔵 MCP Request to ${endpoint}`)
    this.logger.forBot().info(`🔵 Request body: ${JSON.stringify(request)}`)

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      })

      this.logger.forBot().info(`🔵 Response status: ${response.status} ${response.statusText}`)

      if (!response.ok) {
        const errorText = await response.text()
        this.logger.forBot().error(`🔴 HTTP Error Response: ${errorText}`)
        throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`)
      }

      const responseText = await response.text()
      this.logger.forBot().info(`🔵 Raw response: ${responseText}`)
      
      const data = JSON.parse(responseText)
      
      if ((data as any).error) {
        this.logger.forBot().error(`🔴 MCP Error: ${JSON.stringify((data as any).error)}`)
        throw new Error(`MCP Error: ${(data as any).error.message} (code: ${(data as any).error.code})`)
      }

      this.logger.forBot().info(`🟢 MCP Success! Result: ${JSON.stringify((data as any).result)}`)
      return (data as any).result
    } catch (error) {
      this.logger.forBot().error(`🔴 MCP request failed: ${error}`)
      this.logger.forBot().error(`🔴 Error stack: ${(error as Error).stack}`)
      throw error
    }
  }

  /**
   * List all available MCP tools
   */
  async listTools() {
    return await this.makeRequest('tools/list')
  }

  /**
   * Call an MCP tool
   */
  async callTool(toolName: string, arguments_: any) {
    return await this.makeRequest('tools/call', {
      name: toolName,
      arguments: arguments_,
    })
  }
}

export default new bp.Integration({
  register: async () => {
    // Called when integration is installed
    // No special setup needed for this integration
  },

  unregister: async () => {
    // Called when integration is uninstalled
    // No cleanup needed for this integration
  },

  actions: {
    /**
     * List all available Craft CMS content types
     */
    listTools: async ({ ctx, logger }) => {
      const { mcpServerUrl, schemaHandle } = ctx.configuration
      logger.forBot().info(`Configuration: URL=${mcpServerUrl}, Schema=${schemaHandle}`)
      
      if (!mcpServerUrl || !schemaHandle) {
        // Return error in data instead of throwing
        return { 
          tools: [{ 
            name: 'ERROR', 
            description: `Configuration missing: URL=${mcpServerUrl}, Schema=${schemaHandle}` 
          }] 
        }
      }
      
      const client = new MCPClient(mcpServerUrl, schemaHandle, logger)

      try {
        logger.forBot().info('Calling MCP server to list tools...')
        const result = await client.listTools()
        logger.forBot().info(`Received ${result.tools?.length || 0} tools`)
        
        // If result is empty or malformed, return debug info
        if (!result || !result.tools || result.tools.length === 0) {
          return {
            tools: [{
              name: 'DEBUG',
              description: `MCP returned empty result. Raw response: ${JSON.stringify(result)}`
            }]
          }
        }
        
        return { tools: result.tools || [] }
      } catch (error) {
        logger.forBot().error(`Failed to list tools: ${error}`)
        logger.forBot().error(`Error details: ${JSON.stringify(error)}`)
        // Return error in data so user can see it
        return {
          tools: [{
            name: 'ERROR',
            description: `Failed to connect: ${(error as Error).message}`
          }]
        }
      }
    },

    /**
     * Query Craft CMS content
     */
    queryContent: async ({ ctx, input, logger }) => {
      const { mcpServerUrl, schemaHandle } = ctx.configuration
      if (!mcpServerUrl || !schemaHandle) {
        throw new Error('MCP Server URL and Schema Handle are required')
      }
      
      const { toolName, ...queryArgs } = input
      const client = new MCPClient(mcpServerUrl, schemaHandle, logger)

      try {
        logger.forBot().info(`Querying ${toolName} with args: ${JSON.stringify(queryArgs)}`)
        
        // For craft_get_office_contact_info, pass slug directly
        if (toolName === 'craft_get_office_contact_info') {
          const slug = typeof queryArgs.slug === 'string' ? queryArgs.slug : (Array.isArray(queryArgs.slug) ? queryArgs.slug[0] : queryArgs.slug)
          
          logger.forBot().info(`Getting office contact info for slug: ${slug} (type: ${typeof slug})`)
          
          if (!slug) {
            throw new Error('slug parameter is required for craft_get_office_contact_info')
          }
          
          const result = await client.callTool(toolName, { slug })
          
          logger.forBot().info(`Office contact info result: ${JSON.stringify(result).substring(0, 500)}`)
          
          return {
            content: result.content || [],
          }
        }
        
        // For office locations with search terms, fetch all and filter by region
        if (toolName === 'query_officeLocations' && queryArgs.search) {
          const searchTerm = queryArgs.search.toLowerCase()
          logger.forBot().info(`Office location search for: ${searchTerm}`)
          
          // Get all offices (up to 100 to ensure we capture all regional offices)
          const result = await client.callTool(toolName, {
            limit: 100,
            offset: 0,
          })
          
          if (result.content?.[0]?.text) {
            const data = JSON.parse(result.content[0].text)
            const allEntries = data.entries || []
            
            // Filter by search term in title, slug, summary, or ANY region title
            const filtered = allEntries.filter((entry: any) => {
              // Check title, slug, summary
              const mainText = [
                entry.title || '',
                entry.slug || '',
                entry.officeSummary || ''
              ].join(' ').toLowerCase()
              
              if (mainText.includes(searchTerm)) return true
              
              // Check each region title individually
              const regions = entry.region || []
              return regions.some((r: any) => {
                const regionTitle = (r.title || '').toLowerCase()
                return regionTitle.includes(searchTerm)
              })
            })
            
            logger.forBot().info(`Filtered ${filtered.length} offices from ${allEntries.length} total`)
            
            // Apply limit and offset to filtered results
            const limit = queryArgs.limit || 10
            const offset = queryArgs.offset || 0
            const paginatedResults = filtered.slice(offset, offset + limit)
            
            return {
              content: [{
                type: 'text',
                text: JSON.stringify({ entries: paginatedResults })
              }],
            }
          }
        }
        
        // Standard query for non-office-location or non-search queries
        const toolArguments: any = {
          limit: queryArgs.limit || 10,
          offset: queryArgs.offset || 0,
        }

        // Add all optional parameters if provided
        if (queryArgs.search) toolArguments.search = queryArgs.search
        if (queryArgs.title) toolArguments.title = queryArgs.title
        if (queryArgs.slug) toolArguments.slug = queryArgs.slug
        if (queryArgs.id) toolArguments.id = queryArgs.id
        if (queryArgs.status) toolArguments.status = queryArgs.status
        if (queryArgs.before) toolArguments.before = queryArgs.before
        if (queryArgs.after) toolArguments.after = queryArgs.after
        if (queryArgs.dateCreated) toolArguments.dateCreated = queryArgs.dateCreated
        if (queryArgs.dateUpdated) toolArguments.dateUpdated = queryArgs.dateUpdated
        if (queryArgs.orderBy) toolArguments.orderBy = queryArgs.orderBy

        const result = await client.callTool(toolName, toolArguments)
        
        logger.forBot().info(`Query result: ${JSON.stringify(result).substring(0, 200)}...`)
        
        // MCP tools/call returns: { content: [{ type: 'text', text: '...' }], isError: false }
        // The 'text' field contains JSON-encoded GraphQL results
        return {
          content: result.content || [],
        }
      } catch (error) {
        logger.forBot().error(`Failed to query content: ${error}`)
        throw new Error(`Failed to query Craft CMS content: ${(error as Error).message}`)
      }
    },

    /**
     * Get a specific entry by ID
     */
    getEntry: async ({ ctx, input, logger }) => {
      const { mcpServerUrl, schemaHandle } = ctx.configuration
      if (!mcpServerUrl || !schemaHandle) {
        throw new Error('MCP Server URL and Schema Handle are required')
      }
      const { toolName, id } = input
      const client = new MCPClient(mcpServerUrl, schemaHandle, logger)

      try {
        const result = await client.callTool(toolName, { id })
        
        return {
          entry: result.content?.[0] || null,
        }
      } catch (error) {
        logger.forBot().error(`Failed to get entry: ${error}`)
        throw new Error(`Failed to get Craft CMS entry: ${error}`)
      }
    },

    /**
     * Intelligent search with natural language understanding
     */
    intelligentSearch: async ({ ctx, input, logger }) => {
      const { mcpServerUrl, schemaHandle } = ctx.configuration
      const client = new MCPClient(mcpServerUrl, schemaHandle, logger)
      
      try {
        const { query } = input
        logger.forBot().info(`Intelligent search: ${query}`)

        // Parse natural language
        const parsed = parseNaturalLanguage(query)
        logger.forBot().info(`Parsed: ${JSON.stringify(parsed)}`)

        // Get available tools first
        const toolsList = await client.listTools()
        const availableTools = toolsList.tools.map((t: any) => t.name.replace('query_', ''))
        
        // Filter to only query sections that exist
        const sectionsToQuery = parsed.contentTypes.filter((ct: string) => 
          availableTools.includes(ct)
        )
        
        if (sectionsToQuery.length === 0) {
          sectionsToQuery.push(...availableTools) // Query all if none specified
        }

        // Query each section
        const allResults: any[] = []
        for (const section of sectionsToQuery) {
          try {
            const result = await client.callTool(`query_${section}`, parsed.filters)
            if (result.content?.[0]?.text) {
              const data = JSON.parse(result.content[0].text)
              const entries = data.entries || []
              allResults.push(...entries.map((e: any) => ({ ...e, _section: section })))
            }
          } catch (err) {
            logger.forBot().warn(`Failed to query ${section}: ${err}`)
          }
        }

        const summary = allResults.length > 0
          ? `Found ${allResults.length} results for "${parsed.searchTerms}"`
          : `No results found for "${parsed.searchTerms}"`

        return {
          results: allResults,
          summary,
          searchDetails: {
            extractedTerms: parsed.searchTerms,
            contentTypes: sectionsToQuery,
            appliedFilters: parsed.filters,
          },
        }
      } catch (error) {
        logger.forBot().error(`Intelligent search failed: ${error}`)
        throw new Error(`Search failed: ${(error as Error).message}`)
      }
    },

    /**
     * Answer questions using content as knowledge base
     */
    answerQuestion: async ({ ctx, input, logger }) => {
      const { mcpServerUrl, schemaHandle } = ctx.configuration
      const client = new MCPClient(mcpServerUrl, schemaHandle, logger)
      
      try {
        const { question, searchSections } = input
        logger.forBot().info(`Answering: ${question}`)

        // Extract keywords from question (remove common question words)
        const stopWords = /\b(what|are|is|how|do|does|can|the|a|an|to|for|in|on|of|about|with|should|would|could|where)\b/gi
        const keywordList = question
          .toLowerCase()
          .replace(/\?/g, '')
          .replace(stopWords, ' ')
          .trim()
          .split(/\s+/)
          .filter(word => word.length > 3)
        
        // Use primary keyword for search (first meaningful word)
        const primaryKeyword = keywordList[0] || question.toLowerCase()

        logger.forBot().info(`Extracted keywords: ${keywordList.join(', ')} | Using primary: "${primaryKeyword}"`)

        // Get available tools
        const toolsList = await client.listTools()
        const sections = searchSections || toolsList.tools.map((t: any) => t.name.replace('query_', ''))

        // Search for relevant content using primary keyword
        const relevantContent: any[] = []
        for (const section of sections) {
          try {
            // For office locations, try without search first to get all, then filter
            if (section === 'officeLocations' && keywordList.length > 0) {
              const result = await client.callTool(`query_${section}`, {
                limit: 100, // Get all offices to search through
              })
              if (result.content?.[0]?.text) {
                const data = JSON.parse(result.content[0].text)
                const entries = data.entries || []
                // Filter entries that match any keyword in title, slug, summary OR any region title
                const filtered = entries.filter((e: any) => {
                  const mainText = [
                    e.title || '',
                    e.slug || '',
                    e.officeSummary || ''
                  ].join(' ').toLowerCase()
                  
                  // Check main text first
                  if (keywordList.some(kw => mainText.includes(kw.toLowerCase()))) {
                    return true
                  }
                  
                  // Check each region title individually
                  const regions = e.region || []
                  return regions.some((r: any) => {
                    const regionTitle = (r.title || '').toLowerCase()
                    return keywordList.some(kw => regionTitle.includes(kw.toLowerCase()))
                  })
                })
                relevantContent.push(...filtered)
              }
            } else {
              // Skip sections known to be empty or misconfigured
              const emptyOrProblematicSections = [
                'services', 'servicesBrowse', 'servicesBrowseEurope',
                'podcasts', 'podcastsBrowse', 'podcastEpisodes'
              ]
              if (emptyOrProblematicSections.includes(section)) {
                logger.forBot().info(`Skipping ${section} (known to be empty/misconfigured)`)
                continue
              }
              
              const result = await client.callTool(`query_${section}`, {
                search: primaryKeyword,
                limit: 5,
              })
              if (result.content?.[0]?.text) {
                const data = JSON.parse(result.content[0].text)
                const entries = data.entries || data.results || []
                if (entries.length > 0) {
                  relevantContent.push(...entries)
                }
              }
            }
          } catch (err) {
            logger.forBot().warn(`Could not query ${section}: ${err}`)
          }
        }

        logger.forBot().info(`Found ${relevantContent.length} relevant entries`)

        // Generate answer from content
        const answer = relevantContent.length > 0
          ? `Based on ${relevantContent.length} article${relevantContent.length > 1 ? 's' : ''}, here's what I found:\n\n` +
            relevantContent.slice(0, 3).map((e, i) => 
              `${i + 1}. **${e.title}**\n   ${e.summary || 'No summary available'}`
            ).join('\n\n')
          : `I couldn't find specific information about "${question}" in the content.`

        const sources = relevantContent.slice(0, 5).map(e => ({
          title: e.title,
          url: e.url || `https://servicecurator.com/${e._section || 'content'}/${e.slug}`,
        }))

        return { answer, sources }
      } catch (error) {
        logger.forBot().error(`Answer question failed: ${error}`)
        throw new Error(`Failed to answer: ${(error as Error).message}`)
      }
    },
  },

  channels: {},
  handler: async () => {
    // No webhook handler needed for this integration
    return {
      status: 200,
      body: JSON.stringify({ message: 'Integration active' }),
    }
  },
})

/**
 * Parse natural language query into structured search parameters
 */
function parseNaturalLanguage(query: string) {
  const lower = query.toLowerCase()
  
  // Detect content types
  const contentTypes: string[] = []
  if (lower.includes('news') || lower.includes('article')) contentTypes.push('news')
  if (lower.includes('topic')) contentTypes.push('topics')
  
  // Extract search terms - be more permissive
  const stopWords = ['show', 'me', 'find', 'get', 'about', 'the', 'a', 'an', 'news', 'topic', 'topics', 'article', 'articles']
  const words = query.toLowerCase().split(/\s+/).filter(w => w.length > 0)
  const searchTerms = words.filter(w => !stopWords.includes(w)).join(' ')
  
  // Build filters
  const filters: any = { limit: 10 }
  
  if (lower.includes('recent') || lower.includes('latest')) {
    filters.orderBy = 'dateCreated DESC'
    filters.limit = 5
  }
  
  if (lower.includes('last week')) {
    const date = new Date()
    date.setDate(date.getDate() - 7)
    filters.after = date.toISOString().split('T')[0]
  }
  
  if (lower.includes('last month')) {
    const date = new Date()
    date.setMonth(date.getMonth() - 1)
    filters.after = date.toISOString().split('T')[0]
  }
  
  // Only add search if we have actual search terms
  if (searchTerms && searchTerms.length > 0) {
    filters.search = searchTerms
  }
  
  return {
    searchTerms: searchTerms || query,
    contentTypes,
    filters,
  }
}
