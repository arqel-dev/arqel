import { expectTypeOf } from 'expect-type';
import { describe, it } from 'vitest';
import type {
  PaginationMeta,
  RecordType,
  ResourceCreateProps,
  ResourceEditProps,
  ResourceIndexProps,
  ResourceMeta,
} from '../src/resources.js';

describe('Resource Inertia payload shapes', () => {
  it('ResourceMeta carries the canonical fields', () => {
    expectTypeOf<ResourceMeta>().toMatchTypeOf<{
      class: string;
      slug: string;
      label: string;
      pluralLabel: string;
    }>();
  });

  it('ResourceIndexProps is generic over RecordType', () => {
    interface User extends RecordType {
      id: number;
      email: string;
    }

    expectTypeOf<ResourceIndexProps<User>['records']>().toEqualTypeOf<User[]>();
  });

  it('ResourceCreateProps has record: null', () => {
    expectTypeOf<ResourceCreateProps['record']>().toEqualTypeOf<null>();
  });

  it('ResourceEditProps carries recordTitle/recordSubtitle', () => {
    expectTypeOf<ResourceEditProps['recordTitle']>().toEqualTypeOf<string>();
    expectTypeOf<ResourceEditProps['recordSubtitle']>().toEqualTypeOf<string | null>();
  });

  it('PaginationMeta has currentPage/lastPage/perPage/total', () => {
    const meta: PaginationMeta = {
      currentPage: 1,
      lastPage: 10,
      perPage: 25,
      total: 247,
    };

    expectTypeOf(meta.currentPage).toEqualTypeOf<number>();
  });
});
