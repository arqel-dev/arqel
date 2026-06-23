import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { VerifyEmailNoticePage } from '../VerifyEmailNoticePage';
import { postSpy, setMockTranslations } from './setup';

describe('VerifyEmailNoticePage', () => {
  it('renders the title and resend button (English default)', () => {
    render(<VerifyEmailNoticePage />);

    expect(screen.getByRole('heading', { name: 'Verify your email' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Resend link' })).toBeInTheDocument();
  });

  it('calls post on resend button submit', () => {
    render(<VerifyEmailNoticePage resendUrl="/admin/email/verify/resend" />);

    const form = screen.getByRole('button', { name: 'Resend link' }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/email/verify/resend');
  });

  it('interpolates the user email into the intro', () => {
    render(<VerifyEmailNoticePage email="user@example.com" />);

    expect(screen.getByText(/user@example\.com/i)).toBeInTheDocument();
  });

  it('renders the status message when verification-link-sent', () => {
    render(<VerifyEmailNoticePage status="verification-link-sent" />);

    expect(screen.getByText('A new verification link has been sent.')).toBeInTheDocument();
  });

  it('localizes notice copy and interpolates email from the i18n dictionary (pt_BR)', () => {
    setMockTranslations({
      arqel: {
        auth: {
          verify_title: 'Verifique seu e-mail',
          verify_intro:
            'Enviamos um link de verificação para :email. Confira sua caixa de entrada.',
          verify_resent: 'Um novo link de verificação foi enviado.',
          verify_not_received: 'Não recebeu? Clique abaixo para reenviar.',
          verify_resend: 'Reenviar link',
        },
      },
    });

    render(<VerifyEmailNoticePage email="user@example.com" status="verification-link-sent" />);

    expect(screen.getByRole('heading', { name: 'Verifique seu e-mail' })).toBeInTheDocument();
    expect(
      screen.getByText(/Enviamos um link de verificação para user@example\.com/i),
    ).toBeInTheDocument();
    expect(screen.getByText('Um novo link de verificação foi enviado.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Reenviar link' })).toBeInTheDocument();
  });
});
