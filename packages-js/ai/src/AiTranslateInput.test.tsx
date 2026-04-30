import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { type ReactElement, useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  AiTranslateInput,
  type AiTranslateInputFieldProps,
  type AiTranslateValue,
} from './AiTranslateInput.js';

interface ControlledHostProps {
  initialValue: AiTranslateValue;
  onChangeSpy?: (v: AiTranslateValue) => void;
  fieldProps: AiTranslateInputFieldProps;
  csrfToken?: string;
  translateUrl?: string;
}

function ControlledHost(props: ControlledHostProps): ReactElement {
  const [value, setValue] = useState<AiTranslateValue>(props.initialValue);
  return (
    <AiTranslateInput
      name="title"
      value={value}
      onChange={(v) => {
        setValue(v);
        props.onChangeSpy?.(v);
      }}
      props={props.fieldProps}
      resource="posts"
      field="title"
      {...(props.csrfToken !== undefined ? { csrfToken: props.csrfToken } : {})}
      {...(props.translateUrl !== undefined ? { translateUrl: props.translateUrl } : {})}
    />
  );
}

const baseFieldProps: AiTranslateInputFieldProps = {
  languages: ['en', 'pt', 'es'],
  defaultLanguage: 'en',
  autoTranslate: false,
  provider: null,
};

function makeFetchOk(translations: Record<string, string>, status = 200): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => ({ translations }),
  }));
}

function makeFetchFail(status: number): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: false,
    status,
    json: async () => ({}),
  }));
}

