# Tracks, artists and items

This page covers the track list and the track page, artists with their aliases and credit roles, and items: the things statement lines are booked on. Only label admins can open these pages.

## Tracks

A track always belongs to exactly one release. The same recording on a single and on an album is therefore two tracks that share one ISRC.

Tracks are created in two ways: by an import, from the lines of a statement, or by you on the release form (**Add track**). There is no button to create a track on its own; see [Releases](20-releases.md).

### The track list

Open **Catalog → Tracks**. The list shows 50 tracks per page, ordered by title.

| Column   | What it shows                                                                                   |
| -------- | ----------------------------------------------------------------------------------------------- |
| Title    | The track title, linked to the track page.                                                      |
| Artist   | The main artist of the track.                                                                   |
| Release  | The release the track is on.                                                                    |
| ISRC     | The ISRC, or `–`.                                                                               |
| Contract | The track's own contract; or the release contract followed by `(release)`; or the badge `none`. |
| Listen   | Play buttons, or **No audio** when no audio file is attached.                                   |

- **Search title or ISRC…** matches part of the title or of the ISRC. You can type the ISRC with or without dashes.
- **All releases** narrows the list to one release.
- **Clear filters** appears when a search or filter is set and resets it.

A recording that is on several releases is one row in this list, naming all its releases; the row is the oldest of its tracks. A track without audio of its own plays the audio of the same recording on another release. Narrow the list to a release to see and edit that release's own track. A badge **No contract on Example Single** marks a release on which the recording has no contract.

- Click a column heading to sort, again to reverse, and a third time to return to the default order.

The pencil at the end of a row opens a short edit dialog with **Title**, **Artist**, **Contract**, **ISRC**, **ISWC**, **Track number** and **Length (seconds)**. The bin deletes the track.

### All track details

The full set of details is edited on the release page: open the release and click the pencil at the end of the track row. The dialog **Details of …** has these fields:

