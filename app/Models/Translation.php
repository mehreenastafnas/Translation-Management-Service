<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Translation
 *
 * @property int $id
 * @property string $key
 * @property int $language_id
 * @property string $content
 * @property string|null $context
 */
class Translation extends Model
{
    protected $fillable = [
        'key',
        'language_id',
        'content',
        'context',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_translation');
    }
}
