import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ResetPasswordPage } from '../ResetPasswordPage';
import { postSpy, setMockErrors } from './setup';

describe('ResetPasswordPage', () => {
  it('renders email pre-filled and password inputs', () => {
    render(<ResetPasswordPage token="tok-123" email="foo@bar.com" />);

    const emailInput = screen.getByLabelText(/e-mail/i) as HTMLInputElement;
    expect(emailInput.value).toBe('foo@bar.com');
    expect(emailInput).toHaveAttribute('readonly');

    expect(screen.getByLabelText(/^nova senha$/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/confirmar senha/i)).toBeInTheDocument();
  });

  it('calls post on form submit with the configured resetPasswordUrl', () => {
    render(
      <ResetPasswordPage
        token="tok-123"
        email="foo@bar.com"
        resetPasswordUrl="/admin/reset-password"
      />,
    );

    const form = screen.getByRole('button', { name: /redefinir/i }).closest('form');
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

  it('renders the "Voltar ao login" link', () => {
    render(<ResetPasswordPage token="tok-123" email="foo@bar.com" loginUrl="/admin/login" />);

    const link = screen.getByRole('link', { name: /voltar ao login/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/admin/login');
  });

  it('renders email as editable when no email prop is passed', () => {
    render(<ResetPasswordPage token="tok-123" />);

    const emailInput = screen.getByLabelText(/e-mail/i) as HTMLInputElement;
    expect(emailInput).not.toHaveAttribute('readonly');
  });
});
