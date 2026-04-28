import { expectTypeOf } from 'expect-type';
import { describe, it } from 'vitest';
import type { ArqelPageProps, AuthPayload, FlashPayload, SharedProps } from '../src/inertia.js';

describe('SharedProps shape', () => {
  it('matches the canonical Arqel middleware payload', () => {
    expectTypeOf<ArqelPageProps>().toEqualTypeOf<SharedProps>();
    expectTypeOf<SharedProps['auth']>().toEqualTypeOf<AuthPayload>();
    expectTypeOf<SharedProps['flash']>().toEqualTypeOf<FlashPayload>();
    expectTypeOf<SharedProps['arqel']['version']>().toEqualTypeOf<string>();
  });

  it('auth.can is a string→bool map', () => {
    const can: AuthPayload['can'] = { viewAdminPanel: true };

    expectTypeOf(can['viewAdminPanel']).toEqualTypeOf<boolean | undefined>();
  });
});
