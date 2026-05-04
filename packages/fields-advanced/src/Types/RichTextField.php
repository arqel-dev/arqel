<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;

/**
 * Rich-text editor field powered by Tiptap on the React side
 * (`@arqel-dev/fields-advanced/RichTextInput.tsx`, shipped in a future ticket).
 *
 * The PHP side is configuration-only: it advertises the toolbar layout,
 * the optional image-upload disk/directory, the maximum length, the
 * file-attachment toggle, custom Tiptap mark names and a list of
 * mentionable users for `@mentions`. The React component reads those
 * props verbatim and wires Tiptap accordingly.
 *
 * **Server-side sanitisation is the consumer's responsibility.** This
 * field intentionally does NOT pull in `ezyang/htmlpurifier` or any
 * other HTML sanitiser as a hard dependency — sanitisation is a
 * write-time concern (FormRequest rules, Eloquent mutators, or the
 * sanitizer trait scheduled for FIELDS-ADV-002-followup). Treat any
 * value submitted from the editor as untrusted HTML and purify it at
 * the boundary before persisting.
 */
final class RichTextField extends Field
{
    /** @var list<string> */
    public const DEFAULT_TOOLBAR = [
        'bold',
        'italic',
        'link',
        'bulletList',
        'orderedList',
        'heading',
        'blockquote',
    ];

    public const DEFAULT_MAX_LENGTH = 65535;

    protected string $type = 'richText';

    protected string $component = 'RichTextInput';

    /** @var list<string> */
    protected array $toolbar = self::DEFAULT_TOOLBAR;

    protected ?string $imageUploadDisk = null;

    protected ?string $imageUploadDirectory = null;

    protected int $maxLength = self::DEFAULT_MAX_LENGTH;

    protected bool $fileAttachments = false;

    /** @var list<string> */
    protected array $customMarks = [];

    /** @var list<array{id: int|string, name: string, avatar?: string}> */
    protected array $mentionable = [];

    /**
     * Replace the toolbar buttons. Non-string entries are dropped
     * silently so misconfigured arrays never reach the React side.
     *
     * @param array<int|string, mixed> $buttons
     */
    public function toolbar(array $buttons): static
    {
        $this->toolbar = array_values(array_filter(
            $buttons,
            static fn ($button): bool => is_string($button),
        ));

        return $this;
    }

    public function imageUploadDisk(string $disk): static
    {
        $this->imageUploadDisk = $disk;

        return $this;
    }

    public function imageUploadDirectory(string $dir): static
    {
        $this->imageUploadDirectory = $dir;

        return $this;
    }

    /**
     * Clamp to ≥1 so the React side never receives a non-positive
     * upper bound that would render the field permanently invalid.
     */
    public function maxLength(int $max): static
    {
        $this->maxLength = max(1, $max);

        return $this;
    }

    public function fileAttachments(bool $enable = true): static
    {
        $this->fileAttachments = $enable;

        return $this;
    }

    /**
     * Whitelist Tiptap mark names. Non-strings are dropped silently.
     *
     * @param array<int|string, mixed> $marks
     */
    public function customMarks(array $marks): static
    {
        $this->customMarks = array_values(array_filter(
            $marks,
            static fn ($mark): bool => is_string($mark),
        ));

        return $this;
    }

    /**
     * Provide the mention pool. Each entry must contain at least
     * `id` (int|string) and `name` (string); entries missing either
     * are dropped silently.
     *
     * @param array<int, array<string, mixed>> $users
     */
    public function mentionable(array $users): static
    {
        $clean = [];

        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }

            if (! array_key_exists('id', $user) || ! array_key_exists('name', $user)) {
                continue;
            }

            $id = $user['id'];
            $name = $user['name'];

            if (! is_int($id) && ! is_string($id)) {
                continue;
            }

            if (! is_string($name)) {
                continue;
            }

            $entry = ['id' => $id, 'name' => $name];

            if (array_key_exists('avatar', $user) && is_string($user['avatar'])) {
                $entry['avatar'] = $user['avatar'];
            }

            $clean[] = $entry;
        }

        $this->mentionable = $clean;

        return $this;
    }

    /**
     * @return array{
     *     toolbar: list<string>,
     *     imageUploadRoute: ?string,
     *     imageUploadDirectory: ?string,
     *     maxLength: int,
     *     fileAttachments: bool,
     *     customMarks: list<string>,
     *     mentionable: list<array{id: int|string, name: string, avatar?: string}>
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'toolbar' => $this->toolbar,
            'imageUploadRoute' => $this->imageUploadDisk === null
                ? null
                : '/arqel-dev/fields/upload?disk='.$this->imageUploadDisk,
            'imageUploadDirectory' => $this->imageUploadDirectory,
            'maxLength' => $this->maxLength,
            'fileAttachments' => $this->fileAttachments,
            'customMarks' => $this->customMarks,
            'mentionable' => $this->mentionable,
        ];
    }
}
