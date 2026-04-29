/**
 * `<Button>` — primitive button used by ActionButton, FormActions, etc.
 *
 * Variants and sizes mirror the canonical Arqel ActionVariant + ActionColor
 * matrix so server-emitted ActionSchema can drive presentation directly.
 */

import { cva, type VariantProps } from 'class-variance-authority';
import { forwardRef } from 'react';
import { cn } from '../utils/cn.js';

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 rounded-[var(--radius-arqel-sm)] text-sm font-medium ' +
    'transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
    'disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default:
          'bg-[var(--color-arqel-primary)] text-[var(--color-arqel-primary-fg)] hover:opacity-90',
        outline:
          'border border-[var(--color-arqel-border)] bg-transparent text-[var(--color-arqel-fg)] hover:bg-[var(--color-arqel-muted)]',
        ghost: 'bg-transparent text-[var(--color-arqel-fg)] hover:bg-[var(--color-arqel-muted)]',
        destructive:
          'bg-[var(--color-arqel-destructive)] text-[var(--color-arqel-destructive-fg)] hover:opacity-90',
      },
      size: {
        sm: 'h-8 px-3',
        md: 'h-9 px-4',
        lg: 'h-10 px-6',
        icon: 'h-9 w-9',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'md',
    },
  },
);

export type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> &
  VariantProps<typeof buttonVariants>;

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, type = 'button', ...props }, ref) => (
    <button
      ref={ref}
      type={type}
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    />
  ),
);
Button.displayName = 'Button';

export { buttonVariants };
