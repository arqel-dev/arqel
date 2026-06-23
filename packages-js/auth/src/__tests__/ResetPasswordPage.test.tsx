import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ResetPasswordPage } from '../ResetPasswordPage';
import { postSpy, setMockErrors, setMockTranslations } from './setup';

describe('ResetPasswordPage', () => {
  it('renders email pre-filled and password inputs (English default)', () => {
    render(<ResetPasswordPage token="tok-123" email="foo@bar.com" />);

    const emailInput = screen.getByLabelText('Email') as HTMLInputElement;
    expect(emailInput.value).toBe('foo@bar.com');
    expect(emailInput).toHaveAttribute('readonly');

    expect(screen.getByLabelText('New password')).toBeInTheDocument();
    expect(screen.getByLabelText('Confirm password')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Set a new password' })).toBeInTheDocument();
  });

  it('calls post on form submit with the configured resetPasswordUrl', () => {
    render(
      <ResetPasswordPage
        token="tok-123"
        email="foo@bar.com"
        resetPasswordUrl="/admin/reset-password"
      />,
    );

    const form = screen.getByRole('button', { name: 'Reset password' }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/reset-password');
  });

  it('displays validation errors from useForm.errors', () => {
    setMockErrors({ email: 'Token inválido.', password: 'Senha curta demais.' });

    render(<ResetPasswordPage token="tok-123" email="foo@bar.com" />);

    expect(screen.getByText('Token inválido.')).toBeInTheDocument();
    expect(screen.getByText('Senha curta demais.')).toBeInTheDocument();
  });

  it('renders the back-to-login link', () => {
    render(<ResetPasswordPage token="tok-123" email="foo@bar.com" loginUrl="/admin/login" />);

    const link = screen.getByRole('link', { name: 'Back to login' });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/admin/login');
  });

  it('renders email as editable when no email prop is passed', () => {
    render(<ResetPasswordPage token="tok-123" />);

    const emailInput = screen.getByLabelText('Email') as HTMLInputElement;
    expect(emailInput).not.toHaveAttribute('readonly');
  });

  it('localizes title, labels and buttons from the i18n dictionary (pt_BR)', () => {
    setMockTranslations({
      arqel: {
        auth: {
          reset_title: 'Definir nova senha',
          reset_description: 'Escolha uma nova senha para sua conta',
          email: 'E-mail',
          reset_new_password: 'Nova senha',
          confirm_password: 'Confirmar senha',
          reset_submit: 'Redefinir senha',
          back_to_login: 'Voltar ao login',
        },
      },
    });

    render(<ResetPasswordPage token="tok-123" email="foo@bar.com" />);

    expect(screen.getByRole('heading', { name: 'Definir nova senha' })).toBeInTheDocument();
    expect(screen.getByLabelText('E-mail')).toBeInTheDocument();
    expect(screen.getByLabelText('Nova senha')).toBeInTheDocument();
    expect(screen.getByLabelText('Confirmar senha')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Redefinir senha' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Voltar ao login' })).toBeInTheDocument();
  });
});