| Field                                       | Notes                                                                          |
| ------------------------------------------- | ------------------------------------------------------------------------------ |
| Title                                       | Required.                                                                      |
| Version                                     | For example `Radio Edit` or the remix name. Shown in brackets after the title. |
| Main artist                                 | Empty means **Same as release**.                                               |
| Contract                                    | Empty means **Same as release**.                                               |
| Further artist credits                      | Additional artists with a role; see [Artist credits](#artist-credits).         |
| ISRC, ISWC                                  | See below.                                                                     |
| Length (seconds)                            | The length in whole seconds. Shown as minutes and seconds elsewhere.           |
| Disc, Track number                          | Disc 1 to 99, track number 1 to 999.                                           |
| Preview starts at (s)                       | The second at which a platform preview should start.                           |
| BPM                                         | 20 to 400, with up to two decimals.                                            |
| Key                                         | Free text, for example `F minor`.                                              |
| Explicit                                    | **Same as release** (empty), **Explicit** or **Clean version**.                |
| Genre, Language, ℗ line                     | Leave empty to use the value of the release in exports.                        |
| Composers, Lyricists, Producers, Publishers | Names separated by commas.                                                     |
| Lyrics                                      | Free text.                                                                     |

### ISRC and ISWC

- An **ISRC** identifies a recording. It must have 12 letters and digits. Dashes and spaces are ignored when you type it, and it is stored in upper case without dashes: `DE-A1B-26-00001` becomes `DEA1B2600001`. Anything else is rejected with a message.
- Imports find a track by its ISRC within the release. If you entered a track by hand without an ISRC, the first statement line with the same title on that release fills the ISRC in.
- An **ISWC** identifies the composition. It is stored as you type it (at most 16 characters) and is not checked for its format. It is shown on the track page and exported in the metadata CSV.

### A track's own contract

A track without a contract uses the contract of its release. Give a track its own contract only when its deal differs, for example a remix where the remixer gets a share.

Example: `Example Release` has the contract `Example Artist 50/50`. `Second Track` is a remix, so you set the contract `Remixer deal` on that track. Income booked on `Second Track` is now split by `Remixer deal`; income booked on `Opening` or on the release as a whole is still split by `Example Artist 50/50`.

You can set a track contract in the release form, in either track dialog, or in the Catalog manager; see [Releases](20-releases.md#catalog-manager). How contracts split income is explained in [Contracts, payees and ledgers](30-contracts-payees-and-ledgers.md).

### The track page

Click a track title anywhere to open its page. The heading shows the title (with the version in brackets), the artists and the release.

**One recording, several releases.** All tracks of your label that share the same ISRC count as one recording on this page. Income, airplay, findings and the research log cover all of them together, whichever entry you opened. A track without an ISRC is never combined with another.

**Buttons.** The play buttons appear when the track has audio. **Research airplay** appears when a web search is set up for your label; see [Airplay](23-airplay.md).

**Tiles.**

| Tile                | What it shows                                                                                                                     |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| Income              | The sum of all income transactions of the recording. The link **… transactions** below opens the transaction list filtered to it. |
| Airplay             | The number of known airplay entries, and how many of them name a station.                                                         |
| Earned from airplay | The sum of the amounts of those entries.                                                                                          |
| Findings to review  | The number of research findings you have not kept or dismissed yet, and when the track was last researched.                       |

**Details.** ISRC, Release, Contract, Length, Track number, Genre, BPM / key and ISWC. When the track uses the release contract, the contract is followed by `(from the release)`. These details belong to the entry you opened, not to the whole recording.

**On … releases.** Shown only when the recording is on more than one release. It lists each release with its catalog number and **Income there**, the income booked on the track of that release. The entry you opened is marked `(this entry)`.

Example: `Opening` with the ISRC `ZZEXA2600001` is on the single `Opening` (EX001) and on the album `Example Release` (EX002). The single earned 40.00 and the album track 110.00. Both track pages show **Income** 150.00 and the same airplay, and the panel **On 2 releases** shows the two amounts separately.

**Airplay.** Where the track was played: **Station**, **Known from** (a neighbouring rights statement, a web search, or entered by hand), **Played**, **Earned** and **Links**. **Manage airplay and findings** opens the Airplay page filtered to this track; see [Airplay](23-airplay.md).

**The report.** How the income of the recording developed, which platforms it came from and what each paid per stream; see [Reports and play counts](12-reports-and-play-counts.md). Shown only when there is income or a play count.

**Research log.** Every web search run for the recording, 10 to a page and sortable, with its result. Shown only when there are any. The **Airplay** table above it has 25 plays to a page and sorts by **Station**, **Known from**, **Played** and **Earned**.

### Where to find a track

The track page has the same panel **Where to find it** as the release page, for the track's own pages. **Find links** looks the track up by its ISRC on Deezer, as a song of its album on Apple Music, and in Bandcamp sales of the track. Links are added, corrected and removed as on a release; see [Releases](20-releases.md#where-to-find-it).

## Artists

Artists are the performers on your releases and tracks. An import creates an artist whenever a statement mentions a name that is not in your catalog yet. Names are compared without regard to upper and lower case and extra spaces.

### The artist list

Open **Catalog → Artists**. The list shows 50 artists per page, ordered by name.

| Column   | What it shows                                                                                                                 |
| -------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Name     | The name, linked to the artist page, with a badge `alias of …` or `… aliases` where that applies.                             |
| Releases | The number of releases with this artist as main artist. A number above zero links to the release list filtered to the artist. |
| Tracks   | The number of tracks with this artist as main artist.                                                                         |
| Notes    | Your notes.                                                                                                                   |

**Search artists…** matches part of the name.

To add an artist:

1. Click **New artist**.
2. Enter the **Name**. Optionally fill **Alias of**, **Spotify ID**, **Discogs search name** and **Notes**.
3. Click **Create artist**.

The pencil in a row opens the same fields for editing. The bin deletes the artist.

### Aliases

Set **Alias of** when an artist is another name of an artist already in your catalog, for example the spelling a platform or a statement uses, such as `Example Artist feat. Guest Artist`.

- The artist list shows an alias under its artist, not as a row of its own. Tick **Aliases** to show them as rows of their own and edit or delete an alias. The search also finds an artist by the name of an alias.
- The artist page names its aliases under the title; the page of an alias links to its artist.
- A release or a track that an import creates under the name of an alias belongs to the main artist. Releases and tracks that were given to the alias before stay with it until you change their artist.

Rules:

- An alias must point at an artist that is not an alias itself.
- An artist that has aliases cannot become an alias.
- An artist cannot be an alias of itself.

### The artist page

Click an artist name anywhere to open the page. It shows:

- **Releases**: every release the artist is on, as main artist, in any credit role or with one of their tracks, newest first by default. Sortable, 25 to a page. A release of another artist shows what this artist's tracks earned on it. Listed with **Catalog number**, **Released** and the buttons to play or queue the whole release.
- **Tracks**: every track the artist is on, as main artist or in any credit role, with its releases, ISRC and the number of airplay entries. Sortable, 25 to a page. A recording that is on several releases is one row naming all of them, with airplay and income added up.
- **Contracts and payees**: the contracts of these releases and tracks, each with its payees and their share. A track uses its own contract, otherwise that of its release.

The page has no edit button. Edit an artist from the artist list.

### Artist credits

A release and a track each have one main artist (the field **Artist** or **Main artist**) and any number of further credits. Add them under **Further artist credits** with **Add artist credit**, then choose the artist and a role. New rows start with the role **Featuring**.

| Role        | Effect on the displayed name                                                            |
| ----------- | --------------------------------------------------------------------------------------- |
| Main artist | Joined to the main artist with `&`.                                                     |
| Featuring   | Added after `feat.`, several joined with `&`.                                           |
| Remixer     | Not part of the displayed name. Exported in the column `remixers` of the metadata CSV.  |
| Producer    | Not part of the displayed name. Exported in the column `producers` of the metadata CSV. |

Example: the main artist is `Example Artist`, with a further credit `Second Artist` as **Main artist** and `Guest Artist` as **Featuring**. The name is shown as `Example Artist & Second Artist feat. Guest Artist`.

Details:

- The artist in the main field always comes first.
- A credit that repeats the same artist in the same role is kept once. A **Main artist** credit for the artist already in the main field is dropped.
- A track with no artist of its own is shown with the artists of its release in the player.
- An artist used in a credit must exist in your catalog first. Create it in the artist list.
- The text fields **Composers**, **Lyricists**, **Producers** and **Publishers** on a track are plain names. They are not linked to artists in your catalog.

## Items

An item is one sellable thing as it appears in statements: a track or a release in one format group. Every imported transaction is booked on an item.

The format groups are **Digital**, **Physical**, **Rights**, **Publishing** and **Reserves**. Each format of a statement line (for example `Subscription Audio Stream`, `Download Album` or `LP`) belongs to one group.

### How items are matched

During an import, each line is linked to a release and, if it names one, a track. The item is then the combination of release, track and format group:

- All streaming and download lines for `Opening` on `Example Release` share one item with the format group **Digital**.
- LP sales of `Example Release` as a whole go on an item without a track and with the format group **Physical**.
- A line that names a release not in your catalog creates the release, and a new item with it.
- A line that names no release, and whose ISRC is not known either, goes on an **unassigned** item. Lines with the same ISRC and UPC (or, when a line has neither, the same name) and the same format group share that item.

How lines are recognised in detail is described in [Importing statements](10-importing-statements.md).

Every release also has one item named `[Additionals]`. It carries transactions you enter by hand for the release; see [Transactions](31-transactions.md). The tools for mechanicals, PPD and reserves create their own items; see [Mechanicals, PPD and reserves](34-mechanicals-ppd-and-reserves.md).

### The Items page

Open **Catalog → Items**. The list shows 50 items per page, with unassigned items first.

| Column            | What it shows                                                                  |
| ----------------- | ------------------------------------------------------------------------------ |
| Item              | The name from the statement, with the artist name from the statement below it. |
| Format group      | The group, or `–` when the format was not recognised.                          |
| Release           | The release the item is assigned to, or the red badge **Unassigned**.          |
| ISRC / UPC        | The ISRC from the statement, or the UPC when there is no ISRC.                 |
| Contract override | A contract set on the item itself, if any.                                     |
| Transactions      | The number of transactions booked on the item.                                 |
| Total             | The sum of those transactions.                                                 |

- **Search name, ISRC, UPC…** also matches the catalog number.
- **Clear filters** appears when a search or filter is set and resets it.
- **All releases** narrows the list to one release.
- **Unassigned** shows the items without a release. When there are any, a yellow notice at the top says how many, with the button **Show unassigned**.

### Assign an item

Income on an unassigned item stays with the label until you assign the item.

1. Open **Catalog → Items** and tick **Unassigned only**.
2. Click the link icon at the end of the row. The dialog **Assign item** shows what the statement said under **From the file**.
3. Choose the **Release**.
4. Choose the **Track**, or leave it at **Whole release** when the item is the release itself, for example an album download.
5. Click **Save**.

All transactions of the item move with it to the release and track you chose. The confirmation tells you how many were moved.

Example: an unassigned item `Opening` with the ISRC `ZZEXA2600001` has 37 transactions. You assign it to `Example Release` and the track `Opening`. The message reads `Item saved and 37 transactions moved with it.`, and the income now counts for the contract of that track or release.

### Contracts on items

**Contract override** in the same dialog gives the item its own contract. Use it only for items with their own deal, such as a licensed vinyl run. Leave it at **Use track / release contract** otherwise.

An item contract beats the track and release contract. Only a contract set directly on a single transaction is more specific.

## Good to know

- Deleting a track keeps its transactions on the release, but they lose the track and any track-level contract. Items of the track without transactions are deleted with it.
- Deleting an artist keeps the releases and tracks, which lose their artist. Aliases of the artist stay and are no longer aliases. Further credits of the artist are removed.
- The **All artists** filter of the release list only sees the main artist of a release. The counts in the artist list and the artist page also include releases and tracks where the artist is a further credit, such as a remixer, and releases of others that carry one of the artist's tracks.
- The artist column of the track list shows the main artist of the track only, without further credits.
- The short edit dialog in the track list has no version, disc number or credits. Use **Details of …** on the release page for those.
- When you choose a track for an item, the release of that track is used, whatever release was selected. When you move an item to another release, its track is cleared.
- Changing only the contract override of an item does not move any transactions.
- An item can be deleted only when it has no transactions; the bin is shown only then.
- The `[Additionals]` item belongs to its release. It cannot be reassigned or deleted, and it follows the catalog number and UPC of the release.
- Recordings are combined on the track page by ISRC within your label only. Two tracks with the same title but different or missing ISRCs stay separate.
