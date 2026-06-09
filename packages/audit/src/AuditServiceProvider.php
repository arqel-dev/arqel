<?php

declare(strict_types=1);

namespace Arqel\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Spatie\Activitylog\CauserResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel-dev/audit`.
 *
 * - The Spatie ActivityLog provider (auto-discovered from
 *   `spatie/laravel-activitylog`) is responsible for the `activity_log`
 *   migration + `Activity` model bindings — we deliberately do not
 *   re-register them here.
 *
 * - Causer/morph-map safety (issue #230): Spatie persists the activity
 *   causer through a `MorphTo` (`causer()->associate($authUser)`), which
 *   calls `$authUser->getMorphClass()`. Under `Relation::enforceMorphMap()`
 *   (a strict map) that throws `ClassMorphViolationException` unless the
 *   auth provider model is mapped. We decorate Spatie's `CauserResolver`
 *   to auto-register the configured auth provider model(s) in the morph
 *   map just-in-time — but ONLY when a strict map is active and the model
 *   is not already mapped, never overriding an app's own alias and never
 *   forcing a map into existence when none is enforced.
 */
final class AuditServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-audit')
            ->hasConfigFile('audit')
            ->hasRoute('admin');
    }

    public function packageBooted(): void
    {
        $this->registerCauserMorphMapGuard();
    }

    /**
     * Install a non-destructive decorator on Spatie's causer resolution so
     * the auth provider model is present in the morph map at the moment the
     * causer is associated (and thus serialised via `getMorphClass()`).
     */
    private function registerCauserMorphMapGuard(): void
    {
        if (! $this->app->bound(CauserResolver::class)) {
            return;
        }

        $resolver = $this->app->make(CauserResolver::class);

        $resolver->resolveUsing(function (Model|int|string|null $subject = null) use ($resolver): ?Model {
            // Reproduce Spatie's default resolution (auth user / explicit
            // subject), then ensure the resolved causer is mappable.
            $causer = $subject instanceof Model
                ? $subject
                : ($subject === null
                    ? $this->resolveDefaultCauser()
                    : $resolver->resolve($subject));

            if ($causer instanceof Model) {
                self::ensureMorphMapForCauser($causer);
            }

            return $causer;
        });
    }

    /**
     * Mirror Spatie's default causer (the authenticated user under the
     * configured activitylog auth driver).
     */
    private function resolveDefaultCauser(): ?Model
    {
        /** @var string|null $driver */
        $driver = config('activitylog.default_auth_driver');

        $user = auth()->guard($driver)->user();

        return $user instanceof Model ? $user : null;
    }

    /**
     * Register the causer's class in the morph map under a derived alias —
     * but only when a strict map is active and the class is not already
     * mapped. Idempotent and non-destructive: an existing alias for the
     * class (app-supplied or ours) is left untouched.
     */
    public static function ensureMorphMapForCauser(Model $causer): void
    {
        // Never force a map into existence — only act under an enforced map.
        if (! Relation::requiresMorphMap()) {
            return;
        }

        $class = $causer::class;

        // Already mapped (by the app or a previous call) — never override.
        if (Relation::getMorphAlias($class) !== $class) {
            return;
        }

        Relation::morphMap([self::morphAliasFor($class) => $class]);
    }

    /**
     * Derive a stable, collision-resistant morph alias for the auth model.
     *
     * Uses the snake-cased class basename (e.g. `App\Models\User` -> `user`)
     * when that alias is free, otherwise falls back to the full snake-cased
     * namespaced path so two distinct auth models cannot collide.
     */
    private static function morphAliasFor(string $class): string
    {
        $basename = Str::snake(class_basename($class));

        $mapped = Relation::getMorphedModel($basename);

        if ($mapped === null || $mapped === $class) {
            return $basename;
        }

        return Str::snake(str_replace('\\', ' ', $class));
    }
}
