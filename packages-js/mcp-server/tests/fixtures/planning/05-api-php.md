# 05 — PHP API (fixture)

> Fixture used by parser and `get_api_reference` tool tests.

## 1. Resource

Top-level Resource section.

### 1.1 Base class

Generic subheading — body should be folded into parent.

```php
class UserResource extends Resource {}
```

### 1.2 Resource discovery

Concrete sub-symbol (capitalised first letter, not in generic list).

Discovery happens via the `Panel::resources()` registration.

## 2. Panel

Panel is a top-level concrete symbol with no `###` children.

```php
Panel::make('admin')->resources([UserResource::class]);
```

## 3. Examples

Top-level whose name is in the generic set — still emitted because we
only fold `###` headings, never `##`.
