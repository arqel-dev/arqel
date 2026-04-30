import { describe, expect, it } from 'vitest';
import type { CommandPaletteProps, PaletteCommand } from '../../src/palette/index.js';
import { CommandPalette } from '../../src/palette/index.js';

describe('@arqel/ui/palette — module surface', () => {
  it('exports CommandPalette as a function', () => {
    expect(typeof CommandPalette).toBe('function');
  });

  it('keeps the public type surface intact', () => {
    type _Surface = [CommandPaletteProps, PaletteCommand];
    const probe: ReadonlyArray<keyof { [K in keyof _Surface as `t${K & number}`]: _Surface[K] }> =
      [];
    expect(probe).toEqual([]);
  });
});
