<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Trait demonstrativa para integração com `arqel-dev/workflow`.
 *
 * Em produção, o pacote `arqel-dev/workflow` provê este trait com transições
 * registradas via `WorkflowRegistry`. Aqui mantemos uma versão minimal para
 * permitir que o demo funcione standalone em CI sem dependências circulares.
 */
trait HasWorkflow
{
    /**
     * Tenta transicionar o estado do model. Retorna true se válido.
     *
     * @param array<int, string> $allowed
     */
    public function transitionTo(string $next, array $allowed): bool
    {
        $current = (string) ($this->attributes['state'] ?? 'draft');
        if (! in_array($next, $allowed, true)) {
            return false;
        }
        $this->attributes['state'] = $next;
        $this->save();

        return true;
    }
}
