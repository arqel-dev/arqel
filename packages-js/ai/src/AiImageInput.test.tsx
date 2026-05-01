import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { AiImageInput, type AiImageInputFieldProps } from './AiImageInput.js';

const baseFieldProps: AiImageInputFieldProps = {
  analyses: ['alt_text', 'tags'],
  populateFields: { alt_text: 'cover_alt', tags: 'cover_tags' },
  provider: null,
  acceptedMimes: ['image/jpeg', 'image/png', 'image/webp'],
  maxFileSize: 1024 * 1024, // 1 MB para os testes
  buttonLabel: 'Analyze with AI',
};

function makeFile(name: string, size: number, type = 'image/png'): File {
  const file = new File([new Uint8Array(0)], name, { type });
  // Simula tamanho sem alocar bytes reais.
  Object.defineProperty(file, 'size', { value: size, configurable: true });
  return file;
}

function getFileInput(): HTMLInputElement {
  const input = screen.getByLabelText(/Image file/i);
  return input as HTMLInputElement;
}

function makeFetchOk(
  analyses: Record<string, string>,
  populateMapping: Record<string, string> | null = null,
  status = 200,
): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => ({
      analyses,
      ...(populateMapping !== null ? { populateMapping } : {}),
    }),
  }));
}

function makeFetchFailWithMessage(status: number, message: string): ReturnType<typeof vi.fn> {
  return vi.fn(async () => ({
    ok: false,
    status,
    json: async () => ({ message }),
  }));
}

interface FakeFileReaderInstance {
  result: string | null;
  onload: ((this: FakeFileReaderInstance) => void) | null;
  onerror: ((this: FakeFileReaderInstance) => void) | null;
  readAsDataURL(this: FakeFileReaderInstance, _file: File): void;
  error: unknown;
}

function stubFileReader(dataUrl: string): void {
  function FakeFileReader(this: FakeFileReaderInstance): void {
    this.result = null;
    this.onload = null;
    this.onerror = null;
    this.error = null;
    this.readAsDataURL = function (this: FakeFileReaderInstance) {
      this.result = dataUrl;
      // Dispara síncrono — suficiente para os asserts via waitFor.
      queueMicrotask(() => {
        this.onload?.call(this);
      });
    };
  }
  vi.stubGlobal('FileReader', FakeFileReader);
}

function stubCreateObjectUrl(): void {
  vi.stubGlobal(
    'URL',
    Object.assign(URL, {
      createObjectURL: vi.fn(() => 'blob:fake-preview'),
      revokeObjectURL: vi.fn(),
    }),
  );
}

