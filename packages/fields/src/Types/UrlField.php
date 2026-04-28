<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * URL input.
 *
 * Adds the Laravel `url` validation rule by default. See the
 * `HasValidation` note on EmailField for the rule-merging plan.
 */
final class UrlField extends TextField
{
    protected string $type = 'url';

    protected string $component = 'UrlInput';

    /**
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        return ['url'];
    }
}
