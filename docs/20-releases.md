# Releases

This page covers the release list, creating and editing releases, every field on the release form, the release page with its figures, and the Catalog manager. Only label admins can open these pages.

## Where releases come from

Most releases are created for you: when you import a statement, every release, track and artist named in the file that is not in your catalog yet is added. See [Importing statements](10-importing-statements.md).

You can also create a release by hand, for example before its first statement arrives. An import later finds a hand-made release by its UPC / EAN, then by its catalog number. If the release has a catalog number but no UPC yet, the first statement line that names both fills in the UPC.

## The release list

Open **Catalog → Releases**. The list shows 50 releases per page.

| Column         | What it shows                                                                                                                            |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| (cover)        | A small thumbnail of the cover, if one is uploaded.                                                                                      |
| (play)         | **Play release** and **Queue release** buttons, only when at least one track has audio.                                                  |
| Cat. no.       | The catalog number.                                                                                                                      |
| Release        | The name, linked to the release page. A status badge appears unless the status is Done, and a badge with the release type if one is set. |
| Artist         | The credited artists, for example `Example Artist feat. Guest Artist`.                                                                   |
| UPC / EAN      | The barcode number.                                                                                                                      |
| Released       | The release date.                                                                                                                        |
| Tracks         | The number of tracks.                                                                                                                    |
| Contract       | The contract of the release, or the badge **No contract**.                                                                               |
| Income to date | The sum of all income transactions of the release, in your label currency. Expenses are not subtracted here.                             |

**Search.** The box **Search name, catalog number or UPC…** matches any part of those three values. The list updates while you type.

**Filters.**

- **All artists** narrows the list to one artist. It matches the main artist of a release only, not further artist credits.
- **Any status** narrows the list to **To do**, **In progress** or **Done**.
- **Without contract** shows only releases that have no contract of their own.
- **Clear filters** appears when any search or filter is set and resets all of them.

**Sorting.** Without a sort the list is ordered by catalog number, with releases that have no catalog number last, then by name. Click a column heading to sort by it, click again to reverse, and a third time to go back to the default order. Every column with a heading text can be sorted.

The **Metadata CSV** button above the list downloads the metadata of all releases; see [Files, audio and delivery](22-files-audio-and-delivery.md).

## Create a release

1. Open **Catalog → Releases** and click **New release**.
2. Enter at least a **Name**.
3. Fill in what you know; all other fields are optional and can be added later.
4. In the **Tracks** panel, click **Add track** for each track and enter its title.
5. Click **Create release**.

You land on the page of the new release. Every new release also gets an item called `[Additionals]`, which carries income and costs you enter by hand; see [Tracks, artists and items](21-tracks-artists-and-items.md).

Example: for the label Example Records you create a release named `Example Release` by `Example Artist` with catalog number `EX001`, add the tracks `Opening` and `Second Track`, and leave everything else empty. The release appears in the list with the status **To do** and the contract `none`.

## Edit or delete a release

1. Open the release and click **Edit**.
2. Change the fields and click **Save release**.

