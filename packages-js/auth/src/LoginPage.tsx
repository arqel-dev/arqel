import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface LoginPageProps {
  /** Mostra o link "Criar conta" quando true. */
  canRegister?: boolean;
  /** Mostra o link "Esqueci minha senha" quando true. */
  canResetPassword?: boolean;
  /** URL de submit (default `/admin/login`). */
  loginUrl?: string;
  /** URL para registro (default `/register`). */
  registerUrl?: string;
  /** URL para reset de senha (default `/forgot-password`). */
  forgotPasswordUrl?: string;
  /** Texto do header (default "Entrar"). */
  title?: string;
}

type LoginFormData = {
  email: string;
  password: string;
  remember: boolean;
};

/**
 * Página de login bundled de Arqel.
 *
 * Renderizada via Inertia em `arqel/auth/Login`. Usa `useForm()` do
 * Inertia para gerenciar estado, submit e errors.
 */
export function LoginPage({
  canRegister = false,
  canResetPassword = false,
  loginUrl = '/admin/login',
  registerUrl = '/register',
  forgotPasswordUrl = '/forgot-password',
  title = 'Entrar',
}: LoginPageProps): ReactElement {
  const { data, setData, post, processing, errors, reset } = useForm<LoginFormData>({
    email: '',
    password: '',
    remember: false,
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(loginUrl, {
      onFinish: () => reset('password'),
    });
  };

  return (
    <div className="arqel-login-page">
      <h1 className="arqel-login-title">{title}</h1>

      <form onSubmit={handleSubmit} className="arqel-login-form" noValidate>
        <div className="arqel-login-field">
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
            <p className="arqel-login-error" role="alert">
              {errors.email}
            </p>
          ) : null}
        </div>

        <div className="arqel-login-field">
          <label htmlFor="password">Senha</label>
          <input
            id="password"
            type="password"
            name="password"
            value={data.password}
            autoComplete="current-password"
            required
            onChange={(event) => setData('password', event.target.value)}
          />
          {errors.password ? (
            <p className="arqel-login-error" role="alert">
              {errors.password}
            </p>
          ) : null}
        </div>

        <label className="arqel-login-remember">
          <input
            type="checkbox"
            name="remember"
            checked={data.remember}
            onChange={(event) => setData('remember', event.target.checked)}
          />
          Lembrar-me
        </label>

        <button type="submit" disabled={processing} className="arqel-login-submit">
          {processing ? 'Entrando…' : 'Entrar'}
        </button>

        <div className="arqel-login-links">
          {canResetPassword ? (
            <a href={forgotPasswordUrl} className="arqel-login-link">
              Esqueci minha senha
            </a>
          ) : null}
          {canRegister ? (
            <a href={registerUrl} className="arqel-login-link">
              Criar conta
            </a>
          ) : null}
        </div>
      </form>
    </div>
  );
}

export default LoginPage;
