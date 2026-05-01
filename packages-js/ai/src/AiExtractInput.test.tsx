import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { type ReactElement, useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AiExtractInput,
  type AiExtractInputFieldProps,
  type AiExtractValue,
} from './AiExtractInput.js';

interface ControlledHostProps {
  initialValue: AiExtractValue | null;
  onChangeSpy?: (v: AiExtractValue) => void;
  fieldProps: AiExtractInputFieldProps;
  formData?: Record<string, unknown>;
  csrfToken?: string;
  extractUrl?: string;
  onPopulateField?: (target: string, value: unknown) => void;
}

function ControlledHost(props: ControlledHostProps): ReactElement {
  const [value, setValue] = useState<AiExtractValue | null>(props.initialValue);
  return (
    <AiExtractInput
      name="contact"
      value={value}
      onChange={(v) => {
        setValue(v);
        props.onChangeSpy?.(v);
      }}
      props={props.fieldProps}
      resource="contacts"
      field="contact"
      {...(props.formData !== undefined ? { formData: props.formData } : {})}
      {...(props.csrfToken !== undefined ? { csrfToken: props.csrfToken } : {})}
      {...(props.extractUrl !== undefined ? { extractUrl: props.extractUrl } : {})}
      {...(props.onPopulateField !== undefined ? { onPopulateField: props.onPopulateField } : {})}
    />
  );
}

const baseFieldProps: AiExtractInputFieldProps = {
  sourceField: 'rawText',
  targetFields: ['name', 'email', 'phone'],
  buttonLabel: 'Extract with AI',
  usingJsonMode: true,
  provider: null,
};

function makeFetchOk(extracted: Record<string, unknown>, status = 200): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => ({ extracted }),
  }));
}

function makeFetchFailWithMessage(status: number, message: string): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: false,
    status,
    json: async () => ({ message }),
  }));
}

function makeFetchFailNoMessage(status: number): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: false,
    status,
    json: async () => ({}),
  }));
}

describe('<AiExtractInput>', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders source field label and the extract button', () => {
    render(
      <AiExtractInput
        name="contact"
        value={null}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
      />,
    );
    expect(screen.getByText(/Source: rawText/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Extract with AI/i })).toBeInTheDocument();
  });

  it('renders the empty state before any extraction', () => {
    render(
      <AiExtractInput
        name="contact"
        value={null}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
      />,
    );
    expect(screen.getByText(/No extraction yet — click button to start/i)).toBeInTheDocument();
  });

  it('dispatches fetch with sourceText body read from formData[sourceField]', async () => {
    const fetchMock = makeFetchOk({ name: 'Alice', email: 'a@b.com', phone: '123' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'Hi, I am Alice — a@b.com — 123' }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const [calledUrl, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/admin/contacts/fields/contact/extract');
    expect(init.method).toBe('POST');
    const body = JSON.parse(init.body as string) as { sourceText: string };
    expect(body.sourceText).toBe('Hi, I am Alice — a@b.com — 123');
  });

  it('disables the button while loading', async () => {
    let resolveFn: ((v: unknown) => void) | undefined;
    const pending = new Promise((resolve) => {
      resolveFn = resolve;
    });
    const fetchMock = vi.fn(() => pending);
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'x' }}
      />,
    );

    const button = screen.getByRole('button', { name: /Extract with AI/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(button).toBeDisabled();
    });
    expect(screen.getByRole('status', { name: /Extracting/i })).toBeInTheDocument();

    // resolve so test can finish cleanly
    resolveFn?.({
      ok: true,
      status: 200,
      json: async () => ({ extracted: {} }),
    });
    await waitFor(() => {
      expect(button).not.toBeDisabled();
    });
  });

  it('renders preview <dl> with targetFields and values after success', async () => {
    const fetchMock = makeFetchOk({ name: 'Alice', email: 'a@b.com', phone: '123' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <ControlledHost
        initialValue={null}
        fieldProps={baseFieldProps}
        formData={{ rawText: 'blob' }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    await waitFor(() => {
      expect(screen.getByTestId('extract-value-name').textContent).toBe('Alice');
    });
    expect(screen.getByTestId('extract-value-email').textContent).toBe('a@b.com');
    expect(screen.getByTestId('extract-value-phone').textContent).toBe('123');
    expect(screen.getByRole('button', { name: /^Apply all$/i })).toBeInTheDocument();
  });

  it('Apply all calls onChange with the full extracted object when onPopulateField is absent', async () => {
    const fetchMock = makeFetchOk({ name: 'Alice', email: 'a@b.com', phone: '123' });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn<(v: AiExtractValue) => void>();

    render(
      <ControlledHost
        initialValue={null}
        onChangeSpy={onChange}
        fieldProps={baseFieldProps}
        formData={{ rawText: 'blob' }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /^Apply all$/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /^Apply all$/i }));

    expect(onChange).toHaveBeenCalledWith({
      name: 'Alice',
      email: 'a@b.com',
      phone: '123',
    });
  });

  it('Apply individual calls onPopulateField for that single target', async () => {
    const fetchMock = makeFetchOk({ name: 'Alice', email: 'a@b.com', phone: '123' });
    vi.stubGlobal('fetch', fetchMock);
    const onPopulate = vi.fn<(target: string, value: unknown) => void>();

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'blob' }}
        onPopulateField={onPopulate}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Apply email/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /Apply email/i }));

    expect(onPopulate).toHaveBeenCalledTimes(1);
    expect(onPopulate).toHaveBeenCalledWith('email', 'a@b.com');
  });

  it('renders error banner with message from 422 response', async () => {
    const fetchMock = makeFetchFailWithMessage(422, 'AI provider rejected the prompt.');
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'blob' }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('AI provider rejected the prompt.');
  });

  it('renders generic error banner on 500 without message', async () => {
    const fetchMock = makeFetchFailNoMessage(500);
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'blob' }}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('500');
  });

  it('forwards csrf token in X-CSRF-TOKEN header', async () => {
    const fetchMock = makeFetchOk({ name: 'X' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'blob' }}
        csrfToken="csrf-extract-xyz"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = init.headers as Record<string, string>;
    expect(headers['X-CSRF-TOKEN']).toBe('csrf-extract-xyz');
    expect(headers['Content-Type']).toBe('application/json');
  });

  it('prefers `extractUrl` override over the resource/field default URL', async () => {
    const fetchMock = makeFetchOk({ name: 'X' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiExtractInput
        name="contact"
        value={null}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="contacts"
        field="contact"
        formData={{ rawText: 'blob' }}
        extractUrl="/custom/extract-route"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Extract with AI/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const [calledUrl] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/custom/extract-route');
  });
});
