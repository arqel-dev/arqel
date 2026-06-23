import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { LoginPage } from '../LoginPage';
import { postSpy, setMockErrors, setMockTranslations } from './setup';

describe('LoginPage', () => {
  it('renders email, password fields and submit button (English default)', () => {
    render(<LoginPage />);

    expect(screen.getByLabelText('Email')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Login' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Welcome back' })).toBeInTheDocument();
  });

  it('calls post on form submit with the configured loginUrl', () => {
    render(<LoginPage loginUrl="/admin/login" />);

    const form = screen.getByRole('button', { name: 'Login' }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/login');
  });

  it('renders the sign-up link when canRegister is true', () => {
    render(<LoginPage canRegister registerUrl="/register" />);

    const link = screen.getByRole('link', { name: 'Sign up' });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/register');
  });

  it('renders the forgot-password link when canResetPassword is true', () => {
    render(<LoginPage canResetPassword forgotPasswordUrl="/forgot-password" />);

    const link = screen.getByRole('link', { name: /forgot your password/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/forgot-password');
  });

  it('does not render conditional links when flags are false', () => {
    render(<LoginPage />);

    expect(screen.queryByRole('link', { name: 'Sign up' })).toBeNull();
    expect(screen.queryByRole('link', { name: /forgot your password/i })).toBeNull();
  });

  it('displays validation errors from useForm.errors', () => {
    setMockErrors({ email: 'Credenciais inválidas.' });

    render(<LoginPage />);

    expect(screen.getByText('Credenciais inválidas.')).toBeInTheDocument();
  });

  it('localizes title, labels and buttons from the i18n dictionary (pt_BR)', () => {
    setMockTranslations({
      arqel: {
        auth: {
          login_title: 'Bem-vindo de volta',
          email: 'E-mail',
          password: 'Senha',
          login_submit: 'Entrar',
          forgot_password: 'Esqueceu sua senha?',
          sign_up: 'Criar conta',
        },
      },
    });

    render(<LoginPage canRegister canResetPassword />);

    expect(screen.getByRole('heading', { name: 'Bem-vindo de volta' })).toBeInTheDocument();
    expect(screen.getByLabelText('E-mail')).toBeInTheDocument();
    expect(screen.getByLabelText('Senha')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Entrar' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Esqueceu sua senha?' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Criar conta' })).toBeInTheDocument();
  });
});
