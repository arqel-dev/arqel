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
import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface ForgotPasswordPageProps {
  /** URL de submit (default `/admin/forgot-password`). */
  forgotPasswordUrl?: string;
  /** URL para voltar ao login (default `/admin/login`). */
  loginUrl?: string;
  /** Texto do header (default "Recuperar senha"). */
  title?: string;
  /** Subtítulo opcional. */
  description?: string;
  /** URL/path da imagem do hero panel à direita (visível em md+). */
  heroImageSrc?: string;
  /** Alt text da imagem hero. */
  heroImageAlt?: string;
}

type ForgotPasswordFormData = {
  email: string;
};

interface PageProps {
  flash?: { status?: string };
  [key: string]: unknown;
}

/**
 * Página de forgot-password bundled de Arqel.
 *
 * Layout split-screen consistente com `LoginPage` (Card com form à
 * esquerda e hero image à direita visível em md+). Usa `useForm()` do
 * Inertia para estado, submit e errors; lê o flash status via `usePage()`.
 */
export function ForgotPasswordPage({
  forgotPasswordUrl = '/admin/forgot-password',
  loginUrl = '/admin/login',
  title = 'Recuperar senha',
  description = 'Enviaremos um link de redefinição para o seu e-mail',
  heroImageSrc = '/login-hero.svg',
  heroImageAlt = 'Forgot password illustration',
}: ForgotPasswordPageProps): ReactElement {
  const { data, setData, post, processing, errors } = useForm<ForgotPasswordFormData>({
    email: '',
  });

  const page = usePage<PageProps>();
  const status = page.props.flash?.status;

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(forgotPasswordUrl);
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

                {status ? (
                  <FieldDescription className="text-center text-green-600 dark:text-green-400">
                    {status}
                  </FieldDescription>
                ) : null}

                <Field>
                  <FieldLabel htmlFor="email">E-mail</FieldLabel>
                  <Input
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    autoComplete="username"
                    required
                    aria-invalid={Boolean(errors.email)}
                    onChange={(event) => setData('email', event.target.value)}
                  />
                  {errors.email ? <FieldError>{errors.email}</FieldError> : null}
                </Field>

                <Field>
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Enviando…' : 'Enviar link de reset'}
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

export default ForgotPasswordPage;
