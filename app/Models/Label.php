<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $currency
 * @property string|null $contact_email
 * @property string|null $logo_path
 * @property bool $accounts_mechanicals
 * @property bool $accounts_ppd
 * @property bool $accounts_reserves
 * @property array<string, mixed>|null $importer_settings
 * @property array<string, mixed>|null $royalty_tool_settings
 * @property list<string>|null $airplay_markets
 * @property string|null $dataforseo_login
 * @property string|null $dataforseo_password
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Pivot&object{role: string, payee_id: int|null} $pivot Membership of the user when loaded through User::labels().
 */
#[Fillable(['name', 'slug', 'currency', 'contact_email', 'logo_path', 'accounts_mechanicals', 'accounts_ppd', 'accounts_reserves', 'importer_settings', 'royalty_tool_settings', 'airplay_markets', 'dataforseo_login', 'dataforseo_password'])]
#[Hidden(['dataforseo_login', 'dataforseo_password'])]
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
            'royalty_tool_settings' => 'array',
            'airplay_markets' => 'array',
            'dataforseo_login' => 'encrypted',
            'dataforseo_password' => 'encrypted',
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
     * The countries an airplay research searches in, as ISO codes.
     *
     * @return non-empty-list<string>
     */
    public function airplayMarkets(): array
    {
        return $this->airplay_markets ?: ['DE', 'GB', 'US'];
    }

    /**
     * Read an importer setting, e.g. "bandcamp.disable_fee".
     */
    public function importerSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->importer_settings, $key, $default);
    }
}
