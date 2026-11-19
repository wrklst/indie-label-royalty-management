# Troubleshooting

Problems that come up when installing and running the software, by symptom, with the cause and the fix.

## Installation and deployment

| Symptom                                  | Cause                                                               | Fix                                                                                                                             |
| ---------------------------------------- | ------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `could not find driver`                  | The PHP version running the command has no database extension.      | Install `pdo_mysql` (or `pdo_sqlite`) for that version, or call a version that has it: `php8.4 artisan …`. Check with `php -m`. |
| `Class "DOMDocument" not found`          | The `dom`/`xml` extension is missing for that PHP version.          | As above.                                                                                                                       |
| "Unable to locate file in Vite manifest" | The interface was not built.                                        | `npm ci && npm run build`                                                                                                       |
| A blank page or "500" after a deployment | Cached configuration from before the change, or a failed migration. | `php artisan optimize`, `php artisan migrate --force`, then read `storage/logs/laravel.log`.                                    |
| A change in `.env` has no effect         | Configuration is cached.                                            | `php artisan optimize` and `php artisan horizon:terminate`                                                                      |
| "The stream or file could not be opened" | The web server user cannot write to `storage`.                      | Give it write access to `storage` and `bootstrap/cache`.                                                                        |

## Signing in

| Symptom                                      | Cause                                                     | Fix                                                                                                                          |
| -------------------------------------------- | --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| No sign-in code arrives                      | Mail is not configured, or the queue worker does not run. | Check `MAIL_*` in `.env` and `php artisan horizon:status`. With `MAIL_MAILER=log` the code is in `storage/logs/laravel.log`. |
| The passkey is not accepted on a new address | Passkeys are bound to the domain they were registered on. | Sign in with a code or password and register a new passkey under **Settings → Security**.                                    |
| Nobody can register                          | By design.                                                | Create accounts with `php artisan royalties:create-user`, or invite people under **Settings → Team**.                        |
| Links in emails point to the wrong address   | `APP_URL` is wrong.                                       | Set it to the public address including `https://`, then `php artisan optimize`.                                              |

## Background work

Emails, audio conversions, the airplay research and the Bandcamp sync all depend on two things: the queue worker and the scheduler.

| Symptom                                                          | Cause                       | Fix                                                                                                    |
| ---------------------------------------------------------------- | --------------------------- | ------------------------------------------------------------------------------------------------------ |
| A research stays "waiting", playback copies are never ready      | Horizon does not run.       | `php artisan horizon:status`. Start it under your process manager; see [Deployment](04-deployment.md). |
| Background jobs still behave like before an update               | Horizon runs the old code.  | `php artisan horizon:terminate`                                                                        |
| Recurring costs are not booked, Bandcamp does not sync on Monday | The scheduler does not run. | Add the cron entry from [Configuration](03-configuration.md). Check with `php artisan schedule:list`.  |
| A job failed                                                     |                             | `php artisan queue:failed` lists them; the Horizon dashboard at `/horizon` shows the error.            |

## Files and audio

| Symptom                                                  | Cause                                                                                     | Fix                                                                                                                                           |
| -------------------------------------------------------- | ----------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| An upload fails at once for large files                  | PHP or the web server reject the size.                                                    | Raise `upload_max_filesize` and `post_max_size` in `php.ini`, `client_max_body_size` in nginx, and `RELEASE_UPLOAD_MAX_KB`.                   |
| "Playback copy is still being prepared" does not go away | The conversion has not run or failed.                                                     | Check Horizon; check that ffmpeg is installed (`ffmpeg -version`) and built with `libmp3lame`. Run `php artisan royalties:generate-previews`. |
| WAV masters do not play, MP3 uploads do                  | ffmpeg is missing.                                                                        | Install it, or set `FFMPEG_BINARY` to its full path.                                                                                          |
| No cover thumbnails                                      | The `gd` extension is missing.                                                            | Install it. Without it the full-size cover is shown.                                                                                          |
| A delivery package download breaks off                   | The request runs too long or the temporary directory is full.                             | Raise the web server's timeout for PHP (15 minutes are used at most) and free space in the system's temporary directory.                      |
| Files are missing after switching to a bucket            | Records still point at the old disk, or the bucket settings are wrong.                    | `php artisan royalties:move-files --to=s3 --dry-run` reports what is where.                                                                   |
| A development copy shows no files from the bucket        | By design: every environment other than production works in its own folder of the bucket. | Copy the files into the folder named after its `APP_ENV`, or use the local disk there.                                                        |

## Imports

| Symptom                                                          | Cause                                                                                       | Fix                                                                                                                                  |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| "The file was not recognised as a statement of any known source" | The columns match no known layout.                                                          | Choose the source by hand; compare the header with the source's required columns. For a new layout see [Extending](51-extending.md). |
| Everything is "already imported"                                 | The same file was imported before.                                                          | That is the protection working. To import again on purpose, delete the earlier import first.                                         |
| An import is stuck on **Review**                                 | Possible duplicates of existing bookings were found.                                        | Open the import and decide; see [Importing statements](10-importing-statements.md).                                                  |
| Income is missing from a period                                  | The paid date is outside the period, or the lines are in a foreign currency without a rate. | Check the paid date of the import; the period lists unconverted currencies.                                                          |
| A release earns money but no payee gets a share                  | The release has no contract.                                                                | Set one under **Catalog → Catalog manager**. The period lists releases without a contract.                                           |

## Airplay research

| Symptom                                              | Cause                                                         | Fix                                                                                             |
| ---------------------------------------------------- | ------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| "Web search is not set up"                           | The label has no DataForSEO login.                            | Enter it under **Settings → Label**.                                                            |
| "DataForSEO does not accept this login and password" | The website password was entered instead of the API password. | Use the API password from the DataForSEO dashboard under **API Access**.                        |
| "Internal SE Server Error" in the research log       | DataForSEO's own search failed.                               | Nothing to fix. It is retried up to three times and not billed; start the research again later. |
| "Payment Required"                                   | The DataForSEO account has no credit.                         | Top it up.                                                                                      |
| No findings                                          | Nothing on the web names the track, or the pages are gone.    | Normal for older plays. The research log shows what was searched.                               |

## Where to look

- `storage/logs/laravel.log`: every error of the application.
- `/horizon`: background jobs, running and failed.
- **Royalties → Data imports → an import**: what a file created and what was left out.
- `php artisan about`: the environment the application actually runs with.

## Reporting a problem

Open an issue with the version (the commit you run), what you did, what happened and the relevant lines of `laravel.log`. Remove names, emails and amounts first. Report security problems privately to the maintainers, not in a public issue.
