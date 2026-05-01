import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface VerifyEmailNoticePageProps {
  /** E-mail do usuário autenticado, exibido no notice. */
  email?: string | null;
  /** Flash status do backend (e.g. `'verification-link-sent'`). */
  status?: string | null;
  /** URL para reenviar (default `/admin/email/verify/resend`). */
  resendUrl?: string;
  /** Texto do header (default "Verifique seu e-mail"). */
  title?: string;
}

/**
 * Página de notice de verificação de e-mail bundled de Arqel.
 *
 * Renderizada via Inertia em `arqel/auth/VerifyEmailNotice`. Mostra o
 * e-mail do user autenticado e oferece um botão de reenvio.
 */
export function VerifyEmailNoticePage({
  email = null,
  status = null,
  resendUrl = '/admin/email/verify/resend',
  title = 'Verifique seu e-mail',
}: VerifyEmailNoticePageProps): ReactElement {
  const { post, processing } = useForm<Record<string, unknown>>({});

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(resendUrl);
  };

  return (
    <div className="arqel-verify-email-page">
      <h1 className="arqel-verify-email-title">{title}</h1>

      <p className="arqel-verify-email-intro">
        {email
          ? `Enviamos um link de verificação para ${email}. Confira sua caixa de entrada.`
          : 'Enviamos um link de verificação para o seu e-mail. Confira sua caixa de entrada.'}
      </p>

      {status === 'verification-link-sent' ? (
        <p className="arqel-verify-email-status" role="status">
          Um novo link de verificação foi enviado.
        </p>
      ) : null}

      <form onSubmit={handleSubmit} className="arqel-verify-email-form">
        <p>Não recebeu? Reenviar:</p>
        <button type="submit" disabled={processing} className="arqel-verify-email-resend">
          {processing ? 'Enviando…' : 'Reenviar link'}
        </button>
      </form>
    </div>
  );
}

export default VerifyEmailNoticePage;
