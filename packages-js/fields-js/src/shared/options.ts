/**
 * Normalises the polymorphic `options` shape on Select/Radio fields:
 * the server can emit either an array (`[{value, label}, …]`) or an
 * object (`{ value: 'Label' }`). Components only need the array form.
 */

export interface NormalisedOption {
  value: string | number;
  label: string;
}

export function normaliseOptions(options: unknown): NormalisedOption[] {
  if (!options) return [];
  if (Array.isArray(options)) {
    return options.filter(
      (o): o is NormalisedOption =>
        typeof o === 'object' && o !== null && 'value' in o && 'label' in o,
    );
  }
  if (typeof options === 'object') {
    return Object.entries(options as Record<string, string>).map(([value, label]) => ({
      value,
      label,
    }));
  }
  return [];
}
