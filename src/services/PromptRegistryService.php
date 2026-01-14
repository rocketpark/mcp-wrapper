<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use craft\elements\Entry;

/**
 * Prompt Registry Service
 * 
 * Manages MCP Prompts - pre-built conversation starters with structured data
 * that help AI assistants understand and interact with Craft CMS content
 */
class PromptRegistryService extends Component
{
    /**
     * List all available prompts
     * 
     * @return array Array of prompt definitions
     */
    public function listPrompts(): array
    {
        return [
            $this->schemaExplorerPrompt(),
            $this->contentHealthPrompt(),
            $this->queryBuilderPrompt(),
        ];
    }
    
    /**
     * Get a specific prompt by name with optional arguments
     * 
     * @param string $name Prompt name
     * @param array $arguments Optional prompt arguments
     * @return array Prompt definition
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        return match($name) {
            'schema_explorer' => $this->schemaExplorerPrompt(),
            'content_health' => $this->contentHealthPrompt(),
            'query_builder' => $this->queryBuilderPrompt($arguments),
            default => throw new \Exception("Unknown prompt: {$name}"),
        };
    }
    
    /**
     * Schema Explorer Prompt
     * Helps AI understand the complete Craft content model
     */
    private function schemaExplorerPrompt(): array
    {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $sectionData = array_map(function($section) {
            $entryTypes = $section->getEntryTypes();
            if (empty($entryTypes)) {
                return null;
            }
            
            $fieldLayout = $entryTypes[0]->getFieldLayout();
            
            return [
                'handle' => $section->handle,
                'name' => $section->name,
                'type' => $section->type,
                'fields' => array_map(fn($field) => [
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => (new \ReflectionClass($field))->getShortName(),
                ], $fieldLayout->getCustomFields()),
            ];
        }, $sections);
        
        // Filter out null sections
        $sectionData = array_values(array_filter($sectionData));
        
        return [
            'name' => 'schema_explorer',
            'description' => 'Explore the Craft CMS content model, sections, fields, and relationships',
            'arguments' => [],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
I'm sharing the complete content model for this Craft CMS installation.
Please analyze it and help me understand:

1. What types of content are available?
2. What relationships exist between sections?
3. What queries would be most useful?

Content Model:
```json
{$this->formatJson($sectionData)}
```

Please provide a comprehensive overview and suggest some useful queries.
PROMPT
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Content Health Prompt
     * Analyzes content freshness, status distribution, and maintenance needs
     */
    private function contentHealthPrompt(): array
    {
        $sections = Craft::$app->getEntries()->getAllSections();
        
        $healthData = array_map(function($section) {
            $lastEntry = Entry::find()
                ->section($section->handle)
                ->orderBy('dateUpdated DESC')
                ->one();
            
            return [
                'section' => $section->handle,
                'name' => $section->name,
                'counts' => [
                    'live' => Entry::find()->section($section->handle)->status('live')->count(),
                    'disabled' => Entry::find()->section($section->handle)->status('disabled')->count(),
                    'drafts' => Entry::find()->section($section->handle)->drafts()->count(),
                ],
                'lastUpdated' => $lastEntry?->dateUpdated?->format('Y-m-d H:i:s'),
            ];
        }, $sections);
        
        return [
            'name' => 'content_health',
            'description' => 'Analyze content health, freshness, status distribution, and identify maintenance needs',
            'arguments' => [],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
Please analyze this content health report:

```json
{$this->formatJson($healthData)}
```

Provide insights on:
1. Overall content health score
2. Sections needing attention (stale content, high disabled ratio)
3. Content freshness analysis
4. Recommendations for content maintenance
PROMPT
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Query Builder Prompt
     * Helps construct GraphQL queries for specific sections
     */
    private function queryBuilderPrompt(array $arguments = []): array
    {
        $section = $arguments['section'] ?? null;
        
        if (!$section) {
            // List available sections
            $sections = Craft::$app->getEntries()->getAllSections();
            $sectionList = implode(', ', array_map(fn($s) => $s->handle, $sections));
            
            return [
                'name' => 'query_builder',
                'description' => 'Help build GraphQL queries for specific sections',
                'arguments' => [
                    ['name' => 'section', 'description' => "Section to query. Available: {$sectionList}", 'required' => true],
                ],
                'messages' => [
                    [
                        'role' => 'assistant',
                        'content' => [
                            'type' => 'text',
                            'text' => "Which section would you like to query? Available sections: {$sectionList}"
                        ]
                    ]
                ]
            ];
        }
        
        // Get section details
        $sectionModel = Craft::$app->getEntries()->getSectionByHandle($section);
        if (!$sectionModel) {
            throw new \Exception("Section not found: {$section}");
        }
        
        $entryTypes = $sectionModel->getEntryTypes();
        if (empty($entryTypes)) {
            throw new \Exception("No entry types found for section: {$section}");
        }
        
        $fieldLayout = $entryTypes[0]->getFieldLayout();
        
        return [
            'name' => 'query_builder',
            'description' => "Help build GraphQL queries for the {$sectionModel->name} section",
            'arguments' => [
                ['name' => 'section', 'description' => 'Section handle', 'required' => true],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => <<<PROMPT
I want to query the "{$sectionModel->name}" section.

Available fields:
{$this->formatFieldList($fieldLayout->getCustomFields())}

Example query:
```graphql
query {
  entries(section: "{$section}", limit: 10) {
    title
    slug
    ... add fields here
  }
}
```

Please help me build an appropriate query based on what I'm looking for.
PROMPT
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Format data as pretty JSON
     */
    private function formatJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Format field list as human-readable text
     */
    private function formatFieldList(array $fields): string
    {
        if (empty($fields)) {
            return '(No custom fields)';
        }
        
        return implode("\n", array_map(
            fn($f) => "- {$f->handle} ({$f->name}): " . (new \ReflectionClass($f))->getShortName(),
            $fields
        ));
    }
}
