/**
 * Side-effect import that registers every advanced field component
 * into `@arqel-dev/ui`'s FieldRegistry, lazily.
 *
 *   import '@arqel-dev/fields-advanced/register';
 *
 * Each `registerField()` call wraps a dynamic `import()` in
 * `React.lazy()` so the underlying chunk is only fetched when the
 * matching field actually renders. Bundlers (Vite/Rollup/webpack)
 * emit one chunk per dynamic import, giving on-demand loading without
 * any registry-level changes in `@arqel-dev/ui`.
 *
 * Component names match the value emitted by `Field::component()`
 * server-side (e.g. `'RichTextInput'`). Apps can re-register their
 * own components after this import to override defaults.
 */

import { registerField } from '@arqel-dev/ui/form';
import { lazy } from 'react';

registerField(
  'RichTextInput',
  lazy(() => import('./rich-text/RichTextInput.js').then((m) => ({ default: m.RichTextInput }))),
);
registerField(
  'MarkdownInput',
  lazy(() => import('./markdown/MarkdownInput.js').then((m) => ({ default: m.MarkdownInput }))),
);
registerField(
  'CodeInput',
  lazy(() => import('./code/CodeInput.js').then((m) => ({ default: m.CodeInput }))),
);
registerField(
  'RepeaterInput',
  lazy(() => import('./repeater/RepeaterInput.js').then((m) => ({ default: m.RepeaterInput }))),
);
registerField(
  'BuilderInput',
  lazy(() => import('./builder/BuilderInput.js').then((m) => ({ default: m.BuilderInput }))),
);
registerField(
  'KeyValueInput',
  lazy(() => import('./key-value/KeyValueInput.js').then((m) => ({ default: m.KeyValueInput }))),
);
registerField(
  'TagsInput',
  lazy(() => import('./tags/TagsInput.js').then((m) => ({ default: m.TagsInput }))),
);
registerField(
  'WizardInput',
  lazy(() => import('./wizard/WizardInput.js').then((m) => ({ default: m.WizardInput }))),
);
