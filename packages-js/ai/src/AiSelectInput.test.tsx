import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AiSelectInput, type AiSelectInputFieldProps } from './AiSelectInput.js';

const baseFieldProps: AiSelectInputFieldProps = {
  options: { low: 'Low', medium: 'Medium', high: 'High' },
  classifyFromFields: ['title', 'body'],
  provider: null,
  fallbackOption: null,
  hasContextFields: true,
};

function makeFetchOk(
  body: { key: string | null; label?: string | null },
  status = 200,
): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  }));
}

function makeFetchFail(status: number): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: false,
    status,
    json: async () => ({}),
  }));
}

describe('<AiSelectInput>', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders a select with placeholder + each option', () => {
    render(
      <AiSelectInput
        name="priority"
        value={null}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );
    const select = screen.getByRole('combobox') as HTMLSelectElement;
    expect(select.value).toBe('');
    expect(screen.getByRole('option', { name: 'Select...' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Low' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Medium' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'High' })).toBeInTheDocument();
  });

  it('invokes onChange when the user picks an option manually', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();
    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={onChange}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );
    await user.selectOptions(screen.getByRole('combobox'), 'medium');
    expect(onChange).toHaveBeenCalledWith('medium');
  });

  it('disables the button when hasContextFields is false', () => {
    render(
      <AiSelectInput
        name="priority"
        value={null}
        props={{ ...baseFieldProps, hasContextFields: false }}
        resource="tickets"
        field="priority"
      />,
    );
    const button = screen.getByRole('button', { name: 'Classify with AI' });
    expect(button).toBeDisabled();
    expect(button.getAttribute('title')).toContain('No context fields');
  });

  it('enables the button when hasContextFields is true', () => {
    render(
      <AiSelectInput
        name="priority"
        value={null}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );
    expect(screen.getByRole('button', { name: 'Classify with AI' })).toBeEnabled();
  });

  it('dispatches fetch with formData on click', async () => {
    const fetchMock = makeFetchOk({ key: 'high', label: 'High' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
        formData={{ title: 'Server is down', body: 'Production outage' }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [calledUrl, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/admin/tickets/fields/priority/classify');
    expect(init.method).toBe('POST');
    const parsed = JSON.parse(init.body as string) as { formData: Record<string, unknown> };
    expect(parsed.formData).toEqual({ title: 'Server is down', body: 'Production outage' });
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
      <AiSelectInput
        name="priority"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );

    const button = screen.getByRole('button', { name: 'Classify with AI' });
    fireEvent.click(button);

    await waitFor(() => {
      expect(button).toBeDisabled();
    });
    expect(screen.getByRole('status', { name: 'Classifying' })).toBeInTheDocument();

    resolveFn?.({ ok: true, status: 200, json: async () => ({ key: 'low', label: 'Low' }) });
    await waitFor(() => {
      expect(button).toBeEnabled();
    });
  });

  it('on success applies the new value and shows "Suggested by AI"', async () => {
    const fetchMock = makeFetchOk({ key: 'high', label: 'High' });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={onChange}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('high');
    });
    expect(await screen.findByText('Suggested by AI')).toBeInTheDocument();
  });

  it('on http failure renders an error banner', async () => {
    const fetchMock = makeFetchFail(500);
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));
    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('500');
  });

  it('renders "Could not classify" when key is null and no fallback is configured', async () => {
    const fetchMock = makeFetchOk({ key: null, label: null });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={onChange}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));
    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('Could not classify');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('uses fallbackOption when key is null and fallback is configured', async () => {
    const fetchMock = makeFetchOk({ key: null, label: null });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={onChange}
        props={{ ...baseFieldProps, fallbackOption: 'low' }}
        resource="tickets"
        field="priority"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('low');
    });
    expect(await screen.findByText('Used fallback')).toBeInTheDocument();
  });

  it('forwards the csrf token in the X-CSRF-TOKEN header', async () => {
    const fetchMock = makeFetchOk({ key: 'low', label: 'Low' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
        csrfToken="csrf-xyz-789"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = init.headers as Record<string, string>;
    expect(headers['X-CSRF-TOKEN']).toBe('csrf-xyz-789');
    expect(headers['Content-Type']).toBe('application/json');
  });

  it('prefers `classifyUrl` override over the resource/field default URL', async () => {
    const fetchMock = makeFetchOk({ key: 'low', label: 'Low' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiSelectInput
        name="priority"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="tickets"
        field="priority"
        classifyUrl="/custom/classify"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Classify with AI' }));
    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [calledUrl] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/custom/classify');
  });
});
