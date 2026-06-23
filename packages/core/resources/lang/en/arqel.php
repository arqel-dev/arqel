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
        'reset_title' => 'Set a new password',
        'reset_description' => 'Choose a new password for your account',
        'reset_hero_alt' => 'Reset password illustration',
        'reset_new_password' => 'New password',
        'reset_submit' => 'Reset password',
        'reset_submitting' => 'Saving…',
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
    // Accessible names + visible placeholders for the framework's field
    // inputs (@arqel-dev/fields React renderers). :label / :resource are
    // already-translated runtime values substituted client-side. Each value
    // mirrors the component's original English literal so accessible names
    // stay stable for non-Arqel pages and a11y consumer tests.
    'fields' => [
        'increment' => 'Increment',
        'decrement' => 'Decrement',
        'file' => [
            'upload' => 'File upload',
        ],
        'belongsto' => [
            'search' => 'Search :resource…',
        ],
        'multiselect' => [
            'remove' => 'Remove :label',
        ],
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
    // Strings for the @arqel-dev/ai field renderers (AiTextInput,
    // AiSelectInput, AiExtractInput, AiImageInput, AiTranslateInput):
    // default action button labels, visible action controls, user-facing
    // error banners and the sr-only/aria status live regions. Resolved
    // client-side via useArqelTranslations() with the English literal as
    // fallback, so a translation gap never regresses the UI.
    'ai' => [
        // Default action button labels (used when the server omits buttonLabel).
        'generate' => 'Generate with AI',
        'regenerate' => 'Regenerate',
        'classify' => 'Classify with AI',
        'extract' => 'Extract with AI',
        'analyze' => 'Analyze with AI',
        // Visible action controls.
        'apply' => 'Apply',
        'apply_field' => 'Apply :field',
        'apply_all' => 'Apply all',
        'translate_all_missing' => 'Translate all missing',
        'translate_from' => 'Translate from :language',
        'missing_translation' => 'Missing translation',
        'source' => 'Source: :field',
        'select_placeholder' => 'Select...',
        'image_file' => 'Image file',
        // User-facing error banners (HTTP failure + network error).
        'error_http' => 'Generation failed (HTTP :status).',
        'error_network' => 'Generation failed: network error.',
        'classify_error_http' => 'Classification failed (HTTP :status).',
        'classify_error_none' => 'Could not classify.',
        'classify_error_network' => 'Classification failed: network error.',
        'extract_error_http' => 'Extraction failed (HTTP :status).',
        'extract_error_invalid' => 'Extraction failed: invalid response body.',
        'extract_error_network' => 'Extraction failed: network error.',
        'analyze_error_http' => 'Analysis failed (HTTP :status).',
        'analyze_error_invalid' => 'Analysis failed: invalid response body.',
        'analyze_error_network' => 'Analysis failed: network error.',
        'translate_error_http' => 'Translation failed (HTTP :status).',
        'translate_error_invalid' => 'Translation failed: invalid response body.',
        'translate_error_network' => 'Translation failed: network error.',
        // sr-only / aria live-region status announcements.
        'status_generating' => 'Generating',
        'status_classifying' => 'Classifying',
        'status_extracting' => 'Extracting',
        'status_analyzing' => 'Analyzing',
        'status_translating' => 'Translating',
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
    // Accessible names + visible chrome for the rich-content editing surface
    // shipped by @arqel-dev/fields-advanced (Markdown / Repeater / Wizard /
    // Builder / RichText). Values must equal the original English literals so
    // accessible names stay stable for screen readers and consumer tests.
    'fields_advanced' => [
        'markdown_formatting' => 'Markdown formatting',
        'markdown_bold' => 'Bold',
        'markdown_italic' => 'Italic',
        'markdown_heading' => 'Heading',
        'markdown_code' => 'Inline code',
        'markdown_link' => 'Link',
        'markdown_list' => 'List',
        'markdown_preview_open' => 'Open preview',
        'markdown_editor_mode' => 'Editor mode',
        'markdown_preview' => 'Markdown preview',
        'repeater_move_up' => 'Move up',
        'repeater_move_down' => 'Move down',
        'repeater_add_item' => 'Add item',
        'wizard_back' => 'Back',
        'wizard_submit' => 'Submit',
        'wizard_next' => 'Next',
        'builder_close_picker' => 'Close block picker',
        'builder_add_block' => 'Add block',
        'richtext_toolbar' => 'Formatting toolbar',
    ],
    // Visible chrome + accessible names for @arqel-dev/versioning's
    // <VersionTimeline>. :id / :user / :relative / :summary feed the per-item
    // accessible name; keep wording matching the original English literals.
    'versioning' => [
        'initial' => 'Initial',
        'compare' => 'Compare',
        'restore' => 'Restore',
        'empty' => 'No versions yet.',
        'loading' => 'Loading versions',
        'history' => 'Version history',
        'item_label' => 'Version :id by :user, :relative: :summary',
    ],
];
