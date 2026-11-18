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
- Export of all metadata as a CSV sheet, and a delivery package per release
  (cover, audio named after the track list, metadata sheet, label copy).

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

## Migrating from Infinite Catalog

`php artisan royalties:import-infinite-catalog <export-directory> --label=…`
loads an account export (catalog, contracts, periods, payouts and
transactions), and `royalties:compare-infinite-catalog` checks the
calculated period figures against the ones Infinite Catalog reported. The
export itself has to be produced from your own account; no export tool is
included. This project is not affiliated with Infinite Catalog.

## Not included

- Mechanicals, PPD and reserves: the rates can be stored but nothing is
  calculated from them.
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
