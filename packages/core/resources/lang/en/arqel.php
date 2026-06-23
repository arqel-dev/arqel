<?php

declare(strict_types=1);

return [
    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'back' => 'Back',
        'view' => 'View',
        'restore' => 'Restore',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'retry' => 'Retry',
        'menu' => 'Actions',
    ],
    'nav' => [
        'dashboard' => 'Dashboard',
        'settings' => 'Settings',
        'logout' => 'Sign out',
        'profile' => 'Profile',
        'home' => 'Home',
    ],
    'auth' => [
        'login' => 'Sign in',
        'logout' => 'Sign out',
        'register' => 'Register',
        'forgot_password' => 'Forgot your password?',
        'reset_password' => 'Reset password',
        'remember_me' => 'Remember me',
        'email' => 'Email',
        'password' => 'Password',
        'name' => 'Name',
        'invalid_credentials' => 'These credentials do not match our records.',
        // Bundled auth page copy (login/register/forgot-password/verify-email).
        // Used by @arqel-dev/auth React pages via useArqelTranslations().
        'login_title' => 'Welcome back',
        'login_description' => 'Login to your account',
        'login_hero_alt' => 'Login illustration',
        'login_submit' => 'Login',
        'login_submitting' => 'Signing in…',
        'no_account' => "Don't have an account?",
        'sign_up' => 'Sign up',
        'sign_in' => 'Sign in',
        'register_title' => 'Create an account',
        'register_description' => 'Sign up to access the admin panel',
        'register_hero_alt' => 'Register illustration',
        'register_submit' => 'Create account',
        'register_submitting' => 'Creating account…',
        'confirm_password' => 'Confirm password',
        'have_account' => 'Already have an account?',
        'forgot_title' => 'Recover password',
        'forgot_description' => 'We will send a reset link to your email',
        'forgot_hero_alt' => 'Forgot password illustration',
        'forgot_submit' => 'Send reset link',
        'forgot_submitting' => 'Sending…',
        'back_to_login' => 'Back to login',
        'verify_title' => 'Verify your email',
        'verify_hero_alt' => 'Verify email illustration',
        'verify_intro' => 'We sent a verification link to :email. Check your inbox.',
        'verify_intro_generic' => 'We sent a verification link to your email. Check your inbox.',
        'verify_resent' => 'A new verification link has been sent.',
        'verify_not_received' => "Didn't receive it? Click below to resend.",
        'verify_resend' => 'Resend link',
        'verify_resending' => 'Sending…',
        'reset_link_sent' => 'A reset link has been sent if the email exists.',
    ],
    'messages' => [
        'unsaved_changes' => 'You have unsaved changes.',
        'delete_confirm' => 'Are you sure you want to delete this?',
        'cannot_undo' => 'This action cannot be undone.',
        // Type-to-confirm prompt; :value is rendered as inline <code>.
        'type_to_confirm' => 'Type :value to confirm',
        'created' => 'Record created.',
        'updated' => 'Record updated.',
        'deleted' => 'Record deleted.',
        'restored' => 'Record restored.',
    ],
    'errors' => [
        'unauthorized' => 'You are not authorized to perform this action.',
        'forbidden' => 'Forbidden.',
        'not_found' => 'Record not found.',
        'server_error' => 'An unexpected error occurred.',
    ],
    'locale' => [
        'switch' => 'Language',
        'en' => 'English',
        'pt_BR' => 'Português (Brasil)',
    ],
    'pagination' => [
        'previous' => 'Previous',
        'next' => 'Next',
        'showing' => 'Showing :from to :to of :total results',
    ],
    // Visible H1 titles for the default CRUD pages. :label is the resource's
    // (already-translated) singular label; 'fallback' is used when no label
    // or record title is available.
    'pages' => [
        'create' => 'Create :label',
        'edit' => 'Edit :label',
        'record' => 'Record',
        'fallback' => 'record',
    ],
    'tenant' => [
        // Visible + announced fallback name for a tenant with no `name`.
        'unnamed' => 'Tenant :id',
    ],
    // Accessible names (aria-label / sr-only) for framework UI chrome. Kept
    // distinct from short visible labels so screen-reader users hear a full,
    // descriptive accessible name.
    'aria' => [
        'flash_dismiss' => 'Dismiss',
        'chart_loading' => 'Loading chart',
        'stat_sparkline' => 'Trend sparkline',
        'palette_title' => 'Command palette',
        // Pluralizable sr-only live region: "1 command" / "N commands".
        'palette_results' => '{one} :count command|{other} :count commands',
        'palette_list' => 'Commands',
        'breadcrumb' => 'Breadcrumb',
        'theme_toggle_light' => 'Switch to light theme',
        'theme_toggle_dark' => 'Switch to dark theme',
        'tenant_switch' => 'Switch tenant (current: :tenant)',
    ],
];
