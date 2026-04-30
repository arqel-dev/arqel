<?php

declare(strict_types=1);

namespace Arqel\Workflow\Tests\Fixtures;

/**
 * Transition fixture without a `from()` method — should be treated as
 * always-available by `HasWorkflow::getAvailableTransitions()`.
 */
final class AnyToCancelled {}
