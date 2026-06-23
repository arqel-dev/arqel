import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

// Mock the translation hook so we can assert keys are routed through `t`
// (with the English literal as fallback) instead of being hardcoded.
const seenKeys: string[] = [];
vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations:
    () =>
    (key: string, fallback?: string): string => {
      seenKeys.push(key);
      // Echo a recognizable translated value so we can assert the component
      // renders whatever `t` returns rather than its own literal.
      return `T(${fallback ?? key})`;
    },
}));

import { VersionDiff } from '../src/VersionDiff.js';

describe('VersionDiff i18n', () => {
  it('routes the empty state and aria-label through t()', () => {
    render(<VersionDiff before={{}} after={{}} />);

    expect(seenKeys).toContain('arqel.versioning.no_changes');
    expect(seenKeys).toContain('arqel.versioning.field_comparison');

    const empty = screen.getByTestId('version-diff-empty');
    expect(empty).toHaveAttribute('aria-label', 'T(Field comparison)');
    expect(screen.getByText('T(No changes to display.)')).toBeInTheDocument();
  });

  it('localizes the populated card aria-label', () => {
    render(<VersionDiff before={{ name: 'a' }} after={{ name: 'b' }} />);
    const card = screen.getByTestId('version-diff');
    expect(card).toHaveAttribute('aria-label', 'T(Field comparison)');
  });

  it('localizes the value-cell placeholder aria-label on added/removed', () => {
    render(<VersionDiff before={{ kept: 'x' }} after={{ kept: 'x', extra: 'new' }} />);
    // The "before" cell for an added field has no previous value.
    const note = screen.getByLabelText('T(no previous value)');
    expect(note).toBeInTheDocument();
    expect(seenKeys).toContain('arqel.versioning.no_previous_value');
  });

  it('localizes the Modified badge for long block diffs', () => {
    // Different line counts force the block-level (badge) render path rather
    // than the line-by-line diff.
    const long = 'x'.repeat(150);
    const longer = `${'y'.repeat(150)}\nextra line`;
    render(<VersionDiff before={{ body: long }} after={{ body: longer }} />);
    expect(seenKeys).toContain('arqel.versioning.modified');
    expect(screen.getAllByText('T(Modified)').length).toBeGreaterThan(0);
  });
});
