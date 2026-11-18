<?php

namespace App\Models;

use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'currency', 'contact_email', 'logo_path', 'accounts_mechanicals', 'accounts_ppd', 'accounts_reserves', 'importer_settings'])]
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accounts_mechanicals' => 'boolean',
            'accounts_ppd' => 'boolean',
            'accounts_reserves' => 'boolean',
            'importer_settings' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'payee_id'])->withTimestamps();
    }

    /**
     * Read an importer setting, e.g. "bandcamp.disable_fee".
     */
    public function importerSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->importer_settings, $key, $default);
    }
}
