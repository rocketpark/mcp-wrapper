<?php

namespace rocketpark\mcpwrapper\support;

use Craft;
use Throwable;

/**
 * Safe execution wrapper for MCP tools
 * 
 * Wraps tool execution and converts exceptions to standardized error responses
 */
class SafeExecution
{
    /**
     * Execute a callable safely and catch any exceptions
     * 
     * Converts exceptions into standardized error responses rather than
     * crashing the entire MCP server
     * 
     * @template T
     * @param callable(): T $callback Function to execute
     * @return T|array Returns callback result or error array
     */
    public static function run(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            // Log the error
            Craft::error("MCP tool execution error: {$e->getMessage()}", 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');
            
            // Return standardized error response
            return Response::error(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Execute with detailed error formatting
     * 
     * @param callable $callback Function to execute
     * @param string $context Context description for error logging
     * @return mixed Returns callback result or detailed error array
     */
    public static function runWithContext(callable $callback, string $context): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $errorMessage = self::formatErrorMessage($e, $context);
            
            Craft::error($errorMessage, 'mcp-wrapper');
            Craft::error($e->getTraceAsString(), 'mcp-wrapper');
            
            return Response::error(
                $errorMessage,
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Format detailed error message
     * 
     * @param Throwable $e Exception
     * @param string $context Context description
     * @return string Formatted error message
     */
    private static function formatErrorMessage(Throwable $e, string $context): string
    {
        $type = (new \ReflectionClass($e))->getShortName();
        $message = $e->getMessage();
        $file = basename($e->getFile());
        $line = $e->getLine();
        
        return "[{$context}] {$type}: {$message} (in {$file}:{$line})";
    }
}
