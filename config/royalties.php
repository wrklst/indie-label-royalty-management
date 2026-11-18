<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Release file uploads
    |--------------------------------------------------------------------------
    |
    | The largest single file accepted for a release, in kilobytes. Audio
    | masters are big: the server's own upload_max_filesize and
    | post_max_size must allow at least as much.
    |
    */

    'uploads' => [
        'max_kilobytes' => (int) env('RELEASE_UPLOAD_MAX_KB', 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction categories
    |--------------------------------------------------------------------------
    |
    | The categories a transaction can be filed under, grouped by transaction
    | type. They mirror the lists used by Infinite Catalog so existing data
    | can be migrated without remapping.
    |
    */

    'categories' => [
        'income' => [
            '3rd Party License', 'Album License', 'Artist Copies', 'Direct to Fan/Show', 'Direct to Fan/Webstore',
            'Distribution', 'Grant Funding', 'Master Use License', 'Master/Sync License (all in)', 'PPD',
            'Publishing Royalties', 'Publishing Sync License', 'Refund', 'Reserves', 'Rights', 'Sample Usage License',
            'Sub-Label', 'Ticket Sales/Show Fee', 'Wholesale',
        ],
        'expense' => [
            'Advertising', 'Artwork/Design', 'Bio', 'Distribution Fee', 'Event', 'Fee', 'License Fee',
            'Manufacturing (CD)', 'Manufacturing (LP)', 'Manufacturing (Misc.)', 'Marketing', 'Mastering', 'Mixing',
            'Photos', 'PR', 'Radio', 'Recording - Mixing', 'Recording - Musician', 'Recording - Producer',
            'Recording - Studio', 'Remix', 'Return Processing Fee', 'Rights', 'Shipping/Freight', 'Storage Fee',
            'Studio', 'Ticket Buy', 'Tour Support', 'Video',
        ],
        'transfer' => [
            'Advance', 'Artist Merch Given', 'Artist Payback to Label', 'Event', 'Legal Services', 'Mastering',
            'Mechanicals', 'Payout', 'Previous Balance', 'Studio', 'Tour Support',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formats
    |--------------------------------------------------------------------------
    |
    | Every format belongs to one primary group. Items are matched to
    | transactions by release, track and format group.
    |
    */

    'formats' => [
        'Digital' => [
            'Ad-Supported Audio Stream', 'Ad-Supported Video Stream', 'Audiobook (Digital)', 'Background',
            'Breakage (Digital)', 'Cloud/Locker', 'Creation', 'Digital/Other', 'Download Album', 'Download Track',
            'Download/Other', 'eBook', 'Non-interactive Radio', 'Radio/Other', 'Streaming/Other',
            'Subscription Audio Stream', 'Subscription Video Stream', 'User Generated Content (UGC)', 'Video/Other',
        ],
        'Physical' => [
            '10" LP', '2xLP', '7" Single', 'Audiobook (Physical)', 'Book', 'Box Set', 'Bundle (Physical)', 'CD', 'DVD',
            'Cassette', 'Flexidisc', 'Hardback', 'LP', 'Merchandise', 'Paperback', 'Physical/Other', 'Poster', 'T-Shirt',
        ],
        'Rights' => [
            'Performance Rights', 'Neighboring Rights', 'Rights/Other', 'Publishing Rights', 'Mechanical Rights', 'Sync',
            'Blanket License',
        ],
        'Publishing' => ['Mechanical', 'Performance', 'Publishing/Other', 'Print'],
        'Reserves' => ['Reserves (Held)', 'Reserves (Liquidated)'],
    ],

];
