<?php

namespace rocketpark\mcpwrapper\services;

use Craft;
use craft\base\Component;
use ReflectionClass;
use ReflectionMethod;
use rocketpark\mcpwrapper\attributes\Tool;

/**
 * Tool Registry Service
 * 
 * Discovers and manages MCP tools from both auto-generated GraphQL tools
 * and manually registered tool classes using #[Tool] attributes.
 */
class ToolRegistryService extends Component
{
    /**
     * @var string[] Class names to scan for tool methods
     */
    private array $toolClasses = [];

    /**
     * @var array Cached discovered tools
     */
    private ?array $cachedManualTools = null;

    /**
     * Register a tool class for discovery
     * 
     * @param string $className Fully qualified class name
     */
    public function registerToolClass(string $className): void
    {
        if (!in_array($className, $this->toolClasses)) {
            $this->toolClasses[] = $className;
            $this->cachedManualTools = null; // Invalidate cache
            Craft::info("Registered tool class: {$className}", 'mcp-wrapper');
        }
    }

    /**
     * Discover all tools marked with #[Tool] attribute
     * 
     * @return array Array of tool definitions
     */
    public function discoverManualTools(): array
    {
        if ($this->cachedManualTools !== null) {
            return $this->cachedManualTools;
        }

        $tools = [];
        
        foreach ($this->toolClasses as $className) {
            if (!class_exists($className)) {
                Craft::warning("Tool class not found: {$className}", 'mcp-wrapper');
                continue;
            }

            $reflection = new ReflectionClass($className);
            
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Tool::class);
                
                foreach ($attributes as $attribute) {
                    $toolAttr = $attribute->newInstance();
                    
                    $tools[] = [
                        'name' => $toolAttr->name,
                        'description' => $toolAttr->description,
                        'inputSchema' => $toolAttr->inputSchema,
                        'dangerous' => $toolAttr->dangerous,
                        'annotations' => [
                            'readOnlyHint' => !$toolAttr->dangerous,
                            'openWorldHint' => false,
                        ],
                        'handler' => [
                            'class' => $className,
                            'method' => $method->getName(),
                        ],
                    ];
                    
                    Craft::info("Discovered tool: {$toolAttr->name} from {$className}::{$method->getName()}", 'mcp-wrapper');
                }
            }
        }

        $this->cachedManualTools = $tools;
        return $tools;
    }

    /**
     * Get all tools for a schema (GraphQL auto-generated + manual tools)
     * 
     * @param string $schemaHandle GraphQL schema handle
     * @return array Combined array of all tools
     */
    public function getAllTools(string $schemaHandle): array
    {
        // Get GraphQL auto-generated tools
        $manifestBuilder = Craft::$app->getModule('mcp-wrapper')->get('manifestBuilder');
        $graphqlTools = $manifestBuilder->getGeneratedTools($schemaHandle);
        
        // Get manual tools
        $manualTools = $this->discoverManualTools();
        
        // Merge (manual tools can override GraphQL tools by name)
        $allTools = [];
        $toolNames = [];
        
        // Add manual tools first (higher priority)
        foreach ($manualTools as $tool) {
            $allTools[] = $tool;
            $toolNames[$tool['name']] = true;
        }
        
        // Add GraphQL tools that don't conflict
        foreach ($graphqlTools as $tool) {
            if (!isset($toolNames[$tool['name']])) {
                $allTools[] = $tool;
            } else {
                Craft::info("GraphQL tool '{$tool['name']}' overridden by manual tool", 'mcp-wrapper');
            }
        }
        
        Craft::info("Total tools for schema '{$schemaHandle}': " . count($allTools) . 
            " (" . count($manualTools) . " manual + " . (count($allTools) - count($manualTools)) . " GraphQL)", 'mcp-wrapper');
        
        return $allTools;
    }

    /**
     * Execute a tool by name
     * 
     * @param string $toolName Tool name
     * @param array $arguments Tool arguments
     * @return mixed Tool execution result
     */
    public function executeTool(string $toolName, array $arguments = []): mixed
    {
        $manualTools = $this->discoverManualTools();
        
        foreach ($manualTools as $tool) {
            if ($tool['name'] === $toolName) {
                return $this->invokeToolMethod($tool['handler'], $arguments);
            }
        }
        
        throw new \Exception("Tool not found: {$toolName}");
    }

    /**
     * Invoke a tool method
     * 
     * @param array $handler Handler info with class and method
     * @param array $arguments Method arguments (named array from JSON-RPC)
     * @return mixed Method return value
     */
    private function invokeToolMethod(array $handler, array $arguments): mixed
    {
        $className = $handler['class'];
        $methodName = $handler['method'];
        
        // Instantiate the tool class
        $instance = new $className();
        
        // Use reflection to map named arguments to method parameters
        $method = new \ReflectionMethod($className, $methodName);
        $params = $method->getParameters();
        
        // Special case: if method has exactly one array parameter,
        // pass all arguments as that array (for flexible parameter tools)
        if (count($params) === 1 && $params[0]->getType()?->getName() === 'array') {
            return $instance->$methodName($arguments);
        }
        
        $orderedArgs = [];
        
        // Map named arguments to positional arguments
        foreach ($params as $param) {
            $paramName = $param->getName();
            
            if (array_key_exists($paramName, $arguments)) {
                $orderedArgs[] = $arguments[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $orderedArgs[] = $param->getDefaultValue();
            } else {
                throw new \Exception("Missing required parameter: {$paramName}");
            }
        }
        
        // Call the method with ordered arguments
        return $instance->$methodName(...$orderedArgs);
    }

    /**
     * Clear cached tools
     */
    public function clearCache(): void
    {
        $this->cachedManualTools = null;
        Craft::info("Tool registry cache cleared", 'mcp-wrapper');
    }
}
