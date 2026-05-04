<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
final class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description'];
}
