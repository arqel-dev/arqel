import { useArqelTranslations } from '@arqel-dev/react/utils';
import {
  Button,
  Card,
  CardContent,
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
  Input,
} from '@arqel-dev/ui';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactElement } from 'react';

export interface ProfilePageProps {
  user: { id?: number; name?: string; email?: string };
  /** Submit URL for account data (default `/admin/profile`). */
  updateUrl?: string;
  /** Submit URL for password change (default `/admin/profile/password`). */
  passwordUrl?: string;
}

type ProfileFormData = { name: string; email: string };
type PasswordFormData = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

/**
 * Página de perfil (account settings) bundled de Arqel. Renderiza DENTRO do
 * admin shell (não é a auth split-screen). Dois cards: dados de conta +
 * troca de senha. Resolvida via Inertia em `arqel-dev/auth/Profile`.
 */
export function ProfilePage({
  user,
  updateUrl = '/admin/profile',
  passwordUrl = '/admin/profile/password',
}: ProfilePageProps): ReactElement {
  const t = useArqelTranslations();

  const account = useForm<ProfileFormData>({
    name: user.name ?? '',
    email: user.email ?? '',
  });
  const password = useForm<PasswordFormData>({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const submitAccount = (e: FormEvent) => {
    e.preventDefault();
    account.put(updateUrl, { preserveScroll: true });
  };
  const submitPassword = (e: FormEvent) => {
    e.preventDefault();
    password.put(passwordUrl, {
      preserveScroll: true,
      onSuccess: () => password.reset(),
    });
  };

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <h1 className="text-2xl font-semibold">{t('arqel.auth.profile.title', 'Profile')}</h1>

      <Card>
        <CardContent className="pt-6">
          <form onSubmit={submitAccount}>
            <FieldGroup>
              <h2 className="text-lg font-medium">
                {t('arqel.auth.profile.account_section', 'Account data')}
              </h2>
              <Field>
                <FieldLabel htmlFor="profile-name">
                  {t('arqel.auth.profile.name', 'Name')}
                </FieldLabel>
                <Input
                  id="profile-name"
                  value={account.data.name}
                  onChange={(e) => account.setData('name', e.target.value)}
                />
                {account.errors.name && <FieldError>{account.errors.name}</FieldError>}
              </Field>
              <Field>
                <FieldLabel htmlFor="profile-email">
                  {t('arqel.auth.profile.email', 'Email')}
                </FieldLabel>
                <Input
                  id="profile-email"
                  type="email"
                  value={account.data.email}
                  onChange={(e) => account.setData('email', e.target.value)}
                />
                {account.errors.email && <FieldError>{account.errors.email}</FieldError>}
              </Field>
              <Button type="submit" disabled={account.processing}>
                {t('arqel.auth.profile.save', 'Save')}
              </Button>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="pt-6">
          <form onSubmit={submitPassword}>
            <FieldGroup>
              <h2 className="text-lg font-medium">
                {t('arqel.auth.profile.password_section', 'Password')}
              </h2>
              <Field>
                <FieldLabel htmlFor="current-password">
                  {t('arqel.auth.profile.current_password', 'Current password')}
                </FieldLabel>
                <Input
                  id="current-password"
                  type="password"
                  value={password.data.current_password}
                  onChange={(e) => password.setData('current_password', e.target.value)}
                />
                {password.errors.current_password && (
                  <FieldError>{password.errors.current_password}</FieldError>
                )}
              </Field>
              <Field>
                <FieldLabel htmlFor="new-password">
                  {t('arqel.auth.profile.new_password', 'New password')}
                </FieldLabel>
                <Input
                  id="new-password"
                  type="password"
                  value={password.data.password}
                  onChange={(e) => password.setData('password', e.target.value)}
                />
                {password.errors.password && <FieldError>{password.errors.password}</FieldError>}
              </Field>
              <Field>
                <FieldLabel htmlFor="confirm-password">
                  {t('arqel.auth.profile.confirm_password', 'Confirm password')}
                </FieldLabel>
                <Input
                  id="confirm-password"
                  type="password"
                  value={password.data.password_confirmation}
                  onChange={(e) => password.setData('password_confirmation', e.target.value)}
                />
              </Field>
              <Button type="submit" disabled={password.processing}>
                {t('arqel.auth.profile.change_password', 'Change password')}
              </Button>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
