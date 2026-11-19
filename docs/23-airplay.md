# Airplay

Where your tracks were played on the radio. Plays come from neighbouring rights statements, from a web research, or are entered by hand, and each one can collect links to playlists and articles. This page explains the list, the research and what it costs.

## Where plays come from

Every entry under **Catalog → Airplay** says in the column **Known from** how you know about it:

| Known from    | Meaning                                                                                                 |
| ------------- | ------------------------------------------------------------------------------------------------------- |
| **Statement** | A line of an imported neighbouring rights statement (Rightz Audio). The amount is what the play earned. |
| **Search**    | Found by a web research and kept by you.                                                                |
| **Manual**    | Entered by hand.                                                                                        |

### From a statement

A Rightz Audio statement has one line per collecting society, country and period. Some lines name the broadcaster, most do not.

- A line that names a station becomes a play **on that station** for the usage period.
- A line without a station becomes an **unidentified** play: you know the track earned money from a society in a country, but not where it ran. These are listed too, because they are the ones worth researching.

Amounts of several lines for the same track, station and period add up in one entry.

### By hand

**Add airplay** asks for the track (or a free track name and artist for something not in the catalog), the station, the country, the date or period and notes.

## Links

Each play can hold links: the station's playlist page, the show, an article. Open the link icon of an entry to

- paste a link with a title, or
- search the web for the play and keep results with one click.

## Research

A research looks a track up on Google through [DataForSEO](https://dataforseo.com) and reads the pages it finds.

### Setting it up

Two things are needed:

- A **DataForSEO login**, entered under **Settings → Connections → Web search**. It is checked when you save and stored encrypted; see [Connections](42-connections.md).
- **Airplay research countries**, under **Settings → Label**: two-letter country codes, for example `DE GB US`. Every track is searched once per country.

### What one research does

For a track by "Example Artist" called "Opening", with the countries `DE GB`:

1. Google is asked for `"Example Artist" "Opening" radio playlist` in Germany and in the United Kingdom, 30 results each.
2. Stations that are searched for every track in those countries are asked for by name, for example the BBC.
3. For each station the track is already known to have been played on, one more search names that station.
4. Unidentified plays in countries outside your list get a search in their country.
5. Every result page is opened and read. A result is only kept as a finding when the page names the artist or the title.

### Findings

Results appear in the panel **Findings to review**, each with what the check of the page said:

| Check                                   | Meaning                                                                                               |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| **Artist and title are on the page**    | The page was opened and names both                                                                    |
| **Only artist or title is on the page** | It names one of them                                                                                  |
| **Page does not name the track**        | The page was read and names neither. It is listed only when the search result itself named the track. |
| **Page could not be opened**            | The site did not answer or blocks automatic visits                                                    |

For a finding you choose **Keep** (it becomes a play, or a link on the play it belongs to) or **Dismiss**. A dismissed page is not suggested again.

### Starting a research

**Research** on the airplay page offers:

- **Not researched in the last 31 days**
- **One release**: all its tracks
- **Selected tracks**
- **Every track**

Each track is researched in the background. A panel on the airplay page shows which search is running for each track; you can leave the page.

A single play can be researched on its own with the radar button in its row: "it ran on FluxFM, find the show and the playlist".

Ten tracks a day are also researched automatically, the ones researched longest ago, if the scheduler runs.

### The research log

Every search is recorded under **Research log** with the date, the words searched, the country, how many new findings it brought and the error if it failed. Use it to see what was already tried before spending on a search again.

## What it costs

DataForSEO bills every search: ten results are one billed search, so the general search with 30 results counts as three. A track with two countries costs about 7 billed searches; at DataForSEO's live rate that is around one or two cents. Failed searches are not billed.

A research never starts on its own for a label without a login, and the daily automatic run is limited to ten tracks.

## The track page

**Catalog → Tracks → a track** shows everything known about one recording in one place: its plays, open findings, the research log and the income. Tracks that share an ISRC count as one recording there.

## Good to know

- "Internal SE Server Error" in the research log is DataForSEO's own search failing. It is retried up to three times and not billed.
- Station websites rarely keep playlists for long. A play from two years ago is often not findable any more; the statement is then the only record.
- Which stations are known by name, and how to add one, is described in [Extending](51-extending.md).
- A research needs the queue worker: see [Configuration](03-configuration.md). When it does not run, tracks stay "waiting".
