<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BandcampConnectRequest;
use App\Http\Requests\Settings\BandcampSettingsRequest;
use App\Models\BandcampConnection;
use App\Services\Bandcamp\BandcampClient;
use App\Services\Bandcamp\BandcampException;
use App\Services\Bandcamp\BandcampSync;
use App\Support\CurrentLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BandcampController extends Controller
{
    /** Seconds a sync started from the page keeps fetching further months. */
    private const int SYNC_TIME_BUDGET = 20;

    /**
     * The page never receives the client id, the secret or a token, only
     * whether credentials are stored.
     */
    public function edit(): Response
    {
        $connection = BandcampConnection::query()->first();

        return Inertia::render('settings/bandcamp', [
            'connection' => $connection === null ? null : [
                // Enough of the client ID to tell which client is stored; the secret is never sent back.
                'client_id_hint' => Str::mask($connection->client_id, '•', 0, max(0, mb_strlen($connection->client_id) - 2)),
                'bands' => $connection->bands ?? [],
                'band_id' => $connection->band_id,
                'member_band_id' => $connection->member_band_id,
                'enabled' => $connection->enabled,
                'sync_from' => $connection->sync_from?->toDateString(),
                'last_synced_until' => $connection->last_synced_until?->toIso8601String(),
                'last_sync_at' => $connection->last_sync_at?->toIso8601String(),
                'last_sync_status' => $connection->last_sync_status,
                'last_sync_message' => $connection->last_sync_message,
            ],
        ]);
    }

    /**
     * Store the credentials, prove them against Bandcamp and load the
     * accounts they can act for.
     */
    public function connect(BandcampConnectRequest $request, CurrentLabel $currentLabel): RedirectResponse
    {
        $credentials = $request->safe()->only(['client_id', 'client_secret']);
        $connection = BandcampConnection::query()->first();
        $previous = $connection?->only(['client_id', 'client_secret', 'refresh_token', 'access_token', 'access_token_expires_at']);

        if ($connection === null) {
            $connection = BandcampConnection::query()->create([...$credentials, 'label_id' => $currentLabel->id()]);
        } elseif ($connection->client_id !== $credentials['client_id'] || $connection->client_secret !== $credentials['client_secret']) {
            // Tokens belong to the client they were issued for.
            $connection->update([...$credentials, 'refresh_token' => null, 'access_token' => null, 'access_token_expires_at' => null]);
        }

        try {
            $this->loadBands($connection);
        } catch (BandcampException $exception) {
            // Without a token nothing was gained from Bandcamp, so the page
            // goes back to what it was. With a token the credentials are
            // proven and must be kept: the grant cannot be obtained twice.
            if ($connection->refresh()->refresh_token === null) {
                $previous === null ? $connection->delete() : $connection->update($previous);
            }

            throw ValidationException::withMessages(['bandcamp' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connected to Bandcamp. Choose the account to import.')]);

        return back();
    }

    /**
     * Load the list of accounts again with the stored credentials.
     */
    public function bands(): RedirectResponse
    {
        $connection = BandcampConnection::query()->firstOrFail();

        try {
            $this->loadBands($connection);
        } catch (BandcampException $exception) {
            throw ValidationException::withMessages(['bandcamp' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Accounts loaded from Bandcamp.')]);

        return back();
    }

    public function update(BandcampSettingsRequest $request): RedirectResponse
    {
        $connection = BandcampConnection::query()->firstOrFail();

        $connection->fill([
            ...$request->safe()->only(['band_id', 'member_band_id', 'sync_from']),
            'enabled' => $request->boolean('enabled'),
        ]);

        // Another account or an earlier start means fetching from the
        // start date again. Sales that are in already are not doubled.
        if ($connection->isDirty(['band_id', 'member_band_id', 'sync_from'])) {
            $connection->last_synced_until = null;
        }

        $connection->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Bandcamp settings saved.')]);

        return back();
    }

    /**
     * Fetch and import new sales now, within the request.
     */
    public function sync(Request $request, BandcampSync $sync): RedirectResponse
    {
        $connection = BandcampConnection::query()->firstOrFail();

        try {
            $result = $sync->run($connection, $request->user()?->id, self::SYNC_TIME_BUDGET);
        } catch (BandcampException $exception) {
            throw ValidationException::withMessages(['bandcamp' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $result->summary()]);

        return back();
    }

    /**
     * Delete the credentials and tokens. Imported sales stay.
     */
    public function destroy(): RedirectResponse
    {
        BandcampConnection::query()->firstOrFail()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Disconnected from Bandcamp. The stored credentials were deleted.')]);

        return back();
    }

    /**
     * Fetch the accounts and drop a selection Bandcamp no longer lists.
     */
    private function loadBands(BandcampConnection $connection): void
    {
        $bands = (new BandcampClient($connection))->bands();
        $band = collect($bands)->firstWhere('band_id', $connection->band_id);
        $member = collect($band['member_bands'] ?? [])->firstWhere('band_id', $connection->member_band_id);

        $connection->update([
            'bands' => $bands,
            'band_id' => $band === null ? null : $connection->band_id,
            'member_band_id' => $member === null ? null : $connection->member_band_id,
            'enabled' => $band !== null && $connection->enabled,
        ]);
    }
}
