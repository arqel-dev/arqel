import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CollabRichTextField } from '../CollabRichTextField';

describe('CollabRichTextField', () => {
  beforeEach(() => {
    delete (window as unknown as { Echo?: unknown }).Echo;
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    cleanup();
  });

  it('renders a textarea', () => {
    render(<CollabRichTextField modelType="posts" modelId={1} field="body" placeholder="Type…" />);
    const textarea = screen.getByPlaceholderText('Type…');
    expect(textarea).toBeDefined();
    expect(textarea.tagName.toLowerCase()).toBe('textarea');
  });

  it('exposes data-collab-status attribute on the textarea', () => {
    render(<CollabRichTextField modelType="posts" modelId={1} field="body" placeholder="X" />);
    const textarea = screen.getByPlaceholderText('X') as HTMLTextAreaElement;
    expect(textarea.getAttribute('data-collab-status')).toBeDefined();
  });

  it('updates the textarea value when the user types', () => {
    render(<CollabRichTextField modelType="posts" modelId={1} field="body" placeholder="X" />);
    const textarea = screen.getByPlaceholderText('X') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'hello world' } });
    expect(textarea.value).toBe('hello world');
  });

  it('debounces persistUrl POST and only fires once after delay', async () => {
    const fetchMock = vi.fn(() =>
      Promise.resolve(new Response(JSON.stringify({ version: 1 }), { status: 200 })),
    );
    vi.stubGlobal('fetch', fetchMock);

    render(
      <CollabRichTextField
        modelType="posts"
        modelId={1}
        field="body"
        persistUrl="/admin/posts/1/collab/body"
        debounceMs={500}
        placeholder="X"
      />,
    );

    // initial GET hydration may fire a fetch — clear before typing
    fetchMock.mockClear();

    const textarea = screen.getByPlaceholderText('X') as HTMLTextAreaElement;
    fireEvent.change(textarea, { target: { value: 'a' } });
    fireEvent.change(textarea, { target: { value: 'ab' } });
    fireEvent.change(textarea, { target: { value: 'abc' } });

    expect(fetchMock).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(600);

    const postCalls = fetchMock.mock.calls.filter((c) => {
      const init = c[1] as RequestInit | undefined;
      return init?.method === 'POST';
    });
    expect(postCalls.length).toBe(1);
    expect(postCalls[0]?.[0]).toBe('/admin/posts/1/collab/body');
  });
});
