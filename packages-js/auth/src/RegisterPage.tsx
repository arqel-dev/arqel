import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface RegisterPageProps {
  /** Mostra o link "Já tem conta? Entrar" quando true. */
  canLogin?: boolean;
  /** URL de submit (default `/admin/register`). */
  registerUrl?: string;
  /** URL para login (default `/admin/login`). */
  loginUrl?: string;
  /** Texto do header (default "Criar conta"). */
  title?: string;
}

type RegisterFormData = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

/**
 * Página de registro bundled de Arqel.
 *
 * Renderizada via Inertia em `arqel-dev/auth/Register`. Usa `useForm()` do
 * Inertia para gerenciar estado, submit e errors.
 */
export function RegisterPage({
  canLogin = true,
  registerUrl = '/admin/register',
  loginUrl = '/admin/login',
  title = 'Criar conta',
}: RegisterPageProps): ReactElement {
  const { data, setData, post, processing, errors, reset } = useForm<RegisterFormData>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(registerUrl, {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <div className="arqel-register-page">
      <h1 className="arqel-register-title">{title}</h1>

      <form onSubmit={handleSubmit} className="arqel-register-form" noValidate>
        <div className="arqel-register-field">
          <label htmlFor="name">Nome</label>
          <input
            id="name"
            type="text"
            name="name"
            value={data.name}
            autoComplete="name"
            required
            onChange={(event) => setData('name', event.target.value)}
          />
          {errors.name ? (
            <p className="arqel-register-error" role="alert">
              {errors.name}
            </p>
          ) : null}
        </div>

        <div className="arqel-register-field">
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
            <p className="arqel-register-error" role="alert">
              {errors.email}
            </p>
          ) : null}
        </div>

        <div className="arqel-register-field">
          <label htmlFor="password">Senha</label>
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
            <p className="arqel-register-error" role="alert">
              {errors.password}
            </p>
          ) : null}
        </div>

        <div className="arqel-register-field">
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

        <button type="submit" disabled={processing} className="arqel-register-submit">
          {processing ? 'Criando…' : 'Criar conta'}
        </button>

        {canLogin ? (
          <div className="arqel-register-links">
            <a href={loginUrl} className="arqel-register-link">
              Já tem conta? Entrar
            </a>
          </div>
        ) : null}
      </form>
    </div>
  );
}

export default RegisterPage;
