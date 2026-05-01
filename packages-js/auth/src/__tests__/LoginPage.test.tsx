import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { LoginPage } from '../LoginPage';
import { postSpy, setMockErrors } from './setup';

describe('LoginPage', () => {
  it('renders email, password fields and submit button', () => {
    render(<LoginPage />);

    expect(screen.getByLabelText(/e-mail/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/^senha$/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /entrar/i })).toBeInTheDocument();
  });

  it('calls post on form submit with the configured loginUrl', () => {
    render(<LoginPage loginUrl="/admin/login" />);

    const form = screen.getByRole('button', { name: /entrar/i }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/login');
  });

  it('renders the "Criar conta" link when canRegister is true', () => {
    render(<LoginPage canRegister registerUrl="/register" />);

    const link = screen.getByRole('link', { name: /criar conta/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/register');
  });

  it('renders the "Esqueci minha senha" link when canResetPassword is true', () => {
    render(<LoginPage canResetPassword forgotPasswordUrl="/forgot-password" />);

    const link = screen.getByRole('link', { name: /esqueci/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/forgot-password');
  });

  it('does not render conditional links when flags are false', () => {
    render(<LoginPage />);

    expect(screen.queryByRole('link', { name: /criar conta/i })).toBeNull();
    expect(screen.queryByRole('link', { name: /esqueci/i })).toBeNull();
  });

  it('displays validation errors from useForm.errors', () => {
    setMockErrors({ email: 'Credenciais inválidas.' });

    render(<LoginPage />);

    expect(screen.getByText('Credenciais inválidas.')).toBeInTheDocument();
  });
});
