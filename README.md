# Label Royalties

Royalty accounting and release management for independent record labels.
Import the statements your distributor sends, split the income by contract,
give your artists statements they can read, and keep every release together
with its metadata, cover and audio.

Label Royalties comes from the management of
[Never Not Now](https://nevernotnow.net), an independent label in Berlin,
Germany, and is the software the label runs on: its royalty accounting, its
catalogue and its release files are kept in it, in production. It was built
for the day-to-day work of an indie label and is used for exactly that.

Self-hosted, open source (MIT), built with Laravel, Inertia and React.

- [What it does](#what-it-does)
- [Server requirements](#server-requirements)
- [Installation](#installation)
- [Running it in production](#running-it-in-production)
- [Configuration](#configuration)
- [Documentation](#documentation)
- [Command line](#command-line)
- [Not included](#not-included)
- [Development](#development)
- [Security](#security)
- [License](#license)

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
- Expenses can be recouped from payees or borne by the label alone.
- Accounting periods with a check for unassigned income, releases without a
  contract and unconverted currencies; statements as frozen snapshots with
  revisions; payouts that cannot exceed what is payable.
- A profitability report: what the label keeps per release after payee
  shares and its own costs.
- Label costs that belong to no release, entered once or as recurring costs,
  with receipts uploaded, matched to costs and, with an AI service of your
  choice (Infomaniak, OpenAI, Anthropic, Mistral, Gemini or any
  OpenAI-compatible one), read automatically.
- Optional tools for mechanicals, PPD and reserves.
- A payee portal with passwordless sign-in by emailed one-time code.

**Importing statements**

- Zebralution (current and pre-2024 layout), Bandcamp sales reports,
  Rightz Audio and other neighbouring rights statements, and a generic
  spreadsheet template, as CSV or Excel.
- The source and layout of a file are detected from its columns. Releases,
  tracks and artists are created from the file; lines imported before are
  skipped.
- Every import is compared with what is booked already. Possible duplicates
  are shown with a proposal to merge before anything is counted.
- Bandcamp sales can be fetched automatically through the Bandcamp API.
- Buyer names, emails and addresses in Bandcamp reports are never stored.

**Release management**

- Full release and track metadata: versions, credits, ISRC/ISWC, UPC, genre,
  language, ℗/© lines, pre-order and original release dates, blocked services
  and territories, press release, key facts and to-dos.
- Files per release (cover, audio masters, artwork, documents), on the server
  or in an S3-compatible bucket, served only to signed-in admins of the label.
- An audio player that keeps playing while you work: play a track, a whole
  release, or build a queue. Masters are kept exactly as uploaded; an MP3
  copy is made for listening.
- The pages of every release and track on streaming services, shops and
  platforms, found by UPC, ISRC, Bandcamp sales and web search, or added by hand.
- Export of all metadata as a CSV sheet, a delivery package per release, and
  a ZIP for [Subvert](https://subvert.fm)'s uploader.

**Airplay**

- Radio plays from neighbouring rights statements listed per track, with the
  station when the collecting society names it.
- A web research (through [DataForSEO](https://dataforseo.com)) that looks
  for playlists and show pages, reads the pages it finds and proposes them
  for review. Every search is logged.

**A handbook inside the application**

- The **?** button opens the documentation for the screen you are on, in a
  panel beside your work.

## Server requirements

| Component      | Requirement                                                                                                                                                                                                                                                  |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| PHP            | 8.3 or newer                                                                                                                                                                                                                                                 |
| PHP extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `intl`, `mbstring`, `openssl`, `pcntl`, `pdo` with your database's driver, `posix`, `redis`, `session`, `simplexml`, `sockets`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip` |
| Optional       | `gd` (cover thumbnails), `exif` (photo rotation)                                                                                                                                                                                                             |
| Composer       | 2.x                                                                                                                                                                                                                                                          |
| Node.js        | 22 or newer, to build the interface                                                                                                                                                                                                                          |
| Database       | MySQL 8 or SQLite                                                                                                                                                                                                                                            |
| Redis          | For the queue (optional, see [Configuration](docs/03-configuration.md))                                                                                                                                                                                      |
| ffmpeg         | Built with `libmp3lame`, for playback copies of audio (optional)                                                                                                                                                                                             |
| poppler-utils  | `pdftotext`, `pdftoppm`, for reading PDF receipts (optional)                                                                                                                                                                                                 |
| Cron           | One entry for the scheduler                                                                                                                                                                                                                                  |
| Mail           | SMTP or a mail service: sign-in codes are sent by email                                                                                                                                                                                                      |

## Installation

```sh
git clone https://github.com/wrklst/indie-label-royalty-management.git label-royalties
cd label-royalties
touch database/database.sqlite   # only when using SQLite, the default
composer setup                   # installs, creates .env, migrates, builds
composer dev                     # serves the app, runs the queue and watches assets
```

Then create your account. Nobody can register on their own:

```sh
php artisan royalties:create-user you@example.com --name="Your Name"
```

Open `http://localhost:8000`, sign in with the code sent to that address and
create your label. Locally, emails are written to `storage/logs/laravel.log`
instead of being sent, so read the code from there.

`php artisan db:seed` adds a demo user (`test@example.com`, password
`password`) and a "Demo Label". Do not run it on a public server.

Step by step, including MySQL: [docs/02-installation.md](docs/02-installation.md).

## Running it in production

A production server needs, beyond the above:

1. **HTTPS** and a web server pointing at `public/`.
2. **Horizon** kept running by a process manager (`php artisan horizon`).
   Without it no email is sent, no audio converted and no research run.
3. **The scheduler**: `* * * * * php artisan schedule:run` in cron.
4. **Mail** that actually delivers.
5. **Backups** of the database, the files and `.env` (it holds `APP_KEY`,
   which encrypts stored credentials).

Ready-to-use configurations for nginx, Supervisor and Laravel Forge, a deploy
script, and how to move an installation to another server:
[docs/04-deployment.md](docs/04-deployment.md).

## Configuration

`.env` holds only the basic connections of the server: database, Redis, mail
and file storage. Everything a label can change is in the interface under
**Settings**: the Bandcamp connection, the DataForSEO login for the airplay research, the AI service that reads receipts, the file storage, the research countries, the accounting tools and territories.

The settings you are most likely to touch:

| Setting                     | Purpose                                                         |
| --------------------------- | --------------------------------------------------------------- |
| `APP_URL`                   | The public address, with `https://`                             |
| `DB_*`, `REDIS_*`, `MAIL_*` | Database, queue and mail                                        |
| `ROYALTIES_FILES_DISK`      | `local` (default) or `s3` for a bucket, with the `AWS_*` values |
| `RELEASE_UPLOAD_MAX_KB`     | Largest release file; PHP and the web server must allow as much |
| `FFMPEG_BINARY`             | Path to ffmpeg when it is not on the `PATH`                     |
| `HORIZON_ALLOWED_EMAILS`    | Who may open the queue dashboard at `/horizon`                  |

Every setting with examples: [docs/03-configuration.md](docs/03-configuration.md).

## Documentation

The handbook lives in [`docs/`](docs) and is the same text the application
shows in its help panel.

**Setup**

- [Introduction](docs/01-introduction.md): the concepts and a first session
- [Installation](docs/02-installation.md)
- [Configuration](docs/03-configuration.md)
- [Deployment](docs/04-deployment.md)

**Importing**

- [Importing statements](docs/10-importing-statements.md)
- [Bandcamp](docs/11-bandcamp.md)

**Catalog**

- [Releases](docs/20-releases.md)
- [Tracks, artists and items](docs/21-tracks-artists-and-items.md)
- [Files, audio and delivery](docs/22-files-audio-and-delivery.md)
- [Airplay](docs/23-airplay.md)

**Accounting**

- [Contracts, payees and ledgers](docs/30-contracts-payees-and-ledgers.md)
- [Transactions](docs/31-transactions.md)
- [Periods, statements and payouts](docs/32-periods-statements-and-payouts.md)
- [Profitability and label costs](docs/33-profitability-and-label-costs.md)
- [Mechanicals, PPD and reserves](docs/34-mechanicals-ppd-and-reserves.md)

**People and settings**

- [Team, roles and sign-in](docs/40-team-roles-and-sign-in.md)
- [Label settings](docs/41-label-settings.md)
- [Connections](docs/42-connections.md)

**Reference**

- [Command line](docs/50-command-line.md)
- [Extending](docs/51-extending.md): importers, station drivers, tests
- [Troubleshooting](docs/52-troubleshooting.md)

## Command line

```sh
php artisan royalties:create-user you@example.com
php artisan royalties:import statement.csv --label=example-records --paid=2026-11-15
php artisan royalties:sync-bandcamp --label=example-records
php artisan royalties:generate-previews
php artisan royalties:move-files --to=s3 --dry-run
php artisan royalties:research-airplay --label=example-records
```

All commands and options: [docs/50-command-line.md](docs/50-command-line.md).

### Migrating from Infinite Catalog

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
  part of a period. Reserves are not liquidated automatically.
- Statements are downloaded as CSV, not Excel or PDF.
- Imports and delivery packages are built while you wait, not in the
  background.
- Uploads are sent in one request per file; there is no resumable upload.
- The interface is in English only.

## Development

```sh
composer ci:check      # everything CI runs: lint, types, Pint, PHPStan, Pest
vendor/bin/pest tests/Feature
npx playwright install chromium                  # once, for the interface tests
npm run build && vendor/bin/pest tests/Browser   # interface tests need built assets
```

Tests use an in-memory database and fake file storage; they never touch your
data. They also use only invented names, codes and amounts. Please keep it
that way: never commit real statements, real artist names or real sales
figures.

How to add an importer for another distributor or a radio station for the
airplay research: [docs/51-extending.md](docs/51-extending.md).

## Security

Royalty data is confidential. If you find a vulnerability, please report it
privately to the maintainers instead of opening a public issue.

## License

MIT. See [LICENSE](LICENSE).
