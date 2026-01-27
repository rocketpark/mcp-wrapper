<?php

namespace rocketpark\mcpwrapper\support;

/**
 * Request Timeout Exception
 *
 * Thrown when an MCP request exceeds the configured timeout.
 * Per MCP spec: implementations SHOULD establish timeouts for sent requests.
 */
class RequestTimeoutException extends \Exception
{
    /**
     * JSON-RPC error code for request timeout
     * Using -32000 to -32099 range (implementation-defined server errors)
     */
    public const ERROR_CODE = -32001;

    public function __construct(string $message = 'Request timeout', ?\Throwable $previous = null)
    {
        parent::__construct($message, self::ERROR_CODE, $previous);
    }
}
