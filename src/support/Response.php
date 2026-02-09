<?php

namespace rocketpark\mcpwrapper\support;

/**
 * Standardized response helpers for MCP tools
 * 
 * Provides consistent response formats across all tools
 */
class Response
{
    /**
     * Success response with data
     * 
     * @param array $data Additional data to include
     * @return array Response with success flag
     */
    public static function success(array $data = []): array
    {
        return ['success' => true, ...$data];
    }

    /**
     * Error response
     * 
     * @param string $message Error message
     * @param int $code Error code
     * @return array Response with error details
     */
    public static function error(string $message, int $code = 500): array
    {
        return [
            'success' => false,
            'error' => $message,
            'code' => $code,
        ];
    }

    /**
     * Found response for single-item lookups
     * 
     * @param string $key Key name for the found item
     * @param mixed $data Item data
     * @return array Response with found flag
     */
    public static function found(string $key, mixed $data): array
    {
        return [
            'found' => true,
            $key => $data,
        ];
    }

    /**
     * Not found response
     * 
     * @param string $message Optional message
     * @return array Response with not found flag
     */
    public static function notFound(string $message = 'Item not found'): array
    {
        return [
            'found' => false,
            'message' => $message,
        ];
    }

    /**
     * List response with count and items
     * 
     * @param string $key Key name for the items array
     * @param array $items Array of items
     * @param array $meta Additional metadata
     * @return array Response with count and items
     */
    public static function list(string $key, array $items, array $meta = []): array
    {
        return [
            'count' => count($items),
            ...$meta,
            $key => $items,
        ];
    }

    /**
     * Paginated list response
     * 
     * @param string $key Key name for the items array
     * @param array $items Array of items for current page
     * @param int $total Total number of items
     * @param int $limit Items per page
     * @param int $offset Starting offset
     * @return array Response with pagination details
     */
    public static function paginated(
        string $key,
        array $items,
        int $total,
        int $limit,
        int $offset
    ): array {
        return [
            'count' => count($items),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'hasMore' => ($offset + count($items)) < $total,
            $key => $items,
        ];
    }
}
