# Label Royalties

Royalty accounting and release management for independent record labels.
Import the statements your distributor sends, split the income by contract,
give your artists statements they can read, and keep every release together
with its metadata, cover and audio.

Built with Laravel, Inertia and React.

## What it does

**Royalty accounting**

- Several labels in one installation, each with its own catalog, contracts,
  transactions and team. Label admins see everything; payees only their own
  published statements and payouts.
- Contracts with percentage splits per payee, optional separate ledgers (to
  recoup an advance on its own), territory splits and prorated contracts for
  compilations. A track can have its own contract, e.g. for a remix.
- Income, expenses and transfers (advances, paybacks) at full precision:
  streaming lines worth fractions of a cent are never rounded away.
- Expenses can be recouped from payees or borne by the label alone
  (one-time payments, buy-outs).
- Accounting periods with a check for unassigned income, releases without a
  contract and unconverted currencies; statements as frozen snapshots with
  revisions; payouts that cannot exceed what is payable.
- A profitability report: what the label keeps per release after payee
  shares and its own costs.
- Label costs that belong to no release (subscriptions, admin), entered
  once or as recurring costs that are booked on every due date.
- A payee portal with passwordless sign-in by emailed one-time code.

**Importing statements**

- Zebralution (current and pre-2024 layout), Bandcamp sales reports,
  neighbouring rights and a generic spreadsheet template, as CSV or Excel.
- The source and layout of a file are detected from its columns. Releases,
  tracks and artists are created from the file; lines imported before are
  skipped.
- Bandcamp sales can be fetched automatically through the Bandcamp API.
- Buyer names, emails and addresses in Bandcamp reports are never stored.

**Release management**

- Full release and track metadata: versions, credits, ISRC/ISWC, UPC, genre,
  language, ℗/© lines, pre-order and original release dates, blocked services
  and territories, press release, key facts and to-dos.
- Files per release (cover, audio masters, artwork, documents) on a private
  disk. They are only served to signed-in admins of the label, and audio
  can be played in the browser.
- Masters are kept exactly as uploaded. For listening, an MP3 copy
  (320 kbit/s by default) is made of each with ffmpeg; downloads and
  packages always contain the original.
- Covers are shown as small resized copies, made on first use and kept on
  the same private disk.
- Export of all metadata as a CSV sheet, and a delivery package per release
  (cover, audio named after the track list, metadata sheet, label copy).
