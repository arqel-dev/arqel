import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { VerifyEmailNoticePage } from '../VerifyEmailNoticePage';
import { postSpy } from './setup';

describe('VerifyEmailNoticePage', () => {
  it('renders the title and resend button', () => {
    render(<VerifyEmailNoticePage />);

    expect(screen.getByRole('heading', { name: /verifique seu e-mail/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /reenviar link/i })).toBeInTheDocument();
  });

  it('calls post on resend button submit', () => {
    render(<VerifyEmailNoticePage resendUrl="/admin/email/verify/resend" />);

    const form = screen.getByRole('button', { name: /reenviar link/i }).closest('form');
    expect(form).not.toBeNull();
    if (form) fireEvent.submit(form);

    expect(postSpy).toHaveBeenCalledTimes(1);
    expect(postSpy.mock.calls[0]?.[0]).toBe('/admin/email/verify/resend');
  });

  it('renders the user email when provided', () => {
    render(<VerifyEmailNoticePage email="user@example.com" />);

    expect(screen.getByText(/user@example\.com/i)).toBeInTheDocument();
  });

  it('renders the status message when verification-link-sent', () => {
    render(<VerifyEmailNoticePage status="verification-link-sent" />);

    expect(screen.getByText(/novo link de verificação/i)).toBeInTheDocument();
  });
});
