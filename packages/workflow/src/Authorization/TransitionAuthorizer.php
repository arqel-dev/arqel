<?php

declare(strict_types=1);

namespace Arqel\Workflow\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Throwable;

/**
 * Authorizer central de transitions.
 *
 * Decide se um par `(transitionClass, record, user)` é permitido seguindo
 * a ordem de precedência:
 *
 *  1. Se a `transitionClass` declara um método `authorizeFor(?Authenticatable $user, mixed $record): bool`
 *     (estático ou de instância), o retorno desse método é a resposta. Exceções
 *     lançadas pela própria classe degradam para `false` — falhar fechado.
 *  2. Senão, se a aplicação registrou uma Gate com nome
 *     `transition-{fromSlug}-to-{toSlug}` (via `Gate::define`/policies), delega
 *     para `Gate::forUser($user)->allows(...)`.
 *  3. Senão, **deny by default** — comportamento controlado pela flag de config
 *     `arqel-workflow.authorization.deny_when_undefined` (default `true`).
 *     Apps em migração podem definir a flag como `false` para preservar o
 *     comportamento legado de `WF-003` (autorizar tudo na ausência de gate).
 *
 * **Breaking change vs WF-003:** o `StateTransitionField` original retornava
 * `true` quando nenhuma Gate estava registrada. Esta classe inverte o default
 * para *deny-by-default* — opt-out documentado via flag.
 */
final readonly class TransitionAuthorizer
{
    /**
     * Autoriza (ou nega) uma transition para um par user+record.
     *
     * Aceita qualquer string para que callers não precisem provar
     * `class-string` antes de chamar — o método valida via `class_exists`
     * e devolve `false` para inputs inválidos.
     */
    public static function authorize(string $transitionClass, ?Authenticatable $user, mixed $record): bool
    {
        if (! class_exists($transitionClass)) {
            return false;
        }

        $explicit = self::callAuthorizeFor($transitionClass, $user, $record);

        if ($explicit !== null) {
            return $explicit;
        }

        $from = self::resolveFrom($transitionClass);
        $to = self::resolveTo($transitionClass);

        $ability = sprintf(
            'transition-%s-to-%s',
            self::slugifyState($from ?? '*'),
            self::slugifyState($to),
        );

        if (self::gateHas($ability)) {
            try {
                return Gate::forUser($user)->allows($ability, $record);
            } catch (Throwable) {
                return false;
            }
        }

        $denyDefault = (bool) config('arqel-workflow.authorization.deny_when_undefined', true);

        return ! $denyDefault;
    }

    /**
     * Converte um identificador de state (FQCN ou string slug) na slug kebab-case
     * usada nas abilities. Pega o segmento final do FQCN, remove sufixo `State`
     * e aplica kebab-case.
     *
     *  - `App\States\PendingPayment`      → `pending-payment`
     *  - `App\States\PendingPaymentState` → `pending-payment`
     *  - `'pending'`                      → `'pending'`
     *  - `'PaidState'`                    → `'paid'`
     */
    public static function slugifyState(string $stateClassOrKey): string
    {
        if ($stateClassOrKey === '' || $stateClassOrKey === '*') {
            return '*';
        }

        $segment = $stateClassOrKey;

        if (str_contains($segment, '\\')) {
            $pos = strrpos($segment, '\\');
            $segment = $pos === false ? $segment : substr($segment, $pos + 1);
        }

        if (str_ends_with($segment, 'State') && strlen($segment) > 5) {
            $segment = substr($segment, 0, -5);
        }

        $kebab = (string) preg_replace('/(?<!^)(?=[A-Z])/', '-', $segment);

        return strtolower($kebab);
    }

    /**
     * Tenta `authorizeFor` (estático ou de instância) na transition.
     *
     * @param class-string $transitionClass
     */
    private static function callAuthorizeFor(string $transitionClass, ?Authenticatable $user, mixed $record): ?bool
    {
        if (! method_exists($transitionClass, 'authorizeFor')) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($transitionClass, 'authorizeFor');

            if (! $reflection->isPublic()) {
                return null;
            }

            if ($reflection->isStatic()) {
                /** @var mixed $result */
                $result = $reflection->invoke(null, $user, $record);
            } else {
                $instance = new $transitionClass;
                /** @var mixed $result */
                $result = $reflection->invoke($instance, $user, $record);
            }

            return (bool) $result;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve o primeiro `from()` da transition (igual `HasWorkflow::transitionApplies`).
     * Retorna `null` quando ausente — abilities ficam com `*` no slot from.
     *
     * @param class-string $transitionClass
     */
    private static function resolveFrom(string $transitionClass): ?string
    {
        if (! method_exists($transitionClass, 'from')) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($transitionClass, 'from');

            if (! $reflection->isStatic() || ! $reflection->isPublic()) {
                return null;
            }

            /** @var mixed $result */
            $result = $reflection->invoke(null);

            if (! is_array($result)) {
                return null;
            }

            foreach ($result as $candidate) {
                if (is_string($candidate) && $candidate !== '') {
                    return $candidate;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Resolve o `to` da transition. Tenta método estático `to()`; se ausente,
     * deriva do nome da classe via convenção `XxxToYyy`.
     *
     * @param class-string $transitionClass
     */
    private static function resolveTo(string $transitionClass): string
    {
        if (method_exists($transitionClass, 'to')) {
            try {
                $reflection = new ReflectionMethod($transitionClass, 'to');

                if ($reflection->isStatic() && $reflection->isPublic()) {
                    /** @var mixed $result */
                    $result = $reflection->invoke(null);

                    if (is_string($result) && $result !== '') {
                        return $result;
                    }
                }
            } catch (Throwable) {
                // fall through
            }
        }

        $short = self::shortName($transitionClass);

        if (preg_match('/To([A-Z][A-Za-z0-9]*)$/', $short, $matches) === 1) {
            return $matches[1];
        }

        return $short;
    }

    private static function shortName(string $value): string
    {
        if (! str_contains($value, '\\')) {
            return $value;
        }

        $pos = strrpos($value, '\\');

        return $pos === false ? $value : substr($value, $pos + 1);
    }

    /**
     * Wrapper defensivo em torno de `Gate::has()` — facade pode não estar
     * inicializada em testes/standalone (e.g. quando o app não foi bootado).
     */
    private static function gateHas(string $ability): bool
    {
        try {
            if (! Gate::getFacadeRoot()) {
                return false;
            }

            return Gate::has($ability);
        } catch (Throwable) {
            return false;
        }
    }
}
