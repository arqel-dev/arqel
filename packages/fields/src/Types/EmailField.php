<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Email input.
 *
 * Adds the Laravel `email` validation rule by default; the
 * `HasValidation` trait (FIELDS-015) will pick the rules up from
 * `getDefaultRules()` and merge them with user-defined rules.
 */
final class EmailField extends TextField
{
    protected string $type = 'email';

    protected string $component = 'EmailInput';

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return ['email'];
    }
}
