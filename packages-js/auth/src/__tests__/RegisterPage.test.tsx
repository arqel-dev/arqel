import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { RegisterPage } from '../RegisterPage';
import { postSpy, setMockErrors, setMockTranslations } from './setup';

describe('RegisterPage', () => {
  it('renders name, email, password and confirmation fields and submit button', () => {
    render(<RegisterPage />);

    expect(screen.getByLabelText('Name')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByLabelText('Confirm password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Create account' })).toBeInTheDocument();
  });

  it('calls post on form submit with the configured registerUrl', () => {
    render(<RegisterPage registerUrl="/admin/register" />);

    const form = screen.getByRole('button', { name: 'Create account' }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/register');
  });

  it('renders the login link when canLogin is true', () => {
    render(<RegisterPage canLogin loginUrl="/admin/login" />);

    const link = screen.getByRole('link', { name: 'Sign in' });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/admin/login');
  });

  it('does not render login link when canLogin is false', () => {
    render(<RegisterPage canLogin={false} />);

    expect(screen.queryByRole('link', { name: 'Sign in' })).toBeNull();
  });

  it('displays validation errors from useForm.errors', () => {
    setMockErrors({ email: 'O e-mail já está em uso.' });

    render(<RegisterPage />);

    expect(screen.getByText('O e-mail já está em uso.')).toBeInTheDocument();
  });

  it('localizes title, labels and buttons from the i18n dictionary (pt_BR)', () => {
    setMockTranslations({
      arqel: {
        auth: {
          register_title: 'Criar uma conta',
          name: 'Nome',
          email: 'E-mail',
          password: 'Senha',
          confirm_password: 'Confirmar senha',
          register_submit: 'Criar conta',
          have_account: 'Já tem uma conta?',
          sign_in: 'Entrar',
        },
      },
    });

    render(<RegisterPage canLogin />);

    expect(screen.getByRole('heading', { name: 'Criar uma conta' })).toBeInTheDocument();
    expect(screen.getByLabelText('Nome')).toBeInTheDocument();
    expect(screen.getByLabelText('Confirmar senha')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Criar conta' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Entrar' })).toBeInTheDocument();
  });
});
