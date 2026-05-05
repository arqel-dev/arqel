import {
  Button,
  Card,
  CardContent,
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  Input,
} from '@arqel-dev/ui';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface ResetPasswordPageProps {
  /** Token de reset vindo da URL. */
  token: string;
  /** Email pré-preenchido (vindo da query string `?email=`). */
  email?: string;
  /** URL de submit (default `/admin/reset-password`). */
  resetPasswordUrl?: string;
  /** URL para voltar ao login (default `/admin/login`). */
  loginUrl?: string;
  /** Texto do header (default "Definir nova senha"). */
  title?: string;
  /** Subtítulo opcional. */
  description?: string;
  /** URL/path da imagem do hero panel à direita (visível em md+). */
  heroImageSrc?: string;
  /** Alt text da imagem hero. */
  heroImageAlt?: string;
}

type ResetPasswordFormData = {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
};

/**
 * Página de reset-password bundled de Arqel.
 *
 * Layout split-screen consistente com `LoginPage` (Card com form à
 * esquerda e hero image à direita visível em md+). Usa `useForm()` do
 * Inertia para estado, submit e errors.
 */
export function ResetPasswordPage({
  token,
  email = '',
  resetPasswordUrl = '/admin/reset-password',
  loginUrl = '/admin/login',
  title = 'Definir nova senha',
  description = 'Escolha uma nova senha para sua conta',
  heroImageSrc = '/login-hero.svg',
  heroImageAlt = 'Reset password illustration',
}: ResetPasswordPageProps): ReactElement {
  const { data, setData, post, processing, errors } = useForm<ResetPasswordFormData>({
    token,
    email,
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(resetPasswordUrl);
  };

  return (
    <div className="flex min-h-svh w-full items-center justify-center bg-muted p-6 md:p-10">
      <div className="flex w-full max-w-3xl flex-col gap-6">
        <Card className="overflow-hidden p-0">
          <CardContent className="grid p-0 md:grid-cols-2">
            <form onSubmit={handleSubmit} className="p-6 md:p-8" noValidate>
              <FieldGroup>
                <div className="flex flex-col items-center gap-2 text-center">
                  <h1 className="text-2xl font-bold">{title}</h1>
                  <p className="text-balance text-muted-foreground">{description}</p>
                </div>

                <Field>
                  <FieldLabel htmlFor="email">E-mail</FieldLabel>
                  <Input
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    required
                    readOnly={email !== ''}
                    aria-invalid={Boolean(errors.email)}
                    onChange={(event) => setData('email', event.target.value)}
                  />
                  {errors.email ? <FieldError>{errors.email}</FieldError> : null}
                </Field>

                <Field>
                  <FieldLabel htmlFor="password">Nova senha</FieldLabel>
                  <Input
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="new-password"
                    required
                    aria-invalid={Boolean(errors.password)}
                    onChange={(event) => setData('password', event.target.value)}
                  />
                  {errors.password ? <FieldError>{errors.password}</FieldError> : null}
                </Field>

                <Field>
                  <FieldLabel htmlFor="password_confirmation">Confirmar senha</FieldLabel>
                  <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    required
                    aria-invalid={Boolean(errors.password_confirmation)}
                    onChange={(event) => setData('password_confirmation', event.target.value)}
                  />
                  {errors.password_confirmation ? (
                    <FieldError>{errors.password_confirmation}</FieldError>
                  ) : null}
                </Field>

                <Field>
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Salvando…' : 'Redefinir senha'}
                  </Button>
                </Field>

                <FieldDescription className="text-center">
                  <a href={loginUrl} className="underline underline-offset-4">
                    Voltar ao login
                  </a>
                </FieldDescription>
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

export default ResetPasswordPage;
