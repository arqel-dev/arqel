import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ForgotPasswordPage } from '../ForgotPasswordPage';
import { postSpy, setMockErrors, setMockPageProps } from './setup';

describe('ForgotPasswordPage', () => {
  it('renders email input and submit button', () => {
    render(<ForgotPasswordPage />);

    expect(screen.getByLabelText(/e-mail/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /enviar/i })).toBeInTheDocument();
  });

  it('calls post on form submit with the configured forgotPasswordUrl', () => {
    render(<ForgotPasswordPage forgotPasswordUrl="/admin/forgot-password" />);

    const form = screen.getByRole('button', { name: /enviar/i }).closest('form');
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

  it('renders the "Voltar ao login" link', () => {
    render(<ForgotPasswordPage loginUrl="/admin/login" />);

    const link = screen.getByRole('link', { name: /voltar ao login/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/admin/login');
  });

  it('displays validation errors from useForm.errors', () => {
    setMockErrors({ email: 'E-mail inválido.' });

    render(<ForgotPasswordPage />);

    expect(screen.getByText('E-mail inválido.')).toBeInTheDocument();
  });
});
