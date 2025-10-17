<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Language
 *
 * @property int $id
 * @property string $code
 * @property string $name
 */
class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
