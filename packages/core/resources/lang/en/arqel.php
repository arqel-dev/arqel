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
            'browse' => 'Browse',
            'choose_another' => 'Choose another file',
            'drop_hint' => 'Drag a file here or click to browse',
        ],
        'image' => [
            'preview_alt' => 'Preview',
            'choose' => 'Choose image',
            'replace' => 'Replace image',
        ],
        'password' => [
            'show' => 'Show password',
            'hide' => 'Hide password',
        ],
        'belongsto' => [
            'search' => 'Search :resource…',
        ],
        'multiselect' => [
            'remove' => 'Remove :label',
        ],
        'has_many_empty' => 'No :resource linked.',
    ],
    'locale' => [
        'switch' => 'Language',
        'en' => 'English',
        'pt_BR' => 'Português (Brasil)',
    ],
    // Visible copy for @arqel-dev/realtime's <ConnectionStatusBanner>
    // (role=status, aria-live=polite). Shown on every WebSocket
    // disconnect/failed state; keep wording matching the React fallbacks.
    'realtime' => [
        'connecting' => 'Connecting...',
        'disconnected' => 'Connection lost. Reconnecting...',
        'failed' => 'Connection failed. Refresh page.',
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
    // aria-label + title tooltip for @arqel-dev/theme's <ThemeToggle>, which
    // cycles system → light → dark. Each value describes the CURRENT theme and
    // the theme reached on the next click. Resolved client-side via
    // useArqelTranslations() with the English literal as fallback.
    'theme' => [
        'toggle' => [
            'system' => 'Theme: system (click for light)',
            'light' => 'Theme: light (click for dark)',
            'dark' => 'Theme: dark (click for system)',
        ],
    ],
    // Visible + focusable label for @arqel-dev/a11y's <SkipLink>. The default
    // skip-to-content link text; a `label` prop still overrides it. Resolved
    // client-side via useArqelTranslations() with the English literal as
    // fallback.
    'a11y' => [
        'skip_to_content' => 'Skip to main content',
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
        // Client-side validation + configuration error banners.
        'file_too_large' => 'File too large: :size (max :max).',
        'missing_translate_url' => 'Missing translate URL: provide `translateUrl` or both `resource` and `field`.',
        'missing_classify_url' => 'Missing classify URL: provide `classifyUrl` or both `resource` and `field`.',
        'classify_no_context_tooltip' => 'No context fields configured. Add `classifyFromFields` to enable AI classification.',
        'missing_generate_url' => 'Missing generate URL: provide `generateUrl` or both `resource` and `field`.',
        'missing_extract_url' => 'Missing extract URL: provide `extractUrl` or both `resource` and `field`.',
        'missing_analyze_url' => 'Missing analyze URL: provide `analyzeUrl` or both `resource` and `field`.',
        'selected_preview_alt' => 'Selected preview',
        // sr-only / aria live-region status announcements.
        'status_generating' => 'Generating',
        'status_classifying' => 'Classifying',
        'status_extracting' => 'Extracting',
        'status_analyzing' => 'Analyzing',
        'status_translating' => 'Translating',
        'translate_textarea_aria' => 'Translation in :language',
        // Provenance badge shown after a classification (role="status").
        'suggestion_ai' => 'Suggested by AI',
        'suggestion_fallback' => 'Used fallback',
        // Buttons rendered next to the provenance badge after a suggestion.
        'suggestion_accept' => 'Accept',
        'suggestion_pick_another' => 'Pick another',
    ],
    // Visible group headings rendered in the <CommandPalette> listbox when a
    // command has no explicit category, plus the synthetic "Recent" bucket.
    // Resolved client-side via useArqelTranslations() with the English literal
    // as fallback.
    'command_palette' => [
        'category_general' => 'General',
        'category_recent' => 'Recent',
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
    // Visible keyboard-hint labels in the <CommandPalette> footer. The glyph
    // (↑↓ / ↵ / esc) is rendered by the component; only the verb is localized.
    // Resolved client-side via useArqelTranslations() with the English literal
    // as fallback.
    'palette' => [
        'hint_navigate' => '↑↓ navigate',
        'hint_select' => '↵ select',
        'hint_close' => 'esc close',
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
        'markdown_preview_label' => 'Preview',
        'markdown_enter_fullscreen' => 'Enter fullscreen',
        'markdown_exit_fullscreen' => 'Exit fullscreen',
        'markdown_fullscreen_short' => 'Full',
        'markdown_exit_short' => 'Exit',
        'markdown_editor_mode' => 'Editor mode',
        'markdown_tab_edit' => 'Edit',
        'markdown_tab_preview' => 'Preview',
        'markdown_close' => 'Close',
        'markdown_preview' => 'Markdown preview',
        'repeater_move_up' => 'Move up',
        'repeater_move_down' => 'Move down',
        'repeater_add_item' => 'Add item',
        'repeater_add_item_label' => '+ Add item',
        'repeater_drag' => 'Drag to reorder item :number',
        'repeater_expand' => 'Expand item :number',
        'repeater_collapse' => 'Collapse item :number',
        'repeater_clone' => 'Clone item :number',
        'repeater_remove' => 'Remove item :number',
        'wizard_back' => 'Back',
        'wizard_submit' => 'Submit',
        'wizard_next' => 'Next',
        'wizard_step_label' => 'Step :number: :label',
        'wizard_progress' => 'Step :number of :total: :label',
        'wizard_no_fields' => 'This step has no fields.',
        'wizard_empty' => 'No wizard steps configured.',
        'builder_close_picker' => 'Close block picker',
        'builder_add_block' => 'Add block',
        'builder_add_block_label' => '+ Add block',
        'builder_remove_block' => 'Remove block :number',
        'builder_drag_reorder' => 'Drag to reorder block :number',
        'builder_expand_block' => 'Expand block :number',
        'builder_collapse_block' => 'Collapse block :number',
        'builder_move_block_up' => 'Move block :number up',
        'builder_move_block_down' => 'Move block :number down',
        'builder_clone_block' => 'Clone block :number',
        'keyvalue_key' => 'Key',
        'keyvalue_value' => 'Value',
        'keyvalue_add_row' => 'Add :key / :value row',
        'keyvalue_add_row_label' => '+ Add row',
        'keyvalue_remove_row' => 'Remove row :number',
        'tags_remove' => 'Remove tag :tag',
        'code_enter_fullscreen' => 'Enter fullscreen',
        'code_exit_fullscreen' => 'Exit fullscreen',
        'code_fullscreen_short' => 'Full',
        'code_exit_short' => 'Exit',
        'code_language_plaintext' => 'Plain text',
        'richtext_toolbar' => 'Formatting toolbar',
        'richtext_over_limit' => 'Content exceeds the maximum length of :max characters.',
        'richtext_bold' => 'Bold',
        'richtext_italic' => 'Italic',
        'richtext_underline' => 'Underline',
        'richtext_strike' => 'Strikethrough',
        'richtext_h1' => 'Heading 1',
        'richtext_h2' => 'Heading 2',
        'richtext_h3' => 'Heading 3',
        'richtext_ul' => 'Bullet list',
        'richtext_ol' => 'Numbered list',
        'richtext_blockquote' => 'Blockquote',
        'richtext_link' => 'Link',
        'richtext_code' => 'Inline code',
        'richtext_image' => 'Image',
        'richtext_image_disabled' => 'Image upload not configured',
        'richtext_placeholder' => 'Start writing...',
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
        // Visible chrome + accessible names for <VersionDiff>. Values must
        // equal the original English literals so accessible names stay stable.
        'modified' => 'Modified',
        'no_changes' => 'No changes to display.',
        'field_comparison' => 'Field comparison',
        'no_previous_value' => 'no previous value',
        'no_new_value' => 'no new value',
        // Localized changes_summary built by VersionPresenter::summarize().
        // `changed` is plural-aware (trans_choice): :count = field count,
        // :fields = comma-joined field names. Values must equal the original
        // English literals so existing payload consumers stay stable.
        'summary' => [
            'created' => 'Created',
            'no_changes' => 'No changes',
            'changed' => '{1}Changed :count field: :fields|[2,*]Changed :count fields: :fields',
        ],
    ],
    // Visible empty-state chrome for @arqel-dev/workflow's <StateTransition>.
    // Values must equal the original English literals so consumer tests and
    // accessible names stay stable.
    'workflow' => [
        'no_state_assigned' => 'No state assigned.',
        'no_transitions' => 'No transitions available.',
    ],
    // Localized validation chrome for @arqel-dev/marketplace plugin submission
    // (SubmitPluginRequest). `attributes` replaces the raw snake_case field
    // names in :attribute placeholders; `messages` localizes rule failures
    // independent of the host application's validation lines.
    'marketplace' => [
        'attributes' => [
            'slug' => 'slug',
            'composer_package' => 'Composer package',
            'npm_package' => 'npm package',
            'github_url' => 'GitHub URL',
            'type' => 'type',
            'name' => 'name',
            'description' => 'description',
            'screenshots' => 'screenshots',
            'license' => 'license',
        ],
        'messages' => [
            'slug_regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'composer_package_regex' => 'The Composer package must follow the vendor/name format.',
            'type_in' => 'The selected type is invalid.',
        ],
    ],
];
