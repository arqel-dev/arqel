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

export interface LoginPageProps {
  /** Mostra o link "Criar conta" quando true. */
  canRegister?: boolean;
  /** Mostra o link "Esqueci minha senha" quando true. */
  canResetPassword?: boolean;
  /** URL de submit (default `/admin/login`). */
  loginUrl?: string;
  /** URL para registro (default `/register`). */
  registerUrl?: string;
  /** URL para reset de senha (default `/forgot-password`). */
  forgotPasswordUrl?: string;
  /** Texto do header (default "Welcome back"). */
  title?: string;
  /** Subtítulo opcional. */
  description?: string;
  /** URL/path da imagem do hero panel à direita (visível em md+). */
  heroImageSrc?: string;
  /** Alt text da imagem hero. */
  heroImageAlt?: string;
}

type LoginFormData = {
  email: string;
  password: string;
  remember: boolean;
};

/**
 * Página de login bundled de Arqel.
 *
 * Layout shadcn `login-04` (split-screen Card: form à esquerda, hero
 * image à direita visível em md+). Lógica `useForm()` do Inertia para
 * estado, submit e errors. Renderizada via Inertia em `arqel-dev/auth/Login`.
 */
export function LoginPage({
  canRegister = false,
  canResetPassword = false,
  loginUrl = '/admin/login',
  registerUrl = '/register',
  forgotPasswordUrl = '/forgot-password',
  title,
  description,
  heroImageSrc = '/login-hero.svg',
  heroImageAlt,
}: LoginPageProps): ReactElement {
  const t = useArqelTranslations();
  const { data, setData, post, processing, errors, reset } = useForm<LoginFormData>({
    email: '',
    password: '',
    remember: false,
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    post(loginUrl, {
      onFinish: () => reset('password'),
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
                    {title ?? t('arqel.auth.login_title', 'Welcome back')}
                  </h1>
                  <p className="text-balance text-muted-foreground">
                    {description ?? t('arqel.auth.login_description', 'Login to your account')}
                  </p>
                </div>

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
                  <div className="flex items-center">
                    <FieldLabel htmlFor="password">
                      {t('arqel.auth.password', 'Password')}
                    </FieldLabel>
                    {canResetPassword ? (
                      <a
                        href={forgotPasswordUrl}
                        className="ml-auto text-sm underline-offset-2 hover:underline"
                      >
                        {t('arqel.auth.forgot_password', 'Forgot your password?')}
                      </a>
                    ) : null}
                  </div>
                  <Input
                    id="password"
                    type="password"
                    name="password"
                    value={data.password}
                    autoComplete="current-password"
                    required
                    aria-invalid={Boolean(errors.password)}
                    onChange={(event) => setData('password', event.target.value)}
                  />
                  {errors.password ? <FieldError>{errors.password}</FieldError> : null}
                </Field>

                <Field>
                  <Button type="submit" disabled={processing}>
                    {processing
                      ? t('arqel.auth.login_submitting', 'Signing in…')
                      : t('arqel.auth.login_submit', 'Login')}
                  </Button>
                </Field>

                {canRegister ? (
                  <FieldDescription className="text-center">
                    {t('arqel.auth.no_account', "Don't have an account?")}{' '}
                    <a href={registerUrl} className="underline underline-offset-4">
                      {t('arqel.auth.sign_up', 'Sign up')}
                    </a>
                  </FieldDescription>
                ) : null}
              </FieldGroup>
            </form>

            <div className="bg-primary/10 relative hidden md:block">
              <img
                src={heroImageSrc}
                alt={heroImageAlt ?? t('arqel.auth.login_hero_alt', 'Login illustration')}
                className="absolute inset-0 h-full w-full object-cover"
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export default LoginPage;
