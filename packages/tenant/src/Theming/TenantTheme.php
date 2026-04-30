<?php

declare(strict_types=1);

namespace Arqel\Tenant\Theming;

use Illuminate\Database\Eloquent\Model;

/**
 * White-labeling theme settings for a tenant — readonly value-object.
 *
 * Surfaces 5 nullable customisation slots that consumers can persist on
 * the tenant model and bubble up into the Inertia share() of the host app.
 * This package does NOT inject the values into the request lifecycle —
 * that's a cross-package wiring concern (see SKILL.md TENANT-012 entry).
 *
 * The factory `fromTenant()` reads the canonical column names
 * (`primary_color`, `logo_url`, `font_family`, `secondary_color`,
 * `favicon_url`) defensively: missing/non-string attributes degrade to
 * null rather than throwing.
 */
final class TenantTheme
{
    public function __construct(
        public readonly ?string $primaryColor = null,
        public readonly ?string $logoUrl = null,
        public readonly ?string $fontFamily = null,
        public readonly ?string $secondaryColor = null,
        public readonly ?string $faviconUrl = null,
    ) {}

    public static function fromTenant(?Model $tenant): self
    {
        if ($tenant === null) {
            return new self;
        }

        return new self(
            primaryColor: self::stringAttribute($tenant, 'primary_color'),
            logoUrl: self::stringAttribute($tenant, 'logo_url'),
            fontFamily: self::stringAttribute($tenant, 'font_family'),
            secondaryColor: self::stringAttribute($tenant, 'secondary_color'),
            faviconUrl: self::stringAttribute($tenant, 'favicon_url'),
        );
    }

    /**
     * @return array{primaryColor: ?string, logoUrl: ?string, fontFamily: ?string, secondaryColor: ?string, faviconUrl: ?string}
     */
    public function toArray(): array
    {
        return [
            'primaryColor' => $this->primaryColor,
            'logoUrl' => $this->logoUrl,
            'fontFamily' => $this->fontFamily,
            'secondaryColor' => $this->secondaryColor,
            'faviconUrl' => $this->faviconUrl,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->primaryColor === null
            && $this->logoUrl === null
            && $this->fontFamily === null
            && $this->secondaryColor === null
            && $this->faviconUrl === null;
    }

    private static function stringAttribute(Model $tenant, string $key): ?string
    {
        $value = $tenant->getAttribute($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
