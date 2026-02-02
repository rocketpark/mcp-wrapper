<?php
/**
 * GraphQL Input Sanitization Utilities
 *
 * Provides secure string escaping and validation for GraphQL query construction.
 * Extracted to a standalone class for easier testing and reuse.
 *
 * @package rocketpark\mcpwrapper\support
 */

namespace rocketpark\mcpwrapper\support;

/**
 * GraphQL input sanitization and escaping utilities
 *
 * This class provides security-focused string handling for GraphQL queries:
 * - escapeGraphQLString() - Safe escaping using JSON encoding
 * - sanitizeStringInput() - Whitelist-based input validation
 *
 * @since 2.1.0
 */
class GraphQLSanitizer
{
    /**
     * Maximum string length for text parameters
     */
    public const MAX_STRING_LENGTH = 500;

    /**
     * Escape a string for safe use in GraphQL queries
     *
     * GraphQL strings use JSON-style escaping. This is more secure than addslashes()
     * which doesn't handle Unicode escapes, newlines properly, or GraphQL-specific injection vectors.
     *
     * @param string $value The string to escape
     * @return string The escaped string (without surrounding quotes)
     * @throws \Exception If JSON encoding fails (code -32602)
     */
    public static function escapeGraphQLString(string $value): string
    {
        // Use JSON encoding which properly escapes:
        // - Quotes (" → \")
        // - Backslashes (\ → \\)
        // - Newlines, tabs, carriage returns (\n, \t, \r)
        // - Unicode characters (properly encoded)
        // - Control characters
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        // json_encode returns false on encoding failure
        if ($encoded === false) {
            throw new \Exception('Invalid characters in input parameter', -32602);
        }

        // Remove the surrounding quotes that json_encode adds
        return substr($encoded, 1, -1);
    }

    /**
     * Sanitize string input for GraphQL queries
     *
     * Uses a whitelist approach: only allows characters that are known to be safe
     * for the expected input types (search terms, titles, dates, etc.)
     *
     * Allowed characters:
     * - Alphanumeric (a-z, A-Z, 0-9)
     * - Common punctuation (space, comma, period, hyphen, apostrophe, colon)
     * - Date/time characters (T, Z, +, /)
     * - Unicode letters (for international names/content)
     *
     * @param string $input The input string to sanitize
     * @param callable|null $warningLogger Optional callback for logging warnings (receives message and category)
     * @return string The sanitized string
     * @throws \Exception If input contains disallowed characters (code -32602)
     */
    public static function sanitizeStringInput(string $input, ?callable $warningLogger = null): string
    {
        // Remove null bytes (always dangerous)
        $input = str_replace("\0", '', $input);

        // Whitelist pattern: allows safe characters for search/filter values
        // - \p{L} = Unicode letters (for international content)
        // - \p{N} = Unicode numbers
        // - Space character only (NOT \s which allows newlines/tabs that enable log injection)
        // - Common safe punctuation: . , - ' " : ; ! ? @ # $ % & * ( ) + = / \ | < >
        // - Date separators: T Z (ISO dates)
        //
        // Explicitly BLOCKS:
        // - Backticks (`) - used in template literals
        // - Curly braces { } - GraphQL query syntax
        // - Square brackets [ ] - GraphQL array syntax
        // - Triple dots ... - GraphQL fragments
        // - Newlines, tabs, carriage returns (log injection vectors)
        $safePattern = '/^[\p{L}\p{N} .,\-\'"":;!?@#$%&*()+_=\/\\\\|<>TZ]+$/u';

        if (!preg_match($safePattern, $input)) {
            // Log the violation (truncated to prevent log injection)
            $truncatedInput = mb_substr($input, 0, 100);
            if ($warningLogger !== null) {
                $warningLogger("Input contains disallowed characters: {$truncatedInput}", 'mcp-wrapper');
            }
            throw new \Exception('Input contains invalid characters', -32602);
        }

        // Additional check: block GraphQL-specific patterns that could slip through
        // These use multi-character sequences that the single-char whitelist might miss
        $dangerousSequences = [
            '...',      // Fragment spread
            '__',       // Introspection (double underscore)
        ];

        foreach ($dangerousSequences as $sequence) {
            if (str_contains($input, $sequence)) {
                if ($warningLogger !== null) {
                    $warningLogger("Input contains dangerous sequence '{$sequence}'", 'mcp-wrapper');
                }
                throw new \Exception('Input contains invalid character sequence', -32602);
            }
        }

        return $input;
    }

    /**
     * Check if a string is a valid Craft handle
     * Handles should be alphanumeric with underscores/hyphens only
     *
     * @param string $handle The handle to validate
     * @return bool True if valid
     */
    public static function isValidHandle(string $handle): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $handle) === 1;
    }
}
