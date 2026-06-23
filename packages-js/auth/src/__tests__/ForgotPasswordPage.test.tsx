import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ForgotPasswordPage } from '../ForgotPasswordPage';
import { postSpy, setMockErrors, setMockPageProps, setMockTranslations } from './setup';

describe('ForgotPasswordPage', () => {
  it('renders email input and submit button (English default)', () => {
    render(<ForgotPasswordPage />);

    expect(screen.getByLabelText('Email')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Send reset link' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Recover password' })).toBeInTheDocument();
  });

  it('calls post on form submit with the configured forgotPasswordUrl', () => {
    render(<ForgotPasswordPage forgotPasswordUrl="/admin/forgot-password" />);

    const form = screen.getByRole('button', { name: 'Send reset link' }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/forgot-password');
  });

  it('renders the flash status when present in page props', () => {
    setMockPageProps({ flash: { status: 'A reset link has been sent if the email exists.' } });

    render(<ForgotPasswordPage />);

    expect(screen.getByText('A reset link has been sent if the email exists.')).toBeInTheDocument();
  });

  it('renders the back-to-login link', () => {
    render(<ForgotPasswordPage loginUrl="/admin/login" />);

    const link = screen.getByRole('link', { name: 'Back to login' });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/admin/login');
  });

  it('displays validation errors from useForm.errors', () => {
    setMockErrors({ email: 'E-mail inválido.' });

    render(<ForgotPasswordPage />);

    expect(screen.getByText('E-mail inválido.')).toBeInTheDocument();
  });

  it('localizes title, label, button and back link from the i18n dictionary (pt_BR)', () => {
    setMockTranslations({
      arqel: {
        auth: {
          forgot_title: 'Recuperar senha',
          email: 'E-mail',
          forgot_submit: 'Enviar link de redefinição',
          back_to_login: 'Voltar ao login',
        },
      },
    });

    render(<ForgotPasswordPage />);

    expect(screen.getByRole('heading', { name: 'Recuperar senha' })).toBeInTheDocument();
    expect(screen.getByLabelText('E-mail')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Enviar link de redefinição' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Voltar ao login' })).toBeInTheDocument();
  });
});
