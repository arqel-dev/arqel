<?php

declare(strict_types=1);

namespace Arqel\Realtime\Exceptions;

use RuntimeException;

/**
 * Base runtime exception for `arqel/realtime`.
 *
 * Thrown when callers misuse package APIs in ways that cannot be
 * recovered at runtime (e.g., requesting a presence channel while
 * presence is globally disabled via config).
 */
final class RealtimeException extends RuntimeException {}
