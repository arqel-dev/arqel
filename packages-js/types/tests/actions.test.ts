import { expectTypeOf } from 'expect-type';
import { describe, expect, it } from 'vitest';
import type {
  ActionFormField,
  ActionSchema,
  ConfirmationConfig,
  ResourceActions,
} from '../src/actions.js';

describe('ActionSchema shape', () => {
  it('matches the PHP toArray() output (with null-keys filtered)', () => {
    const action: ActionSchema = {
      name: 'publish',
      type: 'row',
      label: 'Publish',
      color: 'success',
      variant: 'default',
      method: 'POST',
    };

    expectTypeOf(action.name).toEqualTypeOf<string>();
    expectTypeOf(action.type).toEqualTypeOf<'row' | 'bulk' | 'toolbar' | 'header'>();
    expect(action.method).toBe('POST');
  });

  it('confirmation config is independently typed', () => {
    const config: ConfirmationConfig = {
      heading: 'Delete?',
      color: 'destructive',
      requiresText: 'DELETE',
    };

    expectTypeOf(config.color).toEqualTypeOf<'destructive' | 'warning' | 'info' | undefined>();
  });

  it('form modal fields are a flat list', () => {
    const fields: ActionFormField[] = [{ name: 'reason', type: 'text' }];

    expectTypeOf(fields[0]).toMatchTypeOf<ActionFormField | undefined>();
  });
});

describe('ResourceActions', () => {
  it('groups row, bulk, toolbar collections', () => {
    expectTypeOf<ResourceActions>().toEqualTypeOf<{
      row: ActionSchema[];
      bulk: ActionSchema[];
      toolbar: ActionSchema[];
    }>();
  });
});
