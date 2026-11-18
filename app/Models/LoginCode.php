<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LoginCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-time code emailed to a user to sign in without a password.
 *
 * Only a keyed hash of the code is stored. A user has at most one code:
 * asking for a new one removes the earlier ones.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['user_id', 'code_hash', 'attempts', 'expires_at'])]
class LoginCode extends Model
{
    /** @use HasFactory<LoginCodeFactory> */
    use HasFactory;

    public const int LENGTH = 6;

    public const int MINUTES_VALID = 15;

    public const int MAX_ATTEMPTS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Replace the user's codes with a fresh one and return it in plain
     * text, which is the only time it is known.
     */
    public static function issueFor(User $user): string
    {
        $code = str_pad((string) random_int(0, (10 ** self::LENGTH) - 1), self::LENGTH, '0', STR_PAD_LEFT);

        self::query()->where('user_id', $user->id)->delete();
        self::query()->create([
            'user_id' => $user->id,
            'code_hash' => self::hash($user->id, $code),
            'expires_at' => now()->addMinutes(self::MINUTES_VALID),
        ]);

        return $code;
    }

    /**
     * Use up the user's code. Every call counts as an attempt, whether or
     * not the code is right, and a right code works exactly once.
     */
    public static function redeem(User $user, string $code): bool
    {
        $loginCode = self::query()->where('user_id', $user->id)->latest('id')->first();

        if ($loginCode === null || $loginCode->expires_at->isPast()) {
            return false;
        }

        // Counting before comparing keeps parallel guesses within the limit.
        $counted = self::query()->whereKey($loginCode->id)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->increment('attempts');

        if ($counted === 0 || ! hash_equals($loginCode->code_hash, self::hash($user->id, $code))) {
            return false;
        }

        return self::query()->whereKey($loginCode->id)->delete() === 1;
    }

    /**
     * A six-digit code is too short for a plain or slow hash to protect
     * it, so it is keyed with the application key and bound to the user.
     */
    public static function hash(int $userId, string $code): string
    {
        return hash_hmac('sha256', $userId.'|'.$code, (string) config('app.key'));
    }
}
