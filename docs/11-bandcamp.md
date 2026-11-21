# Bandcamp

Bandcamp sales can be fetched automatically through the Bandcamp API instead of uploading sales reports. This page explains how to get API access, how to connect an account and what the weekly sync does. It applies only to a label that works with Bandcamp: on the **Bandcamp** card under **Settings → Connections** the box **The label works with Bandcamp** is ticked (a label that never chose has it ticked). Switched off, the Bandcamp statement layout, the delivery ZIP and the weekly sync are hidden for the label, and the scheduled sync skips it; what was imported stays.

## What you need from Bandcamp

The Bandcamp API is not open to everyone. Bandcamp gives access to labels and to artists with their own account on request.

1. Read Bandcamp's developer page, `bandcamp.com/developer`. It says how to request access; at the time of writing that is a message to Bandcamp support from the label account.
2. Ask for access to the **Sales Report API** for your own accounting.
3. Once access is granted, your Bandcamp account settings show a **client ID** and a **client secret** for the API.

> **Note:** There is no "Sign in with Bandcamp" for this. Bandcamp's API uses a client ID and secret that you copy once; there is no login window to click through.

## Connecting

1. Open **Settings → Connections** and follow **Connect your Bandcamp account** on the **Bandcamp** card. That opens the Bandcamp page, which holds the credentials, the account and the sync. If the card says **Switched off**, tick **The label works with Bandcamp** and choose **Save** first.
2. Enter the **Client ID** and the **Client secret** and choose **Test and connect**. The credentials are proven against Bandcamp right away; if Bandcamp refuses them, nothing is stored.
3. Under **What to import** choose:
    - **Bandcamp account**: the label or artist account whose sales are imported.
    - **Only this artist**: shown when the account has artists under it; leave it on **All artists** to import the sales of every artist on a label account.
    - **Import sales from**: the first sync fetches everything since this day.
    - **Sync automatically every week**: new sales are fetched every Monday morning.
4. Choose **Save**, then **Sync now** in the panel **Sync** for the first run. If the account you want is missing, choose **Load accounts from Bandcamp again**.

The client ID, the secret and the access tokens are stored encrypted and are never shown again.

### Example

Example Records moved to this software on 1 October 2026. Everything before that was imported from Bandcamp sales reports.

- **Import sales from**: `2026-10-01`
- **Sync automatically every week**: on

The first sync creates one import for each month since 1 October that has new sales, named like "Bandcamp sync 2026-10-01 – 2026-11-01". From then on the Monday run fetches what is new since the last one, again as one import per month.

## What a sync does

- It fetches the sales since the last sync and imports them like an uploaded Bandcamp report: each sale is a transaction with its date as the paid date.
- Each run is an ordinary import under **Importing → Data imports**. Deleting it removes its transactions. If a month's sales look like transactions that exist already, the sync stops with that message and the import waits in **Duplicates to review**: decide there, then sync again.
- **Sync now** keeps fetching month after month for about 20 seconds. A first sync over several years therefore takes several clicks: the toast says "There is more to fetch: sync again to continue." until everything is in. The scheduled Monday run has no such limit.
- Sales that are in already are not imported twice, whether they came from an earlier sync, from an uploaded sales report or from another royalty system you moved over from, such as an Infinite Catalog export. A sale carried over from another system is recognised by Bandcamp's order number and by whether it was an album, a track or something physical. The same holds for a sales report you upload.
- The two options on the **Bandcamp** card under **Settings → Connections** apply to the sync as well: **Do not deduct 1% from Bandcamp net amounts** and **Skip physical and merch sales**.

The panel **Sync** shows up to which moment sales are in, and below it a badge **OK** or **Failed** with the time and message of the last run.

## Changing the account or the start date

Choosing another account or an earlier start date makes the next sync fetch from the start date again. Sales that are in already are not doubled.

## Disconnecting

The cross on the card on the Bandcamp page opens **Disconnect Bandcamp?**; **Disconnect** deletes the stored client ID, secret and tokens and stops the weekly sync. Transactions that were imported stay. To connect again you need the client ID and secret, and you may have to regenerate the client on Bandcamp.

## From the command line

```sh
php artisan royalties:sync-bandcamp
php artisan royalties:sync-bandcamp --label=example-records
```

Without `--label` every label whose connection has **Sync automatically every week** ticked is synced; with `--label` (slug or id) that label is synced even when it is not ticked. A label with Bandcamp switched off is skipped either way. This is what the scheduler runs on Mondays at 05:00.

## Good to know

- The weekly sync needs the scheduler: see [Configuration](03-configuration.md). Without it, use **Sync now**.
- Buyer names, emails and addresses are never stored.
- Bandcamp reports each sale in the currency it was paid in. Sales in a currency other than the label's need a conversion rate before they count; see [Transactions](31-transactions.md).
- The stored credentials are encrypted with the installation's `APP_KEY`. If that key changes, connect again.
