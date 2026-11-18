<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const BANDCAMP_TOKEN_URL = 'https://bandcamp.com/oauth_token';
const BANDCAMP_BANDS_URL = 'https://bandcamp.com/api/account/1/my_bands';
const BANDCAMP_SALES_URL = 'https://bandcamp.com/api/sales/4/sales_report';

/**
 * A sales report line the way the Bandcamp API sends it, buyer included.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bandcampSale(array $overrides = []): array
{
    return [
        'bandcamp_transaction_id' => 900000001,
        'bandcamp_transaction_item_id' => 700000001,
        'bandcamp_related_transaction_id' => null,
        'date' => '04 Feb 2026 23:22:11 GMT',
        'paid_to' => 'label@example.com',
        'item_type' => 'album',
        'item_name' => 'Example Album',
        'artist' => 'Example Artist',
        'currency' => 'EUR',
        'item_price' => 10.0,
        'quantity' => 1,
        'discount_code' => null,
        'sub_total' => 10.0,
        'transaction_fee' => 0.5,
        'fee_type' => 'paypal',
        'item_total' => 10.0,
        'amount_you_received' => 9.5,
        'paypal_transaction_id' => 'PAYPAL-EXAMPLE-1',
        'net_amount' => 8.5,
        'package' => 'digital download',
        'option' => null,
        'item_url' => 'https://example-records.bandcamp.com/album/example-album',
        'catalog_number' => 'EX001',
        'upc' => '4000000000017',
        'isrc' => '',
        'buyer_name' => 'Jane Example',
        'buyer_email' => 'jane@example.com',
        'buyer_phone' => '+43 555 0100',
        'buyer_note' => 'Greetings from Jane',
        'ship_to_name' => 'Jane Example',
        'ship_to_street' => 'Example Street 1',
        'ship_to_street_2' => 'Top 5',
        'ship_to_city' => 'Vienna',
        'ship_to_state' => 'Wien',
        'ship_to_zip' => '1010',
        'ship_to_country' => 'Austria',
        'ship_to_country_code' => 'AT',
        'ship_date' => null,
        'ship_notes' => 'Ring twice',
        'country' => 'Austria',
        'country_code' => 'AT',
        'region_or_state' => 'Wien',
        'city' => 'Vienna',
        'referer' => 'Bandcamp search',
        'referer_url' => 'bandcamp.com/search',
        'sku' => null,
        ...$overrides,
    ];
}

/**
 * Answer every sales report request from a map of "start_time" => lines.
 * A window that is not in the map has no sales.
 *
 * @param  array<string, list<array<string, mixed>>>  $windows
 */
function fakeBandcampSales(array $windows): void
{
    Http::fake([
        BANDCAMP_SALES_URL => fn (Request $request) => Http::response(['report' => $windows[$request['start_time']] ?? []]),
    ]);
}
