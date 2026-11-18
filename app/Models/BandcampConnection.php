<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLabel;
use Carbon\CarbonImmutable;
use Database\Factories\BandcampConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The Bandcamp API credentials of a label and the state of its sales sync.
 *
 * Client id, client secret and both tokens are encrypted at rest and must
 * never be sent to the browser.
 *
 * @property int $id
 * @property int $label_id
 * @property string $client_id
 * @property string $client_secret
 * @property string|null $refresh_token
 * @property string|null $access_token
 * @property CarbonImmutable|null $access_token_expires_at
 * @property list<array{band_id: int, name: string, subdomain: string|null, member_bands: list<array{band_id: int, name: string, subdomain: string|null}>}>|null $bands Accounts the credentials can act for, as last loaded from Bandcamp.
 * @property int|null $band_id
 * @property int|null $member_band_id
 * @property bool $enabled
 * @property CarbonImmutable|null $sync_from First day to fetch sales for.
 * @property CarbonImmutable|null $last_synced_until Sales before this moment (UTC) are imported.
 * @property CarbonImmutable|null $last_sync_at
 * @property string|null $last_sync_status "ok" or "failed".
 * @property string|null $last_sync_message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['label_id', 'client_id', 'client_secret', 'refresh_token', 'access_token', 'access_token_expires_at', 'bands', 'band_id', 'member_band_id', 'enabled', 'sync_from', 'last_synced_until', 'last_sync_at', 'last_sync_status', 'last_sync_message'])]
#[Hidden(['client_id', 'client_secret', 'refresh_token', 'access_token'])]
class BandcampConnection extends Model
{
    /** @use HasFactory<BandcampConnectionFactory> */
    use BelongsToLabel, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'bands' => 'array',
            'band_id' => 'integer',
            'member_band_id' => 'integer',
            'enabled' => 'boolean',
            'sync_from' => 'date:Y-m-d',
            'last_synced_until' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    /**
     * Whether everything needed to fetch sales is in place.
     */
    public function isReadyToSync(): bool
    {
        return $this->band_id !== null && $this->sync_from !== null;
    }

    /**
     * Forget the tokens, e.g. when the client was regenerated on Bandcamp.
     */
    public function forgetTokens(): void
    {
        $this->update(['refresh_token' => null, 'access_token' => null, 'access_token_expires_at' => null]);
    }
}
