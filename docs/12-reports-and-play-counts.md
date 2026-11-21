# Reports and play counts

Every page that has income shows it in the same report: the dashboard for the whole label, and the pages of a release, a track, an artist, an import and a period for their part of it. Play counts are what the platforms themselves say was played; the report holds them against your statements.

## The report

**How the income developed.** One column per month, stacked by platform. Three views:

| View           | Shows                                                          |
| -------------- | -------------------------------------------------------------- |
| **Income**     | What each platform brought in per month.                       |
| **Streams**    | How many streams the statements report per month.              |
| **Per stream** | What one stream paid on each platform, as a line per platform. |

Point at a month to read its figures, and at a platform to follow it through the chart. The eight platforms with the most income have a colour of their own; the rest are **Other**. A platform has the same colour on every page.

**Platforms.** One row per platform:

| Column                      | Meaning                                                                          |
| --------------------------- | -------------------------------------------------------------------------------- |
| **Income**, **Share**       | What the platform brought in, and its part of the whole.                         |
| **Streams**                 | Streams the statements report.                                                   |
| **Per stream**              | Income from streams divided by streams.                                          |
| **Trend**                   | How that rate went month by month.                                               |
| **Downloads**               | Downloads sold.                                                                  |
| **Per download**            | Income from downloads divided by downloads.                                      |
| **Counted by the platform** | Plays from the platform's own figures, once you imported some (see below).       |
| **On statements**           | Streams the statements report for the same months, and their share of the count. |

Statements name platforms in many ways. `Apple` and `Apple/iTunes` are one row, **Apple Music**. A shop the application does not know keeps the name the statement uses.

**What it was.** Income by kind of use: streams, downloads, use in videos (such as on TikTok or Facebook), records and merchandise, rights and licences.

**Where it was.** The twelve countries with the most income.

**Releases that earned most** (dashboard and artist page) and **Tracks by income** (release page). Each row has a line of its last twelve months, the last three months held against the three before, its streams and what a stream paid. A row that earned nothing in the last three months is marked **quiet**.

### Example

On the page of the track "Example Track" the Platforms table shows Spotify with 6,000 streams and €18.00, so **Per stream** reads €0.003. Apple Music shows 1,200 streams and €6.00, so €0.005.

## Play counts

A platform pays for streams months after they happened, and only for those it counts as paid. Its artist portal shows what it counted itself. Importing that count lets the report show both side by side.

No platform offers these figures to a label's own software. They have to be downloaded by hand:

| Platform                 | Where the figures are                                           |
| ------------------------ | --------------------------------------------------------------- |
| Spotify for Artists      | **Music → Songs**, then the download arrow above the table.     |
| Apple Music for Artists  | **Music → See All**; set the date range, then download the CSV. |
| Amazon Music for Artists | Set the date range on a report page and export it.              |
| Zebralution              | **Analytics → Datenexport**, set as described below.            |

### The Zebralution export

Zebralution counts every platform it delivers to, so one export covers most of them.

The short way: sign in to Greenhouse, Zebralution's portal, then on **Royalties → Play counts** follow the link **download the streams since the last import**. It asks Zebralution for the streams by track, platform and day, from a week before the last counted day until yesterday, or for the last year when nothing was counted yet. The week is fetched again because the latest days are still filling up; the import replaces them.

By hand: in its analytics, set the date filter to the months you want and remove every other filter, then open **Datenexport** and tick:

| Section         | Tick                           | Leave empty                                             |
| --------------- | ------------------------------ | ------------------------------------------------------- |
| **Gruppierung** | **Tracks**, **DSP**, **Datum** | Releases, Land, Firma, Label, Künstler\*innen, Playlist |
| **Datenfelder** | **Streams**                    | Downloads, Views, Creations                             |

**Tracks** puts the ISRC on each line, which is what a line is matched by. **DSP** names the platform, so each count is held against that platform's statement lines. **Datum** gives the days, so a later export replaces exactly the days it covers.

With **DSP** ticked the export takes only the kind of figure that is active in the analytics view, so have streams showing there. If the export does not let **DSP** be combined with **Tracks**, tick **Tracks** and **Datum** only: the file then has all platforms in one count and is listed as **Not named**. Country and playlist are not stored: ticking them only makes the file longer. An export filtered to one country or playlist would replace the full figures of those days with partial ones.

### Importing a file

1. Open **Royalties → Play counts**.
2. Choose the file: CSV or Excel.
3. **Import play counts**.

Nothing else is asked. The platform is the one the lines name, otherwise the one in the file name (`spotify-songs.csv`), otherwise the one the columns give away. A file that names none, such as a distributor's export of all platforms together, is listed as **Not named**. So is a platform the application has no name for. A file without dates counts as the total up to the day it is imported and replaces every earlier count of its tracks on that platform.

The file is read by what its columns are called, not by where it comes from:

| The file needs          | Accepted headers                                     |
| ----------------------- | ---------------------------------------------------- |
| The track               | `track`, `song`, `title`, `name`, or `isrc`          |
| The count               | `plays`, `streams`, `play count`, `listens`, `views` |
| Optional: listeners     | `listeners`                                          |
| Optional: artist        | `artist`                                             |
| Optional: platform      | `platform`, `service`, `store`, `dsp`                |
| Optional: day or period | `date`, or `from` and `to`                           |

A line is matched to a track by its ISRC, otherwise by its name. Punctuation does not matter: `Example Track - Example Remix` is the track `Example Track (Example Remix)`. Where several recordings share a name, the artist column decides. A recording on several releases is counted once.

Lines whose track is not in the catalogue are kept and listed under the file as **Not in the catalogue under that name**. They count for the label as a whole, not for a track.

A newer file replaces earlier counts of the same track and platform for the days it covers, so nothing is counted twice. **Delete** removes everything one file brought; statements and income are not touched.

### The report on the page

Below the upload, **Royalties → Play counts** shows what was counted, drawn like the income report:

- **How the plays developed**: the counted plays month by month, stacked by platform.
- **Platforms**: what each platform counted, its share, its trend, the streams the statements have for the same months, and the days the count covers.
- **Releases by plays** and **Tracks by plays**: the twelve that were played most, each with its last twelve months and the last three months held against the three before. A recording on several releases counts for the release of its first entry.

Country and playlist are not kept, so there is no breakdown by them.

### Reading the comparison

The count and the statements rarely match:

- Statements arrive late. The last months of a count are often not on a statement yet.
- A platform counts every play; it pays only for plays above its thresholds.
- The comparison is by month. A count that starts or ends inside a month is held against the whole month.

A share far below 100% for months that are fully reported is worth a question to your distributor.
