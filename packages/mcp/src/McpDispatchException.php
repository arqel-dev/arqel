<?php

declare(strict_types=1);

namespace Arqel\Mcp;

use RuntimeException;

/**
 * Internal control-flow exception used by {@see McpServer::dispatch()}
 * to surface JSON-RPC errors with a specific spec error code (e.g.
 * `-32601` Method not found, `-32602` Invalid params). Caught by
 * {@see McpServer::handleRequest()} and translated into the wire
 * envelope. Generic Throwables become `-32603` Internal error.
 *
 * Argument-validation handlers (tools/call, prompts/get) raise this via
 * {@see invalidParams()} so missing/invalid CALL arguments map to
 * `-32602 Invalid params` instead of being swallowed by the generic
 * `-32603 Internal error` catch (#143). Genuine internal faults keep
 * throwing plain `RuntimeException` and stay `-32603`.
 */
final class McpDispatchException extends RuntimeException
{
    /** JSON-RPC 2.0 `-32602 Invalid params`. */
    public const INVALID_PARAMS = -32602;

    /**
     * Build an exception that maps to JSON-RPC `-32602 Invalid params`.
     *
     * Use for user-correctable bad-argument conditions (missing/invalid
     * tool or prompt call arguments) so conforming MCP clients label them
     * as recoverable rather than as a server crash.
     */
    public static function invalidParams(string $message): self
    {
        return new self($message, self::INVALID_PARAMS);
    }
}
