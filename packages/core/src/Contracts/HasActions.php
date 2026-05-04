<?php

declare(strict_types=1);

namespace Arqel\Core\Contracts;

/**
 * Marker interface for classes that contribute Actions.
 *
 * The concrete `actions()` and `tableActions()` methods will be added
 * once `arqel-dev/actions` and `arqel-dev/table` ship (ACTIONS-*, TABLE-*).
 * Today the contract is a marker so the registry and controller can
 * branch on capability without forcing a method signature that would
 * have to be reshaped later.
 */
interface HasActions {}
