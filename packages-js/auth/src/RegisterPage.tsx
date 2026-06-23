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
import { useArqelTranslations } from '@arqel-dev/react/utils';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface RegisterPageProps {
  /** Mostra o link "Já tem conta? Entrar" quando true. */
  canLogin?: boolean;
  /** URL de submit (default `/admin/register`). */
  registerUrl?: string;
  /** URL para login (default `/admin/login`). */
  loginUrl?: string;
  /** Texto do header (default "Create an account"). */
  title?: string;
  /** Subtítulo opcional. */
  description?: string;
  /** URL/path da imagem do hero panel à direita. */
  heroImageSrc?: string;
  /** Alt text da imagem hero. */
  heroImageAlt?: string;
}

type RegisterFormData = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

/**
 * Página de registro bundled de Arqel.
 *
 * Layout shadcn `signup-04` (split-screen Card). Renderizada via
 * Inertia em `arqel-dev/auth/Register`.
 */
export function RegisterPage({
  canLogin = true,
  registerUrl = '/admin/register',
  loginUrl = '/admin/login',
  title,
  description,
  heroImageSrc = '/login-hero.svg',
  heroImageAlt,
}: RegisterPageProps): ReactElement {
  const t = useArqelTranslations();
  const { data, setData, post, processing, errors, reset } = useForm<RegisterFormData>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(registerUrl, {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <div className="flex min-h-svh w-full items-center justify-center bg-muted p-6 md:p-10">
      <div className="flex w-full max-w-3xl flex-col gap-6">
        <Card className="overflow-hidden p-0">
          <CardContent className="grid p-0 md:grid-cols-2">
            <form onSubmit={handleSubmit} className="p-6 md:p-8" noValidate>
              <FieldGroup>
                <div className="flex flex-col items-center gap-2 text-center">
                  <h1 className="text-2xl font-bold">
                    {title ?? t('arqel.auth.register_title', 'Create an account')}
                  </h1>
                  <p className="text-balance text-muted-foreground">
                    {description ??
                      t('arqel.auth.register_description', 'Sign up to access the admin panel')}
                  </p>
                </div>

                <Field>
                  <FieldLabel htmlFor="name">{t('arqel.auth.name', 'Name')}</FieldLabel>
                  <Input
                    id="name"
                    type="text"
                    name="name"
                    value={data.name}
                    autoComplete="name"
                    required
                    aria-invalid={Boolean(errors.name)}
                    onChange={(event) => setData('name', event.target.value)}
                  />
                  {errors.name ? <FieldError>{errors.name}</FieldError> : null}
                </Field>

                <Field>
                  <FieldLabel htmlFor="email">{t('arqel.auth.email', 'Email')}</FieldLabel>
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
                  <FieldLabel htmlFor="password">{t('arqel.auth.password', 'Password')}</FieldLabel>
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
                  <FieldLabel htmlFor="password_confirmation">
                    {t('arqel.auth.confirm_password', 'Confirm password')}
                  </FieldLabel>
                  <Input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    value={data.password_confirmation}
                    autoComplete="new-password"
                    required
                    onChange={(event) => setData('password_confirmation', event.target.value)}
                  />
                </Field>

                <Field>
                  <Button type="submit" disabled={processing}>
                    {processing
                      ? t('arqel.auth.register_submitting', 'Creating account…')
                      : t('arqel.auth.register_submit', 'Create account')}
                  </Button>
                </Field>

                {canLogin ? (
                  <FieldDescription className="text-center">
                    {t('arqel.auth.have_account', 'Already have an account?')}{' '}
                    <a href={loginUrl} className="underline underline-offset-4">
                      {t('arqel.auth.sign_in', 'Sign in')}
                    </a>
                  </FieldDescription>
                ) : null}
              </FieldGroup>
            </form>

            <div className="bg-primary/10 relative hidden md:block">
              <img
                src={heroImageSrc}
                alt={heroImageAlt ?? t('arqel.auth.register_hero_alt', 'Register illustration')}
                className="absolute inset-0 h-full w-full object-cover"
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export default RegisterPage;
