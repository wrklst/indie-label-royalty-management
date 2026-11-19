# Bandcamp

Bandcamp sales can be fetched automatically through the Bandcamp API instead of uploading sales reports. This page explains how to get API access, how to connect an account and what the weekly sync does.

## What you need from Bandcamp

The Bandcamp API is not open to everyone. Bandcamp gives access to labels and to artists with their own account on request.

1. Read Bandcamp's developer page, `bandcamp.com/developer`. It says how to request access; at the time of writing that is a message to Bandcamp support from the label account.
2. Ask for access to the **Sales Report API** for your own accounting.
3. Once access is granted, your Bandcamp account settings show a **client ID** and a **client secret** for the API.

> **Note:** There is no "Sign in with Bandcamp" for this. Bandcamp's API uses a client ID and secret that you copy once; there is no login window to click through.

## Connecting

1. Open **Settings → Bandcamp**. You also reach it from **Settings → Label**, in the section "Bandcamp imports".
2. Enter the **Client ID** and the **Client secret** and choose **Connect and load my bands**. The credentials are proven against Bandcamp right away; if Bandcamp refuses them, nothing is stored.
3. Under **What to import** choose:
    - **Bandcamp account**: the label or artist account whose sales are imported.
    - **Only this artist**: leave it empty to import the sales of every artist on a label account.
    - **Import sales from**: the first sync fetches everything since this day.
    - **Sync automatically every week**: new sales are fetched every Monday morning.
4. Save, then choose **Sync now** for the first run.

The client ID, the secret and the access tokens are stored encrypted and are never shown again.

### Example

Example Records moved to this software on 1 October 2026. Everything before that was imported from Bandcamp sales reports.

- **Import sales from**: `2026-10-01`
- **Sync automatically every week**: on

The first sync creates one import per week since 1 October, named like "Bandcamp API Import (examplerecords 2026-10-05 - 2026-10-12)". From then on a new one appears every Monday.

## What a sync does

- It fetches the sales since the last sync and imports them like an uploaded Bandcamp report: each sale is a transaction with its date as the paid date.
- Each run is an ordinary import under **Royalties → Data imports**. Deleting it removes its transactions.
- Sales that are in already are not imported twice, whether they came from an earlier sync or from an uploaded sales report.
- The two options under **Settings → Label → Bandcamp imports** apply to the sync as well: the 1% deduction for payout fees, and skipping physical and merch sales.

The **Sync** section of the page shows when the last sync ran, up to which day sales are in, and the message of the last run if it failed.

## Changing the account or the start date

Choosing another account or an earlier start date makes the next sync fetch from the start date again. Sales that are in already are not doubled.

## Disconnecting

**Disconnect** deletes the stored client ID, secret and tokens and stops the weekly sync. Transactions that were imported stay. To connect again you need the client ID and secret, and you may have to regenerate the client on Bandcamp.

## From the command line

```sh
php artisan royalties:sync-bandcamp
php artisan royalties:sync-bandcamp --label=example-records
```

Without `--label` every label with an enabled connection is synced. This is what the scheduler runs on Mondays at 05:00.

## Good to know

- The weekly sync needs the scheduler: see [Configuration](03-configuration.md). Without it, use **Sync now**.
- Buyer names, emails and addresses are never stored.
- Bandcamp reports each sale in the currency it was paid in. Sales in a currency other than the label's need a conversion rate before they count; see [Transactions](31-transactions.md).
- The stored credentials are encrypted with the installation's `APP_KEY`. If that key changes, connect again.