describe('<AiImageInput>', () => {
  beforeEach(() => {
    stubCreateObjectUrl();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders a file input with the correct accept attribute', () => {
    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    const input = getFileInput();
    expect(input.accept).toBe('image/jpeg,image/png,image/webp');
  });

  it('rejects files larger than maxFileSize and keeps the analyze button disabled', async () => {
    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    const input = getFileInput();
    const big = makeFile('big.png', baseFieldProps.maxFileSize + 1);
    fireEvent.change(input, { target: { files: [big] } });

    await waitFor(() => {
      expect(screen.getByRole('alert').textContent).toMatch(/File too large/i);
    });
    expect(screen.getByRole('button', { name: /Analyze with AI/i })).toBeDisabled();
  });

  it('enables the analyze button when a valid file is selected', async () => {
    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    const input = getFileInput();
    const small = makeFile('small.png', 1024);
    fireEvent.change(input, { target: { files: [small] } });

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Analyze with AI/i })).not.toBeDisabled();
    });
    expect(screen.getByTestId('image-preview')).toBeInTheDocument();
  });

  it('dispatches fetch with imageBase64 in the body on click', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'A cat.', tags: 'cat,cute' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const [calledUrl, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/admin/posts/fields/cover/analyze-image');
    expect(init.method).toBe('POST');
    const body = JSON.parse(init.body as string) as { imageBase64: string };
    expect(body.imageBase64).toBe('data:image/png;base64,AAAA');
  });

  it('disables the button while loading', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    let resolveFn: ((v: unknown) => void) | undefined;
    const pending = new Promise((resolve) => {
      resolveFn = resolve;
    });
    const fetchMock = vi.fn(() => pending);
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    const button = screen.getByRole('button', { name: /Analyze with AI/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(button).toBeDisabled();
    });
    expect(screen.getByRole('status', { name: /Analyzing/i })).toBeInTheDocument();

    resolveFn?.({
      ok: true,
      status: 200,
      json: async () => ({ analyses: {} }),
    });
    await waitFor(() => {
      expect(button).not.toBeDisabled();
    });
  });

  it('renders the <dl> preview with each analysis after success', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'A red car.', tags: 'car,red,vehicle' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(screen.getByTestId('analysis-value-alt_text').textContent).toBe('A red car.');
    });
    expect(screen.getByTestId('analysis-value-tags').textContent).toBe('car,red,vehicle');
    expect(screen.getByRole('button', { name: /^Apply all$/i })).toBeInTheDocument();
  });

  it('Apply individual calls onPopulateField with target from populateMapping', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'A red car.', tags: 'car,red' });
    vi.stubGlobal('fetch', fetchMock);
    const onPopulate = vi.fn<(target: string, value: string) => void>();

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
        onPopulateField={onPopulate}
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Apply alt_text/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /Apply alt_text/i }));

    expect(onPopulate).toHaveBeenCalledTimes(1);
    expect(onPopulate).toHaveBeenCalledWith('cover_alt', 'A red car.');
  });

  it('Apply all iterates over every analysis with mapping', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'A red car.', tags: 'car,red' });
    vi.stubGlobal('fetch', fetchMock);
    const onPopulate = vi.fn<(target: string, value: string) => void>();

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
        onPopulateField={onPopulate}
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /^Apply all$/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /^Apply all$/i }));

    expect(onPopulate).toHaveBeenCalledTimes(2);
    expect(onPopulate).toHaveBeenNthCalledWith(1, 'cover_alt', 'A red car.');
    expect(onPopulate).toHaveBeenNthCalledWith(2, 'cover_tags', 'car,red');
  });

  it('renders error banner with message from 422 response', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchFailWithMessage(422, 'Image moderation rejected the upload.');
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('Image moderation rejected the upload.');
  });

  it('forwards csrf token in X-CSRF-TOKEN header', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'X' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
        csrfToken="csrf-image-xyz"
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    const headers = init.headers as Record<string, string>;
    expect(headers['X-CSRF-TOKEN']).toBe('csrf-image-xyz');
    expect(headers['Content-Type']).toBe('application/json');
  });

  it('prefers analyzeUrl override over the resource/field default URL', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'X' });
    vi.stubGlobal('fetch', fetchMock);

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
        analyzeUrl="/custom/vision-route"
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    const [calledUrl] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(calledUrl).toBe('/custom/vision-route');
  });

  it('uses populateMapping from response over the static prop mapping', async () => {
    stubFileReader('data:image/png;base64,AAAA');
    const fetchMock = makeFetchOk({ alt_text: 'A car.' }, { alt_text: 'override_alt' });
    vi.stubGlobal('fetch', fetchMock);
    const onPopulate = vi.fn<(target: string, value: string) => void>();

    render(
      <AiImageInput
        name="cover"
        value={null}
        props={baseFieldProps}
        resource="posts"
        field="cover"
        onPopulateField={onPopulate}
      />,
    );

    fireEvent.change(getFileInput(), { target: { files: [makeFile('a.png', 1024)] } });
    fireEvent.click(screen.getByRole('button', { name: /Analyze with AI/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Apply alt_text/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /Apply alt_text/i }));

    expect(onPopulate).toHaveBeenCalledWith('override_alt', 'A car.');
  });
});
