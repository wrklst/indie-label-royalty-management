<?php

namespace App\Services\Royalties;

use App\Support\Decimal;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The European Central Bank's reference rate of a day, for amounts in
 * another currency that come without a rate. It is what the market
 * paid, not what a bank or card charged: a rate typed in always wins.
 *
 * @see https://frankfurter.dev  Serves the ECB's rates; no account, and nothing but the two currencies and the day is sent.
 */
class ExchangeRates
{
    private const string URL = 'https://api.frankfurter.dev/v1/';

    /**
     * What one unit of a currency was worth in another on a day; on a
     * weekend or holiday, on the last day before it. Null when the
     * rate cannot be had, and the amount then stays uncounted.
     *
     * @return numeric-string|null
     */
    public function rate(string $from, string $to, DateTimeInterface|string $on): ?string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return '1';
        }

        $today = CarbonImmutable::today();
        $day = CarbonImmutable::parse($on)->startOfDay()->min($today);
        $key = "exchange-rate:{$from}:{$to}:{$day->toDateString()}";
        $known = Decimal::parse(Cache::get($key));

        if ($known !== null) {
            return $known;
        }

        try {
            $rate = Decimal::parse(Http::timeout(5)->get(self::URL.$day->toDateString(), ['base' => $from, 'symbols' => $to])->json("rates.{$to}"));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($rate === null || Decimal::parse($rate) <= 0) {
            return null;
        }

        // A past day is final; today's rate is published in the afternoon.
        Cache::put($key, $rate, $day->lessThan($today) ? null : now()->addHours(3));

        return $rate;
    }

    /**
     * The rate an amount is converted with: 1 in the label's own
     * currency, the one typed in, or else the reference rate of the day.
     *
     * @return numeric-string|null
     */
    public function for(string $currency, string $labelCurrency, mixed $typed, DateTimeInterface|string|null $on): ?string
    {
        if (strtoupper($currency) === strtoupper($labelCurrency)) {
            return '1';
        }

        return Decimal::parse($typed) ?? ($on === null ? null : $this->rate($currency, $labelCurrency, $on));
    }
}
