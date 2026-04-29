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
 */
final class McpDispatchException extends RuntimeException {}
