<?php

declare(strict_types=1);

namespace Arqel\Fields\Concerns;

use Closure;

/**
 * Validation rule builder for fields.
 *
 * The trait keeps three parallel maps keyed by a normalised rule
 * fingerprint:
 *
 * - `$validationRules`: the rule itself (string, object, or Closure
 *   that returns a rule).
 * - `$validationMessages`: optional custom message per rule.
 * - `$validationAttributes`: per-field attribute name override
 *   (single value, applied at the field level not per-rule).
 *
 * `getValidationRules()` resolves Closures and returns a flat list
 * the validator can consume. Rule objects (e.g. `new Password(8)`)
 * are preserved verbatim — the validator handles them natively.
 *
 * `unique()` stores the parameters and emits a `unique:table,column`
 * string today. CORE-006 will inject the current record at
 * serialise time so the rule becomes
 * `Rule::unique($table, $column)->ignore($record)`. Apps that need
 * the full Rule object today can pass it via `rule(Rule::unique(...))`.
 */
trait HasValidation
{
    /** @var array<string, string|object> */
    protected array $validationRules = [];

    /** @var array<string, string> */
    protected array $validationMessages = [];

    protected ?string $validationAttribute = null;

    public function required(bool|Closure $required = true): static
    {
        if ($required === false) {
            return $this->dropRule('required');
        }

        return $this->addRule('required', $required);
    }

    public function nullable(): static
    {
        return $this->addRule('nullable');
    }

    /**
     * @param array<int, string|object> $rules
     */
    public function rules(array $rules): static
    {
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }

        return $this;
    }

    public function rule(string|object $rule, ?string $message = null): static
    {
        $this->addRule($rule);

        if ($message !== null) {
            $this->validationMessages[$this->normalizeRuleKey($rule)] = $message;
        }

        return $this;
    }

    public function unique(?string $table = null, ?string $column = null, mixed $ignorable = null): static
    {
        $resolvedTable = $table ?? 'unknown';
        /** @var string $name */
        $name = $this->getName();
        $resolvedColumn = $column ?? $name;

        $ignorableValue = is_scalar($ignorable) ? (string) $ignorable : '';

        $rule = $ignorable !== null
            ? sprintf('unique:%s,%s,%s', $resolvedTable, $resolvedColumn, $ignorableValue)
            : sprintf('unique:%s,%s', $resolvedTable, $resolvedColumn);

        return $this->addRule($rule);
    }

    public function maxLength(int $max): static
    {
        return $this->addRule('max:'.$max);
    }

    public function minLength(int $min): static
    {
        return $this->addRule('min:'.$min);
    }

    public function requiredIf(string $otherField, mixed $value): static
    {
        $serialised = is_scalar($value) ? (string) $value : '';

        return $this->addRule('required_if:'.$otherField.','.$serialised);
    }

    /**
     * @param string|array<int, string> $otherFields
     */
    public function requiredWith(string|array $otherFields): static
    {
        $list = is_array($otherFields) ? implode(',', $otherFields) : $otherFields;

        return $this->addRule('required_with:'.$list);
    }

    /**
     * @param string|array<int, string> $otherFields
     */
    public function requiredWithout(string|array $otherFields): static
    {
        $list = is_array($otherFields) ? implode(',', $otherFields) : $otherFields;

        return $this->addRule('required_without:'.$list);
    }

    public function validationAttribute(string $attribute): static
    {
        $this->validationAttribute = $attribute;

        return $this;
    }

    public function validationMessage(string $rule, string $message): static
    {
        $this->validationMessages[$rule] = $message;

        return $this;
    }

    /**
     * @return list<string|object>
     */
    public function getValidationRules(): array
    {
        /** @var array<string, string|object> $resolved */
        $resolved = [];

        if (method_exists($this, 'getDefaultRules')) {
            /** @var array<int, string|object> $defaults */
            $defaults = $this->getDefaultRules();
            foreach ($defaults as $rule) {
                $resolved[$this->normalizeRuleKey($rule)] = $rule;
            }
        }

        foreach ($this->validationRules as $key => $rule) {
            $value = $rule instanceof Closure ? $rule() : $rule;
            if ($value instanceof Closure) {
                continue;
            }
            $resolved[$key] = $value;
        }

        /** @var list<string|object> $list */
        $list = array_values($resolved);

        return $list;
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    public function getValidationAttribute(): ?string
    {
        return $this->validationAttribute;
    }

    protected function addRule(string|object $rule, bool|Closure $conditional = true): static
    {
        if (is_bool($conditional) && ! $conditional) {
            return $this;
        }

        $this->validationRules[$this->normalizeRuleKey($rule)] = $rule;

        return $this;
    }

    protected function dropRule(string|object $rule): static
    {
        unset($this->validationRules[$this->normalizeRuleKey($rule)]);

        return $this;
    }

    private function normalizeRuleKey(string|object $rule): string
    {
        if (is_string($rule)) {
            $colon = strpos($rule, ':');

            return $colon === false ? $rule : substr($rule, 0, $colon);
        }

        if ($rule instanceof Closure) {
            return 'closure-'.spl_object_id($rule);
        }

        return $rule::class;
    }
}
