<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Tag
 *
 * @property int $id
 * @property string $name
 */
class Tag extends Model
{
    protected $fillable = [
        'name',
    ];

    public function translations(): BelongsToMany
    {
        return $this->belongsToMany(Translation::class, 'tag_translation');
    }
}
