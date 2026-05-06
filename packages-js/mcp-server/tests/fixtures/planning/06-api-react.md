# 06 — React API (fixture)

> Fixture used by parser and `get_api_reference` tool tests.

## 1. FieldSchema

Top-level concrete symbol; the only `###` child below is generic.

### 1.1 Examples

Generic — folded into FieldSchema.

```ts
const schema: FieldSchema = { type: 'text', name: 'title' };
```

## 2. Hooks

### 2.1 useResource

Camel-case identifier — concrete, NOT folded (despite lowercase first
letter, the uppercase `R` triggers concrete classification).

```ts
const { data } = useResource('users');
```

### 2.2 useArqelForm

Another camel-case hook.

### 2.3 config

All-lowercase descriptor — generic, folded.
