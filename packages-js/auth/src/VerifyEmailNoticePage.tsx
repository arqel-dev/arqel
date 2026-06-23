import { useArqelTranslations } from '@arqel-dev/react/utils';
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
  title,
  heroImageSrc = '/login-hero.svg',
  heroImageAlt,
}: VerifyEmailNoticePageProps): ReactElement {
  const t = useArqelTranslations();
  const { post, processing } = useForm({});

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(resendUrl);
  };

  const intro = email
    ? t('arqel.auth.verify_intro', 'We sent a verification link to :email. Check your inbox.', {
        email,
      })
    : t(
        'arqel.auth.verify_intro_generic',
        'We sent a verification link to your email. Check your inbox.',
      );

  return (
    <div className="flex min-h-svh w-full items-center justify-center bg-muted p-6 md:p-10">
      <div className="flex w-full max-w-3xl flex-col gap-6">
        <Card className="overflow-hidden p-0">
          <CardContent className="grid p-0 md:grid-cols-2">
            <form onSubmit={handleSubmit} className="p-6 md:p-8">
              <FieldGroup>
                <div className="flex flex-col items-center gap-2 text-center">
                  <h1 className="text-2xl font-bold">
                    {title ?? t('arqel.auth.verify_title', 'Verify your email')}
                  </h1>
                  <p className="text-balance text-muted-foreground">{intro}</p>
                </div>

                {status === 'verification-link-sent' ? (
                  <FieldDescription className="text-center text-green-600 dark:text-green-400">
                    {t('arqel.auth.verify_resent', 'A new verification link has been sent.')}
                  </FieldDescription>
                ) : null}

                <FieldDescription className="text-center">
                  {t('arqel.auth.verify_not_received', "Didn't receive it? Click below to resend.")}
                </FieldDescription>

                <Field>
                  <Button type="submit" disabled={processing} variant="outline">
                    {processing
                      ? t('arqel.auth.verify_resending', 'Sending…')
                      : t('arqel.auth.verify_resend', 'Resend link')}
                  </Button>
                </Field>
              </FieldGroup>
            </form>

            <div className="bg-primary/10 relative hidden md:block">
              <img
                src={heroImageSrc}
                alt={heroImageAlt ?? t('arqel.auth.verify_hero_alt', 'Verify email illustration')}
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