To delete, click **Delete** on the edit page and confirm. See [Good to know](#good-to-know) for what is removed and what is kept.

## Fields: Release

| Field                  | What it is for                                                                                                                |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Name                   | The title. Required.                                                                                                          |
| Artist                 | The main artist. Leave it at **No artist / various** for compilations.                                                        |
| Contract               | The contract that splits the income of this release. See [Contract on a release](#contract-on-a-release).                     |
| Catalog number         | Your own number, for example `EX001`. Imports use it to find the release when a line has no known UPC.                        |
| UPC / EAN              | The barcode number, 8 to 14 digits. Spaces and dashes are removed when you save. Imports find the release by this code first. |
| Release date           | The date the release comes out.                                                                                               |
| Spotify ID, Discogs ID | Free text fields for the identifiers on those services.                                                                       |
| Notes                  | Internal notes, shown at the bottom of the release page.                                                                      |

A UPC / EAN can belong to only one release of your label. A 12-digit UPC and its 13-digit EAN form (the same digits with a leading zero) count as the same code.

## Fields: Release details

These are the values platforms and distributors ask for. They are shown on the release page and written into the metadata CSV and the delivery package.

| Field                  | What it is for                                                                                                                                           |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Status                 | Where the release stands in your own workflow. See [Status](#status).                                                                                    |
| Format                 | The release type. See [Release types](#release-types).                                                                                                   |
| Version                | For example `Deluxe Edition` or `The Remixes`. Shown in brackets after the name.                                                                         |
| Genre, Subgenre        | Free text.                                                                                                                                               |
| Language               | A short language code such as `en`, `de`, or `zxx` for instrumental. At most 8 characters.                                                               |
| ℗ line                 | The owner of the recordings, for example `2026 Example Records`.                                                                                         |
| © line                 | The owner of artwork and packaging.                                                                                                                      |
| Explicit               | **Not explicit** (empty), **Explicit** or **Clean version**.                                                                                             |
| Original release date  | For reissues.                                                                                                                                            |
| Pre-order from         | The date pre-orders start.                                                                                                                               |
| Link to external files | A web address (`http` or `https`) of a shared folder, if files also live somewhere else.                                                                 |
| Do not deliver to      | Services that must not get the release, separated by commas, for example `Spotify, Tidal`.                                                               |
| Blocked territories    | Two-letter country codes separated by commas, for example `RU, IL`. They are stored in upper case; anything that is not exactly two letters is rejected. |
| Further artist credits | Additional artists on the whole release, each with a role. See [Tracks, artists and items](21-tracks-artists-and-items.md#artist-credits).               |

The blocked services and territories are recorded and exported. The software does not deliver releases itself, so it does not enforce them.

## Fields: Promotion and planning

| Field         | What it is for                                     |
| ------------- | -------------------------------------------------- |
| To do         | Open tasks for this release.                       |
| Key facts     | Selling points for platforms, press and playlists. |
| Press release | The full press text.                               |

All three are shown on the release page when filled. Key facts and the press release are also written into the `release.txt` of the delivery package.

## Fields: Tracks

Each row is one track with these columns: `#` (track number), **Title**, **Artist**, **ISRC** and **Contract**.

- **Add track** adds a row. The number is prefilled with the next position and the artist with the artist of the release.
- A title is required for every row.
- An ISRC must have 12 letters and digits. Dashes and spaces are ignored, so `DE-A1B-26-00001` and `DEA1B2600001` are the same. It is stored without dashes.
- Leave **Contract** at **Same as release** unless the track has its own deal.
- The **X** at the end of a row removes the track. When you remove a track that was already saved, a warning appears: saving deletes the track, and its transactions stay on the release but lose the track and any track-level contract.

Further track details (version, disc number, ISWC, length, credits, lyrics and more) are edited on the release page, not on this form. See [Tracks, artists and items](21-tracks-artists-and-items.md).

## Fields: Mechanicals, PPD and reserves

This panel only appears when at least one of the three tools is switched on for your label under **Settings → Label**. It holds **Mechanicals rate**, **PPD**, **Reserves %** and the checkbox **Account for mechanicals**. See [Mechanicals, PPD and reserves](34-mechanicals-ppd-and-reserves.md).

## Status

The status is a marker for your own workflow. It does not change any calculation.

| Status      | Meaning                                                                    |
| ----------- | -------------------------------------------------------------------------- |
| To do       | The default for every new release, also for releases created by an import. |
| In progress | You are working on it.                                                     |
| Done        | Nothing left to do. The badge is hidden in the release list for these.     |

## Release types

The field is labelled **Format** on the form and on the release page. The values are **Album**, **EP**, **Single** and **Compilation**, or **Not set**. The type is shown as a badge in the list and is written to the metadata CSV and the delivery package. It has no effect on royalties.

## Contract on a release

The contract of a release applies to every track that has no contract of its own. Without any contract, the label keeps all income.

The most specific contract wins: a contract set on a single transaction, then on the item, then on the track, then on the release. See [Contracts, payees and ledgers](30-contracts-payees-and-ledgers.md).

You can set the contract in three places:

- on the release form, in the field **Contract**,
- per track on the release form or in the track dialogs,
- for many releases and tracks at once in the [Catalog manager](#catalog-manager).

## The release page

Click a release name anywhere to open its page. The heading shows the name (with the version in brackets), the status, the artist, the type, the catalog number, the UPC and the release date.

**Buttons.**

- **Delivery** downloads the release as a package or as a spreadsheet; see [Files, audio and delivery](22-files-audio-and-delivery.md).
- **New expense** opens a new expense transaction with this release filled in; see [Transactions](31-transactions.md).
- **Edit** opens the release form.

**Cover and figures.** Next to the cover there are four tiles:

| Tile             | What it shows                                         |
| ---------------- | ----------------------------------------------------- |
| Income to date   | The sum of all income transactions of the release.    |
| Expenses to date | The sum of all expense transactions (shown negative). |
| Net              | Income plus expenses.                                 |
| Contract         | The contract of the release, linked, or **None**.     |

Example: with 1,250.00 of income and −400.00 of expenses, **Net** shows 850.00.

**Tracks.** The table has the columns `#`, **Title**, **Artist**, **ISRC**, **Length**, **Listen**, **Contract**, **Units** and **Income**. Click a heading to sort.

- **Units** and **Income** are the quantity and amount of the income transactions booked on that track.
- **Contract** shows the track's own contract, **Release contract** when it uses the one of the release, or `none`.
- **Listen** holds the play buttons, or **No audio** when no audio file is attached to the track.
- The pencil at the end of a row opens the dialog **Details of …** for all track details.
- If income was booked on the release without a track, a last line reads **Booked on the release as a whole (album sales, additionals)** with that amount.
- The button **… transactions** above the table opens the transaction list filtered to this release.

**Files.** Upload and manage the cover, audio and other files; see [Files, audio and delivery](22-files-audio-and-delivery.md).

**Release details.** The values from the form: Format, Genre, Language, Explicit, ℗ line, © line, Original release, Pre-order from, Not delivered to, Blocked territories and External files.

**To do, Key facts, Press release.** Each is shown only when it has text.

**The report.** How the income of the release developed, which platforms it came from and what each paid per stream, followed by **Tracks by income**; see [Reports and play counts](12-reports-and-play-counts.md).

**Items.** Every name a statement, platform or manual entry used for the release or one of its tracks. Each row says which track it is booked on (or **Whole release**), which sources used the name, its format group, the number of transactions, their amount and, if set, the contract override; see [Tracks, artists and items](21-tracks-artists-and-items.md#items).

**Notes.** Shown when the release has notes.

## Where to find it

The panel **Where to find it** on the release page collects the pages of the release on the platforms where it is streamed or sold: Spotify, Apple Music, Bandcamp, Subvert, Deezer, Tidal, YouTube, Beatport and so on.

### Finding links

**Find links** looks the release and each of its tracks up in the background. Links appear in the panel as they are found.

| Source                                                     | What it finds                                                                                  | Shown as        |
| ---------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | --------------- |
| The **UPC** of the release                                 | Its album page on Deezer and on Apple Music                                                    | found by code   |
| The **Spotify ID** stored on the release                   | Its album page on Spotify                                                                      | found by code   |
| The **ISRC** of each track                                 | The track's page on Deezer, and its song page on Apple Music                                   | found by code   |
| **Bandcamp sales** fetched through the Bandcamp connection | The Bandcamp page the sales name                                                               | from sales      |
| **Subvert**                                                | The address Subvert builds from artist and release name, kept when that page names the release | found           |
| One **web search**, when the label has a web search login  | Results on known platforms whose title names the release                                       | found by search |

At most one link per platform is found. The web search is one billed search per release; without a login under **Settings → Connections** it is skipped.

### Adding and correcting

- **Add link**: paste the address. The platform is recognised from it; choose one yourself for an address that is not recognised.
- The pencil corrects a link. A corrected link counts as your own.
- Removing a link that was found keeps it out for good: **Find links** does not bring it back. Add it by hand if you want it again.

Example: for `Example Release` (EX001, UPC 4000000000001), **Find links** adds Deezer and Apple Music by the UPC and the Subvert page `subvert.fm/example-artist/example-release`. The Bandcamp page is not found, because no sale came in through the sync yet, so you paste `https://example.bandcamp.com/album/example-release` with **Add link**.

## Play or queue a whole release

When at least one track of a release has audio, two buttons appear above the track table:

- **Play release** replaces the current queue with the tracks of the release and starts the first one.
- **Queue release** adds the tracks to the end of the queue. Tracks that are already queued are not added twice.

The same two actions are available as icon buttons in the release list and on an artist's page. Tracks without audio are left out. See [Files, audio and delivery](22-files-audio-and-delivery.md#the-audio-player) for the player itself.

## Catalog manager

Open **Catalog → Catalog manager** (`/catalog`). It is the place to assign contracts across the whole catalog without opening each release.

Every release is a box with its catalog number, name and artist, and a contract field on the right. Below it, each track has its own contract field.

1. Choose a contract in the field of a release to set the release contract. **No release contract** clears it.
2. Choose a contract in the field of a track to give that track its own contract. When left empty, the field shows `Release: …` with the name of the release contract, or **No contract**.
3. Nothing else is needed: every change is saved immediately.

Tools on the page:

- **Search releases and tracks…** narrows the page to releases where the release name, catalog number, artist, a track title, a track ISRC or a track artist contains your text.
- **Without contract** hides every release that has a release contract. A release without a contract is also hidden when all of its tracks have their own contract.
- The counter on the right says how many tracks have no contract at all, or **Every track is covered by a contract.**
- A badge **No contract** marks each track that has neither its own contract nor a release contract.
- **New contract** opens the contract form.

Example: `Example Release` has no contract and two tracks. The counter shows `2 tracks without a contract`. You pick the contract `Example Artist 50/50` on the release; both badges disappear and the counter drops by two. `Second Track` is a remix with its own deal, so you pick `Remixer deal` on that track only.

## Good to know

- The **All artists** filter only looks at the main artist. A release where an artist is only a further credit is not found by it, but it is counted in the artist list and listed on the artist's own page.
- **Income to date** in the list counts income only. Use the **Net** tile on the release page to see income minus expenses.
- Amounts are shown in your label currency, using the converted value of each transaction.
- The track order in the form and on the release page is by track number. The disc number is not shown on the release form; set it in **Details of …** on the release page.
- Saving the release form replaces the track list: tracks whose row you removed are deleted. Their transactions stay on the release.
- Deleting a release deletes its tracks, its files (from storage, for good) and its items without transactions. Its transactions are kept but become unassigned, so they no longer earn for any payee. Items that carry transactions stay behind as unassigned items, and the `[Additionals]` item is renamed to `[Additionals] Example Release`, so you can assign that income to another release on the **Items** page.
- A release can be saved without tracks. Income can still be booked on the release itself.
- The Catalog manager shows 50 releases per page. Search and **Without contract** work on the whole catalog, not only the page you are on, and **Clear filters** resets them.
- The status, the release type and the blocked services and territories are information only. They do not affect statements or payouts.
