import { Button, Card, CardContent, Field, FieldDescription, FieldGroup } from '@arqel-dev/ui';
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
  /** URL/path da imagem do hero panel à direita (visível em md+). */
  heroImageSrc?: string;
  /** Alt text da imagem hero. */
  heroImageAlt?: string;
}

/**
 * Página de notice de verificação de e-mail bundled de Arqel.
 *
 * Layout split-screen consistente com `LoginPage` (Card com mensagem +
 * botão de reenvio à esquerda e hero image à direita visível em md+).
 */
export function VerifyEmailNoticePage({
  email = null,
  status = null,
  resendUrl = '/admin/email/verify/resend',
  title = 'Verifique seu e-mail',
  heroImageSrc = '/login-hero.svg',
  heroImageAlt = 'Verify email illustration',
}: VerifyEmailNoticePageProps): ReactElement {
  const { post, processing } = useForm({});

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(resendUrl);
  };

  const intro = email
    ? `Enviamos um link de verificação para ${email}. Confira sua caixa de entrada.`
    : 'Enviamos um link de verificação para o seu e-mail. Confira sua caixa de entrada.';

  return (
    <div className="flex min-h-svh w-full items-center justify-center bg-muted p-6 md:p-10">
      <div className="flex w-full max-w-3xl flex-col gap-6">
        <Card className="overflow-hidden p-0">
          <CardContent className="grid p-0 md:grid-cols-2">
            <form onSubmit={handleSubmit} className="p-6 md:p-8">
              <FieldGroup>
                <div className="flex flex-col items-center gap-2 text-center">
                  <h1 className="text-2xl font-bold">{title}</h1>
                  <p className="text-balance text-muted-foreground">{intro}</p>
                </div>

                {status === 'verification-link-sent' ? (
                  <FieldDescription className="text-center text-green-600 dark:text-green-400">
                    Um novo link de verificação foi enviado.
                  </FieldDescription>
                ) : null}

                <FieldDescription className="text-center">
                  Não recebeu? Clique abaixo para reenviar.
                </FieldDescription>

                <Field>
                  <Button type="submit" disabled={processing} variant="outline">
                    {processing ? 'Enviando…' : 'Reenviar link'}
                  </Button>
                </Field>
              </FieldGroup>
            </form>

            <div className="bg-primary/10 relative hidden md:block">
              <img
                src={heroImageSrc}
                alt={heroImageAlt}
                className="absolute inset-0 h-full w-full object-cover"
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export default VerifyEmailNoticePage;
