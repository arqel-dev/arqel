import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AiTextInput, type AiTextInputFieldProps } from './AiTextInput.js';

const baseFieldProps: AiTextInputFieldProps = {
  provider: null,
  buttonLabel: 'Generate with AI',
  maxLength: null,
  hasContextFields: false,
};

function makeFetchOk(text: string, status = 200): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => ({ text }),
  }));
}

function makeFetchFail(status: number): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: false,
    status,
    json: async () => ({}),
  }));
}

describe('<AiTextInput>', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders a textarea with the initial value', () => {
    render(
      <AiTextInput name="bio" value="hello" props={baseFieldProps} resource="users" field="bio" />,
    );
    const textarea = screen.getByRole('textbox') as HTMLTextAreaElement;
    expect(textarea.value).toBe('hello');
  });

  it('invokes onChange when the user types in the textarea', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();
    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={onChange}
        props={baseFieldProps}
        resource="users"
        field="bio"
      />,
    );
    const textarea = screen.getByRole('textbox');
    await user.type(textarea, 'ab');
    expect(onChange).toHaveBeenCalled();
    // Last call argument should be the latest emitted character ("b" with empty value props).
    const lastCall = onChange.mock.calls.at(-1);
    expect(lastCall?.[0]).toBe('b');
  });

  it('renders the configured button label and dispatches fetch on click', async () => {
    const fetchMock = makeFetchOk('generated text');
    vi.stubGlobal('fetch', fetchMock);

    const onChange = vi.fn();
    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={onChange}
        props={{ ...baseFieldProps, buttonLabel: 'Make magic' }}
        resource="users"
        field="bio"
      />,
    );

    const button = screen.getByRole('button', { name: 'Make magic' });
    expect(button).toBeEnabled();
    fireEvent.click(button);

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [calledUrl, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/admin/users/fields/bio/generate');
    expect(init.method).toBe('POST');
  });

  it('disables the button while loading', async () => {
    let resolveFn:
      | ((v: { ok: boolean; status: number; json: () => Promise<unknown> }) => void)
      | undefined;
    const pending = new Promise<{ ok: boolean; status: number; json: () => Promise<unknown> }>(
      (res) => {
        resolveFn = res;
      },
    );
    const fetchMock = vi.fn(() => pending);
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="users"
        field="bio"
      />,
    );

    const button = screen.getByRole('button', { name: 'Generate with AI' });
    fireEvent.click(button);

    await waitFor(() => {
      expect(button).toBeDisabled();
    });
    expect(screen.getByRole('status')).toBeInTheDocument();

    resolveFn?.({ ok: true, status: 200, json: async () => ({ text: 'done' }) });
    await waitFor(() => {
      expect(button).toBeEnabled();
    });
  });

  it('injects the generated text via onChange on success', async () => {
    const fetchMock = makeFetchOk('generated body');
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();

    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={onChange}
        props={baseFieldProps}
        resource="users"
        field="bio"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Generate with AI' }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('generated body');
    });
  });

  it('renders an error banner and re-enables the button on failure', async () => {
    const fetchMock = makeFetchFail(500);
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="users"
        field="bio"
      />,
    );

    const button = screen.getByRole('button', { name: 'Generate with AI' });
    fireEvent.click(button);

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('500');
    expect(button).toBeEnabled();
  });

  it('renders a char counter when maxLength is provided', () => {
    render(
      <AiTextInput
        name="bio"
        value="abc"
        props={{ ...baseFieldProps, maxLength: 100 }}
        resource="users"
        field="bio"
      />,
    );
    expect(screen.getByText('3 / 100')).toBeInTheDocument();
  });

  it('changes the button label to "Regenerate" after a successful generation', async () => {
    const fetchMock = makeFetchOk('first output');
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();

    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={onChange}
        props={baseFieldProps}
        resource="users"
        field="bio"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Generate with AI' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Regenerate' })).toBeInTheDocument();
    });
  });

  it('forwards the csrf token in the X-CSRF-TOKEN header', async () => {
    const fetchMock = makeFetchOk('ok');
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="users"
        field="bio"
        csrfToken="csrf-abc-123"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Generate with AI' }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = init.headers as Record<string, string>;
    expect(headers['X-CSRF-TOKEN']).toBe('csrf-abc-123');
    expect(headers['Content-Type']).toBe('application/json');
  });

  it('prefers `generateUrl` override over the resource/field default URL', async () => {
    const fetchMock = makeFetchOk('ok');
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiTextInput
        name="bio"
        value=""
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="users"
        field="bio"
        generateUrl="/custom/route"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Generate with AI' }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [calledUrl] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/custom/route');
  });
});
