/**
 * Re-export shadcn (new-york) Button so existing consumers continue to
 * import `Button` and `buttonVariants` from `@arqel-dev/ui/action`
 * while the actual implementation lives in `src/shadcn/ui/button.tsx`.
 *
 * Variants exposed (shadcn): `default | destructive | outline |
 * secondary | ghost | link`. Sizes: `default | xs | sm | lg | icon |
 * icon-xs | icon-sm | icon-lg`.
 */

import type { ComponentProps } from 'react';
import type { Button } from '../shadcn/ui/button.js';

export { Button, buttonVariants } from '../shadcn/ui/button.js';

export type ButtonProps = ComponentProps<typeof Button>;