- A ZIP per release for [Subvert](https://subvert.fm)'s uploader: the cover
  and the best audio file of each track, named in track order. Pricing,
  credits and rights are entered on Subvert's form. Subvert offers no sales
  export or API yet, so its income is entered by hand or with the
  spreadsheet template.

## Requirements

- PHP 8.3 or newer with the `bcmath`, `zip`, `xmlreader` and `sockets`
  extensions
- Composer, Node.js 22 or newer
- SQLite, MySQL or PostgreSQL

## Installation

```sh
git clone <this repository> label-royalties
cd label-royalties
touch database/database.sqlite   # only when using SQLite, the default
composer setup                   # installs, creates .env, migrates, builds
php artisan db:seed              # optional: demo user and label
composer dev                     # serves the app and watches assets
```

The seeder creates `test@example.com` with the password `password` and a
label called "Demo Label". Change or remove that account before putting an
installation on the internet.

### Things to configure for real use

- **Mail.** Sign-in codes and invitations are sent by email. Set the
  `MAIL_*` values in `.env`.
- **Queue worker.** Those emails are queued. Run `php artisan queue:work`
  (or set `QUEUE_CONNECTION=sync`), otherwise nothing is sent.
- **Scheduler.** Recurring label costs and the weekly Bandcamp sync need
  Laravel's scheduler:
  run `php artisan schedule:run` every minute from cron.
- **Uploads.** Audio masters are large. Raise PHP's `upload_max_filesize`
  and `post_max_size`, and set `RELEASE_UPLOAD_MAX_KB` to match.
- **Playback copies.** The MP3 a master is played from is made by
  [ffmpeg](https://ffmpeg.org) (built with libmp3lame) in a queued job, so
  ffmpeg has to be installed and a queue worker running. Set
  `FFMPEG_BINARY` to its full path if it is not on the worker's `PATH`;
  `AUDIO_PREVIEW_BITRATE` (default `320`) sets the quality. A conversion
  may take up to `AUDIO_PREVIEW_TIMEOUT` seconds (default `600`): keep
  `DB_QUEUE_RETRY_AFTER` and the worker's `--timeout` above that for very
  long recordings. Without a worker, or to catch up, run
  `php artisan royalties:generate-previews` (options: `--label=`,
  `--release=`, `--force`); it converts everything that has no copy yet.
  Without ffmpeg, MP3 uploads still play, WAV and FLAC play as uploaded in
  browsers that support them, and other formats cannot be played; the file
  table of a release shows the state of each file.
- **Cover thumbnails** need PHP's `gd` extension (and `exif` to honour the
  rotation of photos). Without it the full-size cover is shown.
- **Large packages.** A delivery package or Subvert ZIP is written to the
  system's temporary directory before it is sent, so that directory needs
  as much free space as the release is large, and the web server must
  allow the request to run for a few minutes (PHP's own time limit is
  raised to 15 minutes for these downloads).
- **Registration.** Anyone who can reach the site can create an account and
  their own label. Remove `Features::registration()` from
  `config/fortify.php` to make an installation invitation-only.

## Importing from the command line

```sh
php artisan royalties:import statement.csv --label=demo-label --paid=2026-10-15
php artisan royalties:sync-bandcamp --label=demo-label
```

`--paid` is the day the money arrived. Sources that carry no payment date
(Zebralution, neighbouring rights) need it, because a transaction counts in
the accounting period its paid date falls in.

## Adding an importer

One class per file layout, picked up automatically:

```sh
php artisan make:importer ExampleDistributor
php artisan make:importer Zebralution2027 --source=zebralution --layout="2027 onwards"
```

The generated class declares the columns that identify the layout and maps
one line to a transaction. Encoding, delimiter, title lines above the header
and the difference between CSV and Excel are handled before your code runs.
See `app/Services/Imports/Importers/BaseImporter.php`.

## How the figures are calculated

- A transaction counts in the period its **paid date** falls in.
- Its contract is the most specific one set: transaction, then item, then
  track, then release. Without a contract the label keeps the amount.
- Net = income + recoupable expenses (expenses are stored negative). A
  payee's share is net × split, booked on the ledger named by the split.
- A ledger with a negative balance is unrecouped and carries forward. Only
  positive ledgers are payable, and ledgers never offset one another.
- A payout is deducted in the period it settles.

## Mechanicals, PPD and reserves

Three optional tools, switched on per label under Settings → Label. None of
them is part of the period calculation. Each is run by hand under
Royalties: choose a range of paid dates, check the report, then create the
transactions. A run is one data import of ordinary transactions on
dedicated items, so it flows through contracts, statements and ledgers like
everything else, and deleting the import undoes it. Running a range that
overlaps an earlier run asks for confirmation, because it would count the
same sales twice.

- **Mechanicals**: units × a rate per track. Units are permanent downloads
  (and physical sales if the label wants them) paid in the range, plus any
  manufacturing runs you enter. The rate is the track's own, else the
  release's, else the US statutory rate for the track's duration,
  converted with the rate from the label settings when the label does not
  account in US dollars. Each track gets an expense on
  “Track [Mechanicals Expenses]” and the same amount as income on
  “Track [Mechanicals Income]”. The pair cancels out until the income item
  is given a publishing contract, which is what pays the songwriter.
- **PPD**: quantity × a rate per unit, set on the item, else the track,
  else the release. Each release and track gets the total as income on
  “[PPD Income]” and its negative on “[PPD Income Offset]”. A payee owed
  15% of PPD gets it from a contract with a 15% split on the income item.
- **Reserves**: physical income of a release paid in the range × its
  reserves percentage (25 means 25%). You enter the day the reserve is
  held and the day it is liquidated; the release earns that much less in
  the period of the first date and gets it back in the period of the
  second.

The items the tools create have no contract of their own and follow the
track or release. Set one on the Items page to change who is paid.

## Migrating from Infinite Catalog

`php artisan royalties:import-infinite-catalog <export-directory> --label=…`
loads an account export (catalog, contracts, periods, payouts and
transactions), and `royalties:compare-infinite-catalog` checks the
calculated period figures against the ones Infinite Catalog reported. The
export itself has to be produced from your own account; no export tool is
included. This project is not affiliated with Infinite Catalog.

## Not included

- Mechanicals: no Harry Fox Agency file is produced, and the statutory
  rates are the US ones only.
- Mechanicals, PPD and reserves are never calculated on their own or as
  part of a period; see the tools above. Reserves are not liquidated
  automatically after a number of periods.
- Statements are downloaded as CSV, not Excel or PDF.
- Imports and delivery packages are built while you wait, not in the
  background.
- Uploads are sent in one request per file; there is no resumable upload.

## Development

```sh
composer test          # lint check, static analysis and all tests
vendor/bin/pest tests/Feature
npm run build && vendor/bin/pest tests/Browser   # interface tests need built assets
npx playwright install chromium                  # once, for the interface tests
```

Tests use only invented names, codes and amounts. Please keep it that way:
never commit real statements, real artist names or real sales figures.

## Security

Royalty data is confidential. If you find a vulnerability, please report it
privately to the maintainers instead of opening a public issue.
