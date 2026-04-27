<?php

declare(strict_types=1);

namespace Arqel\Core\Contracts;

/**
 * Contract every Arqel Resource class must satisfy.
 *
 * A Resource declares the metadata used by the registry to expose
 * a model in panels (slug-based routing, navigation, labels). All
 * methods are static so the registry can introspect classes without
 * instantiating them.
 */
interface HasResource
{
    /**
     * Fully qualified class name of the Eloquent model this resource manages.
     *
     * @return class-string
     */
    public static function getModel(): string;

    /**
     * URL-safe identifier used for routing (e.g. "users", "blog-posts").
     */
    public static function getSlug(): string;

    /**
     * Human-readable singular label (e.g. "User").
     */
    public static function getLabel(): string;

    /**
     * Human-readable plural label (e.g. "Users").
     */
    public static function getPluralLabel(): string;

    /**
     * Optional navigation icon name (heroicons / lucide identifier).
     */
    public static function getNavigationIcon(): ?string;

    /**
     * Optional navigation group label (e.g. "Content", "System").
     */
    public static function getNavigationGroup(): ?string;

    /**
     * Optional navigation sort order; lower values appear first.
     */
    public static function getNavigationSort(): ?int;
}
