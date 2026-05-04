import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface ForgotPasswordPageProps {
  /** URL de submit (default `/admin/forgot-password`). */
  forgotPasswordUrl?: string;
  /** URL para voltar ao login (default `/admin/login`). */
  loginUrl?: string;
  /** Texto do header (default "Recuperar senha"). */
  title?: string;
}

type ForgotPasswordFormData = {
  email: string;
};

interface PageProps {
  flash?: { status?: string };
  [key: string]: unknown;
}

/**
 * Página de forgot-password bundled de Arqel.
 *
 * Renderizada via Inertia em `arqel-dev/auth/ForgotPassword`. Mostra flash
 * `status` quando o backend confirma o envio do reset link.
 */
export function ForgotPasswordPage({
  forgotPasswordUrl = '/admin/forgot-password',
  loginUrl = '/admin/login',
  title = 'Recuperar senha',
}: ForgotPasswordPageProps): ReactElement {
  const { data, setData, post, processing, errors } = useForm<ForgotPasswordFormData>({
    email: '',
  });

  const page = usePage<PageProps>();
  const status = page.props.flash?.status;

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(forgotPasswordUrl);
  };

  return (
    <div className="arqel-forgot-password-page">
      <h1 className="arqel-forgot-password-title">{title}</h1>

      {status ? (
        <p className="arqel-forgot-password-status" role="status">
          {status}
        </p>
      ) : null}

      <form onSubmit={handleSubmit} className="arqel-forgot-password-form" noValidate>
        <div className="arqel-forgot-password-field">
          <label htmlFor="email">E-mail</label>
          <input
            id="email"
            type="email"
            name="email"
            value={data.email}
            autoComplete="username"
            required
            onChange={(event) => setData('email', event.target.value)}
          />
          {errors.email ? (
            <p className="arqel-forgot-password-error" role="alert">
              {errors.email}
            </p>
          ) : null}
        </div>

        <button type="submit" disabled={processing} className="arqel-forgot-password-submit">
          {processing ? 'Enviando…' : 'Enviar link de reset'}
        </button>

        <div className="arqel-forgot-password-links">
          <a href={loginUrl} className="arqel-forgot-password-link">
            Voltar ao login
          </a>
        </div>
      </form>
    </div>
  );
}

export default ForgotPasswordPage;
