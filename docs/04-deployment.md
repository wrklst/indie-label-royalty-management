# Deployment

How to run Label Royalties on a production server. The steps are shown for a plain Ubuntu server and for Laravel Forge; any host that runs a Laravel application works the same way.

## What a production server needs

1. A web server (nginx or Caddy) with PHP-FPM, pointing at the `public` folder.
2. A database and Redis.
3. HTTPS. Sign-in with passkeys does not work without it.
4. A process manager that keeps **Horizon** running.
5. A **cron** entry for the scheduler.
6. Working **mail**.
7. **ffmpeg**, if audio should be playable.
8. **Backups** of the database, the files and the `.env` file.

The settings behind each of these are explained in [Configuration](03-configuration.md).

## First deployment

```sh
git clone https://github.com/your-account/label-royalties.git /var/www/label-royalties
cd /var/www/label-royalties
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# now edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL, database, mail, Redis, storage
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan royalties:create-user you@example.com --name="Your Name"
```

The web server user needs write access to `storage` and `bootstrap/cache`.

### nginx

```nginx
server {
    listen 443 ssl http2;
    server_name royalties.example.com;
    root /var/www/label-royalties/public;
    index index.php;

    client_max_body_size 1024m;   # audio masters

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 900;  # delivery packages take minutes
    }
}
```

### Horizon under Supervisor

`/etc/supervisor/conf.d/label-royalties-horizon.conf`:

```ini
[program:label-royalties-horizon]
process_name=%(program_name)s
command=php /var/www/label-royalties/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/label-royalties/storage/logs/horizon.log
stopwaitsecs=3600
```

```sh
sudo supervisorctl reread && sudo supervisorctl update
```

### Cron

```
* * * * * cd /var/www/label-royalties && php artisan schedule:run >> /dev/null 2>&1
```

## Laravel Forge

1. Create the site with the repository connected and the web directory `/public`.
2. Set the site's PHP version to one that has all extensions from [Installation](02-installation.md).
3. Fill in the environment in the site's **Environment** tab.
4. Under **Processes** add a daemon with the command `php artisan horizon`, running in the site directory.
5. Under **Scheduler** add the job `php artisan schedule:run`, every minute.
6. Use this deploy script:

```sh
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci || npm install
npm run build
$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

If Horizon still runs old code after a deployment, add `$FORGE_PHP artisan horizon:terminate` as the last line.

7. Create the first account over SSH:

```sh
cd ~/royalties.example.com/current
php artisan royalties:create-user you@example.com --name="Your Name"
```

> **Note:** A Forge server often has several PHP versions. If `php artisan` fails with "could not find driver" or "Class DOMDocument not found", the command line uses a version without the needed extensions. Call the right one explicitly, for example `php8.4 artisan …`.

## Moving an existing installation to a new server

1. **Files.** If they are on the old server's disk, either copy `storage/app/private` to the same place on the new server, or move them into a bucket first with `php artisan royalties:move-files --to=s3` and give the new server the same bucket settings.
2. **Database.** Install the application on the new server and run the migrations, so the tables exist. Then copy the data only, leaving out sessions, cache and queue tables:

```sh
mysqldump --no-create-info --complete-insert --single-transaction \
  --ignore-table=old_db.migrations --ignore-table=old_db.sessions \
  --ignore-table=old_db.cache --ignore-table=old_db.cache_locks \
  --ignore-table=old_db.jobs --ignore-table=old_db.job_batches \
  --ignore-table=old_db.failed_jobs --ignore-table=old_db.login_codes \
  --ignore-table=old_db.password_reset_tokens \
  old_db > data.sql

mysql new_db < data.sql
```

3. **Application key.** Copy `APP_KEY` from the old `.env`. Without it the stored Bandcamp and DataForSEO credentials cannot be decrypted and have to be entered again.
4. **Passkeys** are bound to the address they were registered on. On a new domain they have to be registered again; sign in with a code or password first.
5. Compare a few figures (number of transactions, a period total) between old and new before switching over.

## Backups

Back up three things, and test restoring them:

| What     | How                                                                                         |
| -------- | ------------------------------------------------------------------------------------------- |
| Database | A daily dump (`mysqldump`), kept off the server                                             |
| Files    | `storage/app/private`, or the bucket: turn on versioning or replicate it to a second bucket |
| `.env`   | Once, and after every change. It holds `APP_KEY`.                                           |

Audio masters are the one thing that cannot be recreated from anywhere else. If the files are on the server's own disk, a disk failure without a backup loses them.

## After every deployment

- `php artisan migrate --force` has run.
- `php artisan optimize` has run, so configuration changes are in effect.
- Horizon was restarted, so background jobs use the new code.
- The interface was built (`npm run build`).

## Good to know

- Building a delivery package or a Subvert ZIP writes to the system's temporary directory first. It needs as much free space as the release is large, and the web server has to allow the request to run for several minutes.
- Server-side rendering is switched off. The application sits behind a login, so it gains nothing from it, and no Node process has to run in production.
- Plan a switch-over for a time when you are at your desk. Nothing in it needs a night shift: importing a database dump of a small label takes seconds.
