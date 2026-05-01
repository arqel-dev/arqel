import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface ResetPasswordPageProps {
  /** Token de reset vindo da URL. */
  token: string;
  /** Email pré-preenchido (vindo da query string `?email=`). */
  email?: string;
  /** URL de submit (default `/admin/reset-password`). */
  resetPasswordUrl?: string;
  /** URL para voltar ao login (default `/admin/login`). */
  loginUrl?: string;
  /** Texto do header (default "Definir nova senha"). */
  title?: string;
}

type ResetPasswordFormData = {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
};

/**
 * Página de reset-password bundled de Arqel.
 *
 * Renderizada via Inertia em `arqel/auth/ResetPassword`. Recebe token
 * via prop (extraído da rota) e email via query string.
 */
export function ResetPasswordPage({
  token,
  email = '',
  resetPasswordUrl = '/admin/reset-password',
  loginUrl = '/admin/login',
  title = 'Definir nova senha',
}: ResetPasswordPageProps): ReactElement {
  const { data, setData, post, processing, errors } = useForm<ResetPasswordFormData>({
    token,
    email,
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(resetPasswordUrl);
  };

  return (
    <div className="arqel-reset-password-page">
      <h1 className="arqel-reset-password-title">{title}</h1>

      <form onSubmit={handleSubmit} className="arqel-reset-password-form" noValidate>
        <div className="arqel-reset-password-field">
          <label htmlFor="email">E-mail</label>
          <input
            id="email"
            type="email"
            name="email"
            value={data.email}
            autoComplete="username"
            required
            readOnly={email !== ''}
            onChange={(event) => setData('email', event.target.value)}
          />
          {errors.email ? (
            <p className="arqel-reset-password-error" role="alert">
              {errors.email}
            </p>
          ) : null}
        </div>

        <div className="arqel-reset-password-field">
          <label htmlFor="password">Nova senha</label>
          <input
            id="password"
            type="password"
            name="password"
            value={data.password}
            autoComplete="new-password"
            required
            onChange={(event) => setData('password', event.target.value)}
          />
          {errors.password ? (
            <p className="arqel-reset-password-error" role="alert">
              {errors.password}
            </p>
          ) : null}
        </div>

        <div className="arqel-reset-password-field">
          <label htmlFor="password_confirmation">Confirmar senha</label>
          <input
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            value={data.password_confirmation}
            autoComplete="new-password"
            required
            onChange={(event) => setData('password_confirmation', event.target.value)}
          />
        </div>

        <button type="submit" disabled={processing} className="arqel-reset-password-submit">
          {processing ? 'Salvando…' : 'Redefinir senha'}
        </button>

        <div className="arqel-reset-password-links">
          <a href={loginUrl} className="arqel-reset-password-link">
            Voltar ao login
          </a>
        </div>
      </form>
    </div>
  );
}

export default ResetPasswordPage;
