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
    | Playback copies of audio
    |--------------------------------------------------------------------------
    |
    | Uploaded masters are kept untouched; for listening in the browser
    | ffmpeg (built with libmp3lame) makes an MP3 of each. The binary is
    | looked up on the PATH unless a full path is given. The bitrate is in
    | kbit/s (constant, stereo), the sample rate in Hz, and the timeout is
    | how many seconds one conversion may take.
    |
    | Conversions are queued and worked off by Horizon. Where no worker
    | runs, set "queue" to false: a master is then converted in the web
    | process right after the upload has been answered.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Horizon dashboard
    |--------------------------------------------------------------------------
    |
    | The email addresses of the users who may open /horizon outside the
    | local environment, separated by commas.
    |
    */

    'horizon_emails' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('HORIZON_ALLOWED_EMAILS', '')),
    ))),

    'audio' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'bitrate' => (int) env('AUDIO_PREVIEW_BITRATE', 320),
        'sample_rate' => (int) env('AUDIO_PREVIEW_SAMPLE_RATE', 44100),
        'timeout' => (int) env('AUDIO_PREVIEW_TIMEOUT', 600),
        'queue' => (bool) env('AUDIO_PREVIEW_QUEUE', true),
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
            'Distribution', 'Grant Funding', 'Master Use License', 'Master/Sync License (all in)', 'Mechanicals', 'PPD',
            'Publishing Royalties', 'Publishing Sync License', 'Refund', 'Reserves', 'Rights', 'Sample Usage License',
            'Sub-Label', 'Ticket Sales/Show Fee', 'Wholesale',
        ],
        'expense' => [
            'Advertising', 'Artwork/Design', 'Bio', 'Distribution Fee', 'Event', 'Fee', 'License Fee',
            'Manufacturing (CD)', 'Manufacturing (LP)', 'Manufacturing (Misc.)', 'Marketing', 'Mastering', 'Mechanicals',
            'Mixing',
            'Photos', 'PR', 'Radio', 'Recording - Mixing', 'Recording - Musician', 'Recording - Producer',
            'Recording - Studio', 'Remix', 'Return Processing Fee', 'Rights', 'Shipping/Freight', 'Storage Fee',
            'Studio', 'Ticket Buy', 'Tour Support', 'Video',
        ],
        // Running costs of the label itself, not tied to a release.
        'overhead' => [
            'Subscriptions', 'Software', 'Hosting', 'Accounting', 'Legal', 'Bank Fees', 'Insurance', 'Office', 'Travel',
            'Marketing', 'Fee', 'Other',
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

    /*
    |--------------------------------------------------------------------------
    | Mechanicals tool
    |--------------------------------------------------------------------------
    |
    | The US statutory mechanical rates for physical copies and permanent
    | downloads, in US dollars per unit: a flat rate per work for
    | recordings of up to five minutes and a rate per started minute for
    | longer ones. A run of the tool picks one table. The download formats
    | are the ones counted as units; physical formats are added when the
    | label asks for it.
    |
    */

    'mechanicals' => [
        'statutory_rates' => [
            '2026' => ['per_work' => '0.131', 'per_minute' => '0.0252'],
            '2025' => ['per_work' => '0.127', 'per_minute' => '0.0245'],
            '2024' => ['per_work' => '0.124', 'per_minute' => '0.0239'],
            '2023' => ['per_work' => '0.12', 'per_minute' => '0.0231'],
            'pre-2023' => ['per_work' => '0.091', 'per_minute' => '0.0175'],
        ],
        'download_formats' => ['Download Album', 'Download Track', 'Download/Other'],
    ],

];
