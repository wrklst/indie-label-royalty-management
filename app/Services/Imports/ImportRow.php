<?php

namespace App\Services\Imports;

use App\Enums\TransactionType;

/**
 * One statement line normalised into the shape of a transaction.
 */
class ImportRow
{
    /**
     * @param  string|null  $gross  What customers paid before anyone took a cut, when the statement says.
     * @param  string|null  $distributionFee  What the distributor, shop or agency kept of it.
     * @param  string|null  $paymentFee  What the payment provider kept of it.
     * @param  array<string, mixed>  $raw  The source row, stripped of personal data.
     */
    public function __construct(
        public string $total,
        public TransactionType $type = TransactionType::Income,
        public ?string $timestamp = null,
        public ?string $paidTimestamp = null,
        public ?string $currency = null,
        public ?string $quantity = null,
        public ?string $price = null,
        public ?string $category = null,
        public ?string $format = null,
        public ?string $usageType = null,
        public ?string $region = null,
        public ?string $provider = null,
        public ?string $retailer = null,
        public ?string $externalId = null,
        public ?string $source = null,
        public ?string $memo = null,
        public ?string $artist = null,
        public ?string $releaseArtist = null,
        public ?string $releaseName = null,
        public ?string $trackName = null,
        public ?string $catalogNumber = null,
        public ?string $upc = null,
        public ?string $isrc = null,
        public ?string $payeeName = null,
        public ?int $itemId = null,
        public ?int $contractId = null,
        public array $raw = [],
        public ?string $gross = null,
        public ?string $distributionFee = null,
        public ?string $paymentFee = null,
    ) {}
}