describe('<AiTranslateInput>', () => {
  beforeEach(() => {
    vi.unstubAllGlobals();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders one tab per configured language', () => {
    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello' }}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );
    const tabs = screen.getAllByRole('tab');
    expect(tabs).toHaveLength(3);
    expect(tabs[0]?.textContent).toContain('en');
    expect(tabs[1]?.textContent).toContain('pt');
    expect(tabs[2]?.textContent).toContain('es');
  });

  it('switches the active tab when the user clicks another language', async () => {
    const user = userEvent.setup();
    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello', pt: 'Olá' }}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    expect(ptTab).toBeDefined();
    await user.click(ptTab as HTMLElement);

    expect(ptTab?.getAttribute('aria-selected')).toBe('true');
    const textarea = screen.getByRole('textbox') as HTMLTextAreaElement;
    expect(textarea.value).toBe('Olá');
  });

  it('edits translations[activeLang] and emits the full object via onChange', async () => {
    const onChange = vi.fn<(v: AiTranslateValue) => void>();
    const user = userEvent.setup();
    render(
      <AiTranslateInput
        name="title"
        value={{ en: '' }}
        onChange={onChange}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    const textarea = screen.getByRole('textbox');
    await user.type(textarea, 'Hi');

    expect(onChange).toHaveBeenCalled();
    const lastArg = onChange.mock.calls.at(-1)?.[0];
    expect(lastArg).toBeDefined();
    // last typed char ends up at en
    expect((lastArg as AiTranslateValue)['en']).toBe('i');
  });

  it('shows "Translate from {default}" only on non-default tabs', async () => {
    const user = userEvent.setup();
    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello' }}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    // default (en) tab — no translate-one button
    expect(screen.queryByRole('button', { name: /Translate from en/i })).toBeNull();

    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    await user.click(ptTab as HTMLElement);

    expect(screen.getByRole('button', { name: /Translate from en/i })).toBeInTheDocument();
  });

  it('dispatches fetch with targetLanguages: [activeLang] and sourceText from default', async () => {
    const fetchMock = makeFetchOk({ pt: 'Olá' });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn();
    const user = userEvent.setup();

    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello' }}
        onChange={onChange}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    await user.click(ptTab as HTMLElement);

    fireEvent.click(screen.getByRole('button', { name: /Translate from en/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [calledUrl, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/admin/posts/fields/title/translate');
    expect(init.method).toBe('POST');
    const body = JSON.parse(init.body as string) as {
      sourceLanguage: string;
      targetLanguages: string[];
      sourceText: string;
    };
    expect(body.sourceLanguage).toBe('en');
    expect(body.targetLanguages).toEqual(['pt']);
    expect(body.sourceText).toBe('Hello');
  });

  it('merges response translations into state and calls onChange with full object', async () => {
    const fetchMock = makeFetchOk({ pt: 'Olá', es: 'Hola' });
    vi.stubGlobal('fetch', fetchMock);
    const onChange = vi.fn<(v: AiTranslateValue) => void>();
    const user = userEvent.setup();

    render(
      <ControlledHost
        initialValue={{ en: 'Hello' }}
        onChangeSpy={onChange}
        fieldProps={baseFieldProps}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Translate all missing/i }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalled();
    });
    const lastArg = onChange.mock.calls.at(-1)?.[0] as AiTranslateValue;
    expect(lastArg['en']).toBe('Hello');
    expect(lastArg['pt']).toBe('Olá');
    expect(lastArg['es']).toBe('Hola');
    // also ensure the targets in body were the missing ones
    const init = fetchMock.mock.calls[0]?.[1] as RequestInit;
    const body = JSON.parse(init.body as string) as { targetLanguages: string[] };
    expect(body.targetLanguages.sort()).toEqual(['es', 'pt']);

    // and the user can still navigate tabs after success
    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    await user.click(ptTab as HTMLElement);
    await waitFor(() => {
      expect((screen.getByRole('textbox') as HTMLTextAreaElement).value).toBe('Olá');
    });
  });

  it('renders an error banner on failure and keeps tabs usable', async () => {
    const fetchMock = makeFetchFail(500);
    vi.stubGlobal('fetch', fetchMock);
    const user = userEvent.setup();

    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello' }}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    await user.click(ptTab as HTMLElement);

    fireEvent.click(screen.getByRole('button', { name: /Translate from en/i }));

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('500');

    // tabs still usable after failure
    const esTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('es'));
    await user.click(esTab as HTMLElement);
    expect(esTab?.getAttribute('aria-selected')).toBe('true');
  });

  it('translate-all-missing only requests the empty languages', async () => {
    const fetchMock = makeFetchOk({ es: 'Hola' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello', pt: 'Olá' }}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: /Translate all missing/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const init = fetchMock.mock.calls[0]?.[1] as RequestInit;
    const body = JSON.parse(init.body as string) as { targetLanguages: string[] };
    expect(body.targetLanguages).toEqual(['es']);
  });

  it('renders a missing-translation indicator on tabs with empty/null value', () => {
    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello', pt: '' }}
        props={baseFieldProps}
        resource="posts"
        field="title"
      />,
    );

    const tabs = screen.getAllByRole('tab');
    const enTab = tabs.find((t) => t.textContent?.includes('en'));
    const ptTab = tabs.find((t) => t.textContent?.includes('pt'));
    const esTab = tabs.find((t) => t.textContent?.includes('es'));

    expect(enTab?.getAttribute('data-missing')).toBe('false');
    expect(ptTab?.getAttribute('data-missing')).toBe('true');
    expect(esTab?.getAttribute('data-missing')).toBe('true');

    // visual dots present on missing tabs only
    expect(within(ptTab as HTMLElement).getByLabelText('Missing translation')).toBeInTheDocument();
    expect(within(esTab as HTMLElement).getByLabelText('Missing translation')).toBeInTheDocument();
    expect(within(enTab as HTMLElement).queryByLabelText('Missing translation')).toBeNull();
  });

  it('forwards the csrf token in the X-CSRF-TOKEN header', async () => {
    const fetchMock = makeFetchOk({ pt: 'Olá' });
    vi.stubGlobal('fetch', fetchMock);
    const user = userEvent.setup();

    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello' }}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="posts"
        field="title"
        csrfToken="csrf-translate-xyz"
      />,
    );

    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    await user.click(ptTab as HTMLElement);
    fireEvent.click(screen.getByRole('button', { name: /Translate from en/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = init.headers as Record<string, string>;
    expect(headers['X-CSRF-TOKEN']).toBe('csrf-translate-xyz');
    expect(headers['Content-Type']).toBe('application/json');
  });

  it('prefers `translateUrl` override over the resource/field default URL', async () => {
    const fetchMock = makeFetchOk({ pt: 'Olá' });
    vi.stubGlobal('fetch', fetchMock);
    const user = userEvent.setup();

    render(
      <AiTranslateInput
        name="title"
        value={{ en: 'Hello' }}
        onChange={vi.fn()}
        props={baseFieldProps}
        resource="posts"
        field="title"
        translateUrl="/custom/translate-route"
      />,
    );

    const ptTab = screen.getAllByRole('tab').find((b) => b.textContent?.includes('pt'));
    await user.click(ptTab as HTMLElement);
    fireEvent.click(screen.getByRole('button', { name: /Translate from en/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });
    const [calledUrl] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/custom/translate-route');
  });
});
