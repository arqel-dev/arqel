<?php

declare(strict_types=1);

namespace Arqel\Tenant\Concerns;

/**
 * Adds opt-in feature flags to a tenant model.
 *
 * Backs onto an Eloquent `features` array attribute. The consuming
 * model is expected to declare the cast itself so the database
 * value (typically JSON column) is hydrated as an array:
 *
 * ```php
 * protected $casts = ['features' => 'array'];
 * ```
 *
 * Defensive on read: when the attribute is null, missing, or
 * stored as a non-array (legacy data), the trait treats the list
 * as empty rather than blowing up. Mutations always emit a clean
 * deduplicated `array<int, string>`.
 *
 * Trait works on plain objects too — any class exposing
 * `features` as a public property (or via `__get`/`__set`) gets
 * the same semantics. The Eloquent `setAttribute` path is the
 * primary use-case but tests use lightweight POPOs.
 *
 * @see \Arqel\Tenant\Middleware\RequireTenantFeature route gate
 */
trait HasFeatures
{
    /**
     * Whether `$feature` is enabled on this tenant.
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures(), true);
    }

    /**
     * Add `$feature` to the tenant's feature list. No-op when the
     * feature is already present (deduped). Does not persist —
     * call `save()` afterwards on Eloquent models.
     */
    public function enableFeature(string $feature): void
    {
        $features = $this->getFeatures();

        if (! in_array($feature, $features, true)) {
            $features[] = $feature;
        }

        $this->writeFeatures($features);
    }

    /**
     * Remove `$feature` from the tenant's feature list. No-op
     * when the feature isn't enabled. Does not persist — call
     * `save()` afterwards on Eloquent models.
     */
    public function disableFeature(string $feature): void
    {
        $features = array_values(array_filter(
            $this->getFeatures(),
            static fn (string $f): bool => $f !== $feature,
        ));

        $this->writeFeatures($features);
    }

    /**
     * Canonical list of features currently enabled on the tenant.
     *
     * @return array<int, string>
     */
    public function getFeatures(): array
    {
        $raw = $this->readFeaturesAttribute();

        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $value) {
            if (is_string($value) && $value !== '' && ! in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }

        return $clean;
    }

    /**
     * Read the raw `features` attribute via Eloquent's
     * `getAttribute()` so registered casts (typically `array`)
     * run.
     */
    private function readFeaturesAttribute(): mixed
    {
        if (method_exists($this, 'getAttribute')) {
            /** @var mixed $value */
            $value = $this->getAttribute('features');

            return $value;
        }

        return null;
    }

    /**
     * Write the canonical list back via `setAttribute()` so the
     * registered cast serialises the array on save.
     *
     * @param array<int, string> $features
     */
    private function writeFeatures(array $features): void
    {
        if (method_exists($this, 'setAttribute')) {
            $this->setAttribute('features', $features);
        }
    }
}
