<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Post;
use App\States\PostStates;

/**
 * PostResource — showcase de integração com fields, AI, workflow e versioning.
 *
 * Em produção este recurso estende `Arqel\Core\Resources\Resource` e usa o
 * factory de fields (`Field::text()`, `Field::aiText()`, etc.). A definição
 * abaixo é a forma declarativa que o painel `admin` consome.
 */
final class PostResource
{
    public static string $model = Post::class;

    public static string $slug = 'posts';

    public static string $label = 'Post';

    public static string $pluralLabel = 'Posts';

    /**
     * Definição declarativa de fields. Cada entry mapeia para um Field do
     * pacote `arqel-dev/fields`. Strings de tipo refletem a API do FieldFactory.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fields(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'type' => 'slug', 'fromField' => 'title'],
            ['name' => 'summary', 'type' => 'aiText', 'prompt' => 'Summarize the post in 2 sentences'],
            ['name' => 'body', 'type' => 'richText'],
            ['name' => 'state', 'type' => 'stateTransition', 'transitions' => PostStates::transitions()],
            ['name' => 'published_at', 'type' => 'dateTime'],
            ['name' => 'author_id', 'type' => 'belongsTo', 'resource' => UserResource::class],
        ];
    }
}
