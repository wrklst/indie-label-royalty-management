# Airplay

Where your tracks were played on the radio. Plays come from neighbouring rights statements, from a web research, or are entered by hand, and each one can collect links to playlists and articles. This page explains the list, the research and what it costs. Rightz Audio statements are only offered while the label works with Rightz Audio (**Settings → Connections**); plays already imported stay either way.

## Where plays come from

Every entry under **Catalog → Airplay** says in the column **Known from** how you know about it:

| Known from                        | Meaning                                                                                                 |
| --------------------------------- | ------------------------------------------------------------------------------------------------------- |
| **Neighbouring rights statement** | A line of an imported neighbouring rights statement (Rightz Audio). The amount is what the play earned. |
| **Web search**                    | Found by a web research and kept by you.                                                                |
| **Entered by hand**               | Entered by hand.                                                                                        |

### From a statement

A Rightz Audio statement has one line per collecting society, country and period. Some lines name the broadcaster, most do not.

- A line that names a station becomes a play **on that station** for the usage period.
- A line without a station becomes an **unknown** play: you know the track earned money from a society in a country, but not where it ran. These are listed too, because they are the ones worth researching.

Amounts of several lines for the same track, station and period add up in one entry.

### By hand

**New airplay** opens a dialog with these fields; **Create airplay** saves it.

| Field                           | Notes                                                                                                                             |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| **Track**                       | A track of the catalog, or **Not in the catalog**: then type the names below. A catalog track fills in its name, artist and ISRC. |
| **Track name**, **Artist**      | For something not in the catalog.                                                                                                 |
| **Station**                     | Leave empty when you do not know it yet; the play then shows **Unknown**.                                                         |
| **Country**                     | A two-letter code, for example `DE`.                                                                                              |
| **Played on / from**, **Until** | The day, or the first day of a period. Leave **Until** empty for a single day.                                                    |
| **Notes**                       | Show, presenter, how you found out.                                                                                               |

### The list

The table has the columns **Track**, **Artist**, **Station** (with media type, country and society underneath), **Known from**, **Played**, **Earned** and **Links**. Click a heading to sort; 50 plays fit on a page. The **Known from** badge of a statement play opens the import it came from; under it a line says whether the play was researched: **Not researched yet**, **Researched** with the date, **Waiting for research**, or the step a running research is at. A play from a statement has **Earned** from the statement; for the others the cell reads –.

At the end of a row are the radar button (research this play; see below), the pencil (**Edit airplay**) and the bin. The bin opens **Delete {track} on {station}?**: the play and its links and notes are removed, the income from the statement stays.

### Finding a play

Above the table, search a track, artist, station or ISRC, choose a **Track** or the origin under **Known from** (the three values above), and use **Clear filters** to start over. The **Track** filter also narrows the findings and the research log. **Manage airplay and findings** on a track page opens this page already narrowed to that track, and the filter shows it. The plays, the findings and the research log are separate lists with a pager each (25 to a page for the findings and the log).

## Links

Each play can hold links: the station's playlist page, the show, an article. Open the link icon of an entry to

- paste a link with a title, or
- search the web for the play and keep results with one click.

## Research

A research looks a track up on Google through [DataForSEO](https://dataforseo.com) and reads the pages it finds.

### Setting it up

Two things are needed:

- A **DataForSEO login**: the **DataForSEO API login** and **DataForSEO API password** on the **Web search** card under **Settings → Connections**, then **Test and connect**. It is checked when you save and stored encrypted; see [Connections](42-connections.md). Without it the buttons **Research** and the web search for a play's links are not shown.
- **Airplay research countries**, on the same card, then **Save countries**: two-letter country codes separated by spaces or commas, for example `DE GB US`. A label that never set them is searched in `DE`, `GB` and `US`. Every track is searched once per country.

### What one research does

For a track by "Example Artist" called "Opening", with the countries `DE GB`:

1. Google is asked for `"Example Artist" "Opening" radio playlist` in Germany and in the United Kingdom, 30 results each.
2. Stations that are searched for every track in those countries are asked for by name, for example the BBC.
3. For each station the track is already known to have been played on, one more search names that station. At most two stations are searched this way.
4. Plays at an unknown station in countries outside your list get a search in their country, again at most two.
5. Results from shops, streaming services, lyric sites and social networks are dropped. A result is only kept as a finding when its title, text or address names the artist or the title.
6. The first 15 new findings of a run have their page opened and read. The rest are kept without that check and show **Page not checked**.

A run stops starting new searches after seven minutes, and a search that fails does not stop the others.

### Findings

Results appear in the panel **Findings to review (N)**, which is only there while there are any, each with what the check of the page said:

| Check                                   | Meaning                                                                                               |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| **Artist and title are on the page**    | The page was opened and names both                                                                    |
| **Only artist or title is on the page** | It names one of them                                                                                  |
| **Page does not name the track**        | The page was read and names neither. It is listed only when the search result itself named the track. |
| **Page could not be opened**            | The site did not answer or blocks automatic visits                                                    |
| **Page not checked**                    | The page was not opened: only the first 15 findings of a run are.                                     |

Beside that, a badge says what the finding looks like: the station when one is recognised, **Looks like radio** when the text mentions radio, a playlist or similar, otherwise **Mention**. Where a station's page says which show it was, the show is quoted too.

For a finding you choose **Keep** (it becomes a play, or a link on the play it belongs to) or **Dismiss**. A dismissed page is not suggested again.

### Starting a research

**Research** on the airplay page offers:

- **Not researched in the last 31 days**
- **One release**: all its tracks
- **Selected tracks**
- **Every track**

**Start research** queues the tracks. Each track is researched in the background. The panel **Research in progress: N running, M waiting** shows which search each one is at and refreshes itself every few seconds; you can leave the page. If nothing is running while tracks wait, the queue worker is not started. A track that failed stays listed with the reason.

A single play can be researched on its own with the radar button in its row: "it ran on FluxFM, find the show and the playlist". The links dialog of a play also has **Search the web** with words you can change.

Ten tracks a day are also researched automatically at 06:30, the ones researched longest ago, if the scheduler runs; see [Configuration](03-configuration.md). `royalties:research-airplay` does the same by hand; see [The command line](50-command-line.md).

### The research log

Every search is recorded under **Research log**, which is only there once a search was run, with the columns **When**, **For** (the track, or the play it was for), **Searched** (the words and the country) and **Result**: how many results and new findings, which you can open to read the results, or the error if it failed. Use it to see what was already tried before spending on a search again.

## What it costs

DataForSEO bills every search: ten results are one billed search, so the general search with 30 results counts as three. A track with two countries costs about 7 billed searches; at DataForSEO's live rate that is around one or two cents. Failed searches are not billed.

A research never starts on its own for a label without a login, and the daily automatic run is limited to ten tracks.

## The track page

**Catalog → Tracks → a track** shows the plays of one recording, a tile with the number of findings to review and when it was last researched, the research log and the income; the findings themselves are decided here on the Airplay page. Tracks that share an ISRC count as one recording there.

## Good to know

- "Internal SE Server Error" in the research log is DataForSEO's own search failing. It is tried up to three times and not billed.
- Station websites rarely keep playlists for long. A play from two years ago is often not findable any more; the statement is then the only record.
- Which stations are known by name, and how to add one, is described in [Extending](51-extending.md).
- A research needs the queue worker: see [Configuration](03-configuration.md). When it does not run, tracks stay "waiting". A research that has been "running" for more than 15 minutes is treated as dead and disappears from the progress panel.
