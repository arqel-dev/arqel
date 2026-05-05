/**
 * Shadcn (new-york) primitives re-exported as the public surface of
 * `@arqel-dev/ui/primitives`.
 *
 * The actual component source lives in `src/shadcn/ui/*` (puxado via
 * `pnpm dlx shadcn@latest add ...`). Apps consume them via this barrel
 * or the top-level `@arqel-dev/ui` barrel — never reach into
 * `src/shadcn/ui/*` directly to keep the indirection.
 *
 * Conventions:
 * - All components use Radix UI primitives (via `radix-ui` package) and
 *   shadcn CSS vars (`--primary`, `--border`, etc.) declared in
 *   `styles/globals.css`.
 * - The compound `<Select>` here is the Radix Select with Trigger /
 *   Content / Item etc. — _not_ a `<select>` element. Consumers that
 *   only need a native `<select>` should use `<select>` directly.
 */

export { Alert, AlertDescription, AlertTitle } from '../shadcn/ui/alert.js';
export { Badge, badgeVariants } from '../shadcn/ui/badge.js';
export {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '../shadcn/ui/card.js';
export { Checkbox } from '../shadcn/ui/checkbox.js';
export {
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSeparator,
  FieldSet,
  FieldTitle,
} from '../shadcn/ui/field.js';
export { Input } from '../shadcn/ui/input.js';
export { Label } from '../shadcn/ui/label.js';
export {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
} from '../shadcn/ui/select.js';
export { Separator } from '../shadcn/ui/separator.js';
export { Skeleton } from '../shadcn/ui/skeleton.js';
export { Textarea } from '../shadcn/ui/textarea.js';
