<?php

declare(strict_types=1);

namespace Arqel\Realtime\Collab;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Eloquent model que persiste o snapshot binário de um Y.Doc
 * (collaborative editing scaffold do RT-005).
 *
 * Cada linha representa o estado consolidado de um único campo
 * `field` do model `(model_type, model_id)`. O `state` é o blob
 * binário produzido pelo cliente Yjs (`Y.encodeStateAsUpdate`),
 * persistido como `longBlob` para tolerar documentos grandes.
 *
 * Concurrência é gerida via `version` (optimistic): o cliente envia
 * a versão que esperava ver no servidor; a request é rejeitada com
 * 409 quando o servidor já avançou. Última escrita conhecida em
 * `last_user_id` (nullable — guests/CLI permitidos).
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $field
 * @property string|null $state
 * @property int $version
 * @property int|null $last_user_id
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class YjsDocument extends Model
{
    public $timestamps = false;

    protected $table = 'arqel_yjs_documents';

    protected $fillable = [
        'model_type',
        'model_id',
        'field',
        'state',
        'version',
        'last_user_id',
        'updated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'last_user_id' => 'integer',
        'updated_at' => 'datetime',
    ];

    /**
     * Relação polimórfica para o model "dono" do documento.
     */
    public function morphedModel(): MorphTo
    {
        return $this->morphTo(name: 'model', type: 'model_type', id: 'model_id');
    }
}
