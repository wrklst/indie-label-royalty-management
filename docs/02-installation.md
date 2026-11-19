# Installation

How to get Label Royalties running: what the server needs, the installation itself, and creating the first account. For a production server continue with [Configuration](03-configuration.md) and [Deployment](04-deployment.md) afterwards.

## Server requirements

| Component               | Requirement                                                                                                                                                                                                                                                       |
| ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP                     | 8.3 or newer (8.4 and 8.5 are in daily use)                                                                                                                                                                                                                       |
| PHP extensions          | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `intl`, `mbstring`, `openssl`, `pcntl`, `pdo` with the driver of your database, `posix`, `redis`, `session`, `simplexml`, `sockets`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip` |
| Optional PHP extensions | `gd` for cover thumbnails, `exif` to honour the rotation of photos                                                                                                                                                                                                |
| Composer                | 2.x                                                                                                                                                                                                                                                               |
| Node.js                 | 22 or newer, with npm (only needed to build the interface)                                                                                                                                                                                                        |
| Database                | MySQL 8 or SQLite. PostgreSQL is supported by the framework but not tested with this application.                                                                                                                                                                 |
| Redis                   | For the queue. Optional: see "Without Redis" in [Configuration](03-configuration.md).                                                                                                                                                                             |
| ffmpeg                  | Built with `libmp3lame`, for the MP3 playback copies of audio masters. Optional.                                                                                                                                                                                  |
| Cron                    | One entry that runs the scheduler every minute.                                                                                                                                                                                                                   |
| Mail                    | An SMTP account or a mail service. Sign-in codes and invitations are sent by email.                                                                                                                                                                               |
| Storage                 | Disk space for release files, or an S3-compatible bucket. Masters are large: plan for roughly 300 MB per release with WAV masters.                                                                                                                                |

> **Note:** Check the extensions of the PHP version the command line uses as well as the one the web server uses: `php -m`. A server can have several PHP versions installed, and one of them may be missing `pdo_mysql` or `dom`. Every `php artisan` command in this handbook must be run with a complete one, for example `php8.4 artisan …`.

## Local installation

```sh
git clone https://github.com/your-account/label-royalties.git
cd label-royalties
touch database/database.sqlite   # only when using SQLite, the default
composer setup                   # installs, creates .env, migrates, builds
composer dev                     # serves the app, runs the queue and watches assets
```

`composer setup` does five things: installs the PHP packages, copies `.env.example` to `.env` if there is none, generates the application key, runs the database migrations, and installs and builds the interface.

The application is then at `http://localhost:8000`.

### Using MySQL instead of SQLite

Create an empty database, then set these values in `.env` before running `composer setup` (or run `php artisan migrate` afterwards):

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=label_royalties
DB_USERNAME=label_royalties
DB_PASSWORD=your-password
```

## Create the first account

Nobody can register on their own. Accounts are created on the command line:

```sh
php artisan royalties:create-user you@example.com --name="Your Name"
```

The command prints the address to sign in at. Enter your email there and you receive a one-time code by email.

> **Note:** Locally, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` instead of sending them. Read the code from that file, or set up real mail first.

After the first sign-in you are asked to create your label: its name and its accounting currency. From then on further people are invited in the interface under **Settings → Team**; see [Team, roles and sign-in](40-team-roles-and-sign-in.md).

### Demo data

```sh
php artisan db:seed
```

creates the user `test@example.com` with the password `password` and a label called "Demo Label". Do not run this on a server that is reachable from the internet, or delete the account right after.

## Checking the installation

| Check               | How                                                                                                            |
| ------------------- | -------------------------------------------------------------------------------------------------------------- |
| The interface loads | Open the address in a browser. A "Vite manifest" error means the interface was not built: run `npm run build`. |
| The queue runs      | `php artisan horizon:status` says "Horizon is running".                                                        |
| The scheduler runs  | `php artisan schedule:list` shows the jobs and when they are next due.                                         |
| Mail works          | Request a sign-in code and see that it arrives.                                                                |
| ffmpeg is found     | `ffmpeg -version` prints a version as the user PHP runs as.                                                    |
| Files can be stored | Upload a cover to a release.                                                                                   |

## Updating

```sh
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan horizon:terminate    # the process manager starts it again with the new code
```

Migrations only ever add to the database; an update does not delete data. Make a database backup before every update regardless.

## Good to know

- The application key in `.env` (`APP_KEY`) encrypts stored secrets such as the Bandcamp credentials and the DataForSEO password. If you lose it or change it, those have to be entered again. Keep it with your backups.
- `.env` is for the basic connections of the server only: database, Redis, mail, file storage. Everything a label can change is in the interface under **Settings**.
- Running tests never touches your data: they use an in-memory database and fake file storage.
