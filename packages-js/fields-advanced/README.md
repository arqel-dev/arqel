# @arqel/fields-advanced

Advanced field components for [Arqel](https://arqel.dev) — ships rich React inputs (Tags, KeyValue, JSON, Markdown, RichText, CodeEditor, etc.) that plug into `@arqel/ui`'s `FieldRegistry`.

Components are exported as named exports; the host app decides which to register:

```ts
import { TagsInput } from '@arqel/fields-advanced/tags';
import { registerField } from '@arqel/ui/form';

registerField('TagsInput', TagsInput);
```

## License

MIT.
