# Command line

Every command the application adds, with its options and an example. Run them in the application's folder on the server. Where several PHP versions are installed, call the one the application uses, for example `php8.4 artisan …`.

`--label` always takes the label's slug (as in the address of its pages) or its numeric id.

## Accounts

### royalties:create-user

Creates an account for someone who will set up a label. Nobody can register on their own.

```sh
php artisan royalties:create-user you@example.com --name="Your Name"
```

The person signs in with a code sent by email. Without `--name` the part of the address before the @ is used. Further members of an existing label are invited in the interface instead, under **Settings → Team**.

## Importing

### royalties:import

Imports a statement file, the same way the upload form does.

```sh
php artisan royalties:import statement.csv --label=example-records --paid=2026-11-15
```

| Option        | Meaning                                                                                                                           |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `--label=`    | The label to import into                                                                                                          |
| `--importer=` | Source or importer key: `zebralution`, `bandcamp`, `rightz-audio`, `audio-rights`, `template`. Detected from the file by default. |
| `--paid=`     | Paid date (`Y-m-d`) for sources without one                                                                                       |
| `--rate=`     | Conversion rate for lines in another currency                                                                                     |

When possible duplicates are found, nothing is counted and the import waits for your decision under **Royalties → Data imports**.

### royalties:sync-bandcamp

Fetches new Bandcamp sales through the Bandcamp API and imports them.

```sh
php artisan royalties:sync-bandcamp --label=example-records
```

Without `--label` every label with an enabled connection is synced. Scheduled for Mondays at 05:00.

## Catalog and files

### royalties:generate-previews

Makes the MP3 playback copies of audio masters that have none yet.

```sh
php artisan royalties:generate-previews
php artisan royalties:generate-previews --label=example-records --release=12 --force
```

`--force` converts again what already has a copy, for example after changing the bitrate.

### royalties:move-files

Copies release files and statement files to another disk and points their records at it.

```sh
php artisan royalties:move-files --to=s3 --dry-run
php artisan royalties:move-files --to=s3
```

Every copy is checked by size. Originals are never deleted. Running it again continues where it stopped.

### royalties:research-airplay

Queues a web research for tracks that have not been researched lately.

```sh
php artisan royalties:research-airplay --label=example-records --days=31 --limit=10
```

| Option     | Default    | Meaning                                                                       |
| ---------- | ---------- | ----------------------------------------------------------------------------- |
| `--label=` | all labels | Only this label                                                               |
| `--days=`  | `31`       | Research tracks whose last research is older than this                        |
| `--limit=` | `10`       | The most tracks to queue per label; every track costs several billed searches |

Scheduled daily at 06:30. Labels without a DataForSEO login are skipped.

## Accounting

### royalties:book-recurring-expenses

Books the recurring label costs that have come due. Scheduled daily at 04:00; running it by hand is harmless, a cost is never booked twice for the same due date.

```sh
php artisan royalties:book-recurring-expenses
```

## Migrating from Infinite Catalog

### royalties:import-infinite-catalog

Loads a complete account export (catalog, contracts, periods, payouts and transactions) into a label.

```sh
php artisan royalties:import-infinite-catalog /path/to/export --label=example-records
```

### royalties:compare-infinite-catalog

Checks the calculated period figures against the ones Infinite Catalog reported.

The export has to be produced from your own account; no export tool is included. This project is not affiliated with Infinite Catalog.

## For developers

### make:importer

Creates an importer class for a new statement source, or a new layout of an existing one. See [Extending](51-extending.md).

```sh
php artisan make:importer ExampleDistributor
```

## Updating

### royalties:update

Updates the installation to the newest tagged version of its repository, as described under [Installation](02-installation.md#updating).

```sh
php artisan royalties:update --check
php artisan royalties:update
```

| Option             | Meaning                                                                                                  |
| ------------------ | -------------------------------------------------------------------------------------------------------- |
| `--check`          | Only say whether a newer version exists.                                                                 |
| `--without-backup` | Do not copy the database first. Use it only after backing it up yourself.                                |
| `--scheduled`      | For the scheduler: run only when an update was asked for in the interface or an automatic update is due. |

The command ends with an error when the update was refused or failed, and says whether the previous version runs again.

### royalties:reset-code-cache

Has the web server forget the PHP code it has compiled (OPcache), so that it runs the code on disk. The updater runs it; run it yourself after changing code by hand on a server set up with `opcache.validate_timestamps=0`.

```sh
php artisan royalties:reset-code-cache
```

## Useful framework commands

| Command                         | What it tells you                                                 |
| ------------------------------- | ----------------------------------------------------------------- |
| `php artisan about`             | Environment, drivers and versions at a glance                     |
| `php artisan migrate:status`    | Which database migrations have run                                |
| `php artisan schedule:list`     | The scheduled jobs and when each is next due                      |
| `php artisan horizon:status`    | Whether the queue worker runs                                     |
| `php artisan horizon:terminate` | Stops the worker so the process manager restarts it with new code |
| `php artisan queue:failed`      | Background jobs that failed                                       |
| `php artisan optimize`          | Caches configuration and routes; run after changing `.env`        |

## Good to know

- Commands that work with files use the configured file storage, so they behave the same with a local disk and a bucket.
- Each command accepts `--help` and prints its options.
