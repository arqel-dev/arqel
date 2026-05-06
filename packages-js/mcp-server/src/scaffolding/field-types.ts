/**
 * Canonical Arqel field-type table.
 *
 * Source of truth: `packages/fields/src/Types/`. If a new Field class
 * lands there, add a row here and update `SUPPORTED_FIELD_TYPES`.
 */

export interface FieldTypeSpec {
  /** Canonical PascalCase name as exposed in PHP (e.g. `Text`, `BelongsTo`). */
  readonly name: string;
  /** PHP class basename (e.g. `TextField`). */
  readonly className: string;
  /** Fully-qualified PHP class name. */
  readonly fqcn: string;
}

export const FIELD_TYPES: readonly FieldTypeSpec[] = [
  { name: 'Text', className: 'TextField', fqcn: 'Arqel\\Fields\\Types\\TextField' },
  { name: 'Textarea', className: 'TextareaField', fqcn: 'Arqel\\Fields\\Types\\TextareaField' },
  { name: 'Number', className: 'NumberField', fqcn: 'Arqel\\Fields\\Types\\NumberField' },
  { name: 'Currency', className: 'CurrencyField', fqcn: 'Arqel\\Fields\\Types\\CurrencyField' },
  { name: 'Boolean', className: 'BooleanField', fqcn: 'Arqel\\Fields\\Types\\BooleanField' },
  { name: 'Toggle', className: 'ToggleField', fqcn: 'Arqel\\Fields\\Types\\ToggleField' },
  { name: 'Select', className: 'SelectField', fqcn: 'Arqel\\Fields\\Types\\SelectField' },
  {
    name: 'MultiSelect',
    className: 'MultiSelectField',
    fqcn: 'Arqel\\Fields\\Types\\MultiSelectField',
  },
  { name: 'Radio', className: 'RadioField', fqcn: 'Arqel\\Fields\\Types\\RadioField' },
  { name: 'Email', className: 'EmailField', fqcn: 'Arqel\\Fields\\Types\\EmailField' },
  { name: 'URL', className: 'UrlField', fqcn: 'Arqel\\Fields\\Types\\UrlField' },
  { name: 'Password', className: 'PasswordField', fqcn: 'Arqel\\Fields\\Types\\PasswordField' },
  { name: 'Slug', className: 'SlugField', fqcn: 'Arqel\\Fields\\Types\\SlugField' },
  { name: 'Date', className: 'DateField', fqcn: 'Arqel\\Fields\\Types\\DateField' },
  { name: 'DateTime', className: 'DateTimeField', fqcn: 'Arqel\\Fields\\Types\\DateTimeField' },
  { name: 'BelongsTo', className: 'BelongsToField', fqcn: 'Arqel\\Fields\\Types\\BelongsToField' },
  { name: 'HasMany', className: 'HasManyField', fqcn: 'Arqel\\Fields\\Types\\HasManyField' },
  { name: 'File', className: 'FileField', fqcn: 'Arqel\\Fields\\Types\\FileField' },
  { name: 'Image', className: 'ImageField', fqcn: 'Arqel\\Fields\\Types\\ImageField' },
  { name: 'Color', className: 'ColorField', fqcn: 'Arqel\\Fields\\Types\\ColorField' },
  { name: 'Hidden', className: 'HiddenField', fqcn: 'Arqel\\Fields\\Types\\HiddenField' },
];

export const SUPPORTED_FIELD_TYPE_NAMES: readonly string[] = FIELD_TYPES.map((t) => t.name);

const NORMALIZED_LOOKUP = new Map<string, FieldTypeSpec>();
for (const spec of FIELD_TYPES) {
  NORMALIZED_LOOKUP.set(spec.name.toLowerCase(), spec);
  // Also accept SCREAMING_SNAKE / snake_case forms.
  NORMALIZED_LOOKUP.set(spec.name.toLowerCase().replace(/[_-]/g, ''), spec);
}
// Manual aliases for forms that don't survive the simple lower-case dance.
const ALIASES: Record<string, string> = {
  multi_select: 'MultiSelect',
  multiselect: 'MultiSelect',
  belongs_to: 'BelongsTo',
  belongsto: 'BelongsTo',
  has_many: 'HasMany',
  hasmany: 'HasMany',
  date_time: 'DateTime',
  datetime: 'DateTime',
  url: 'URL',
};
for (const [alias, target] of Object.entries(ALIASES)) {
  const spec = FIELD_TYPES.find((t) => t.name === target);
  if (spec) NORMALIZED_LOOKUP.set(alias, spec);
}

/**
 * Resolve a user-supplied type string (case-insensitive) to a canonical spec.
 * Returns `null` if not found.
 */
export function resolveFieldType(input: string): FieldTypeSpec | null {
  const key = input.trim().toLowerCase().replace(/[_-]/g, '');
  return NORMALIZED_LOOKUP.get(key) ?? null;
}
