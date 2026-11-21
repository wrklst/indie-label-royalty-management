# Importing statements

Most money enters the books through an import: you upload the statement a distributor, shop or collecting society sent, and every line becomes a transaction. This page covers the supported sources, the upload form, the check for duplicates and how to undo an import.

## Supported sources

| Source                                        | File                                                                          | Paid date                      |
| --------------------------------------------- | ----------------------------------------------------------------------------- | ------------------------------ |
| **Zebralution**, track statement from 2024 Q1 | The quarterly `statement_label_track` file from the licensor portal           | You enter it                   |
| **Zebralution**, legacy layout before 2024 Q1 | The older file with "Period Sold", "Provider", "Shop" and "Rev.less Publ.EUR" | You enter it                   |
| **Bandcamp**                                  | The raw sales report (**Tools → Sales report** on Bandcamp)                   | Taken from each sale           |
| **Rightz Audio**                              | The neighbouring rights statement as downloaded (Excel)                       | You enter it                   |
| **Audio rights (neighbouring rights)**        | Any neighbouring rights income, in the template layout                        | You enter it                   |
| **Spreadsheet template**                      | Your own CSV or Excel file in the template layout                             | From the file, or you enter it |

Files can be CSV or Excel (`.xlsx`), up to 50 MB. The encoding, the separator and title lines above the header are worked out for you.

Bandcamp sales can also be fetched automatically, without uploading anything: see [Bandcamp](11-bandcamp.md). A source that is missing can be added with a small class: see [Extending](51-extending.md).

## Importing a file

1. Open **Royalties → Data imports** and choose **Import a statement**.
2. **Where is the file from?** Leave it on automatic detection, or pick the source. The layout is recognised by the columns of the file, so the two Zebralution layouts are one entry.
3. **File.** Drop the file on the field or click **browse**. It is sent when you choose **Import**. The name of the import defaults to the file name.
4. **Dates.** Enter the **Paid date**: the day the money reached your bank account. It decides which accounting period the lines count in. For sources that carry no payment date it is required. Tick **Use this paid date for every line** to override dates the file does contain.
5. **Options.**
    - **Conversion rate**: only for lines in a currency other than the label's. Without a rate those lines are imported but not counted until you set one.
    - **Tags**: free words to find the import again.
    - **Skip lines that were already imported**: leave it on. It protects you from uploading the same statement twice.
6. Submit. The file is checked and imported in one go. If anything stops the import, nothing is saved.

### Example

Example Records receives its Zebralution statement for the third quarter on 15 November 2026. The file has 4,210 lines.

1. Upload `statement_label_track_2026Q3.csv`, paid date `2026-11-15`.
2. The import page shows 4,210 lines imported and the total, followed by the report: how the income developed month by month, by platform, by kind of use and by country; see [Reports and play counts](12-reports-and-play-counts.md).
3. Two releases in the file were not in the catalog yet. They now exist under **Catalog → Releases**, without a contract. The period for the fourth quarter will point them out as "releases without a contract".

## What an import creates

- A **transaction** for each line, with its amount at full precision, the retailer, the country and the format.
- **Releases, tracks and artists** that the file names and the catalog does not have yet. They are matched by UPC, catalog number and ISRC first, by name after that.
- **Items**: the release or track in a format group (digital, physical, rights). See [Tracks, artists and items](21-tracks-artists-and-items.md).
- For Rightz Audio statements, an entry under **Catalog → Airplay** for each line: see [Airplay](23-airplay.md).

The original line of the file is kept with every transaction, without personal data. Buyer names, emails and addresses in Bandcamp reports are discarded before anything is stored.

## What was deducted before the money arrived

A statement reports what the label receives. Some also say what was paid in the first place and who kept what on the way. Where a file says so, every transaction keeps three more figures:

| Figure                                       | Zebralution                                                | Bandcamp                                                 | Rightz Audio                             |
| -------------------------------------------- | ---------------------------------------------------------- | -------------------------------------------------------- | ---------------------------------------- |
| **Paid by customers**                        | "Amount Distributable": what the shop paid the distributor | "item total": what the buyer paid, with shipping and tax | Your amount plus the agency's commission |
| **Kept by distributors, shops and agencies** | The part the contract share leaves to Zebralution          | Bandcamp's revenue share                                 | "Total RightzAudio"                      |
| **Kept by payment providers**                | not reported                                               | The payment provider's fee                               | not reported                             |

The import page, every period and the dashboard add these up in the panel **Where the money went**:

| Line                                                                   | Example    |
| ---------------------------------------------------------------------- | ---------- |
| Paid by customers                                                      | 100.00     |
| Kept by distributors, shops and agencies                               | −20.00     |
| Kept by payment providers                                              | −4.00      |
| Other deductions (taxes, shipping, mechanicals passed on, payout fees) | −6.00      |
| Received without a breakdown                                           | 30.00      |
| **Received by the label**                                              | **100.00** |

A period continues from there with **Costs**, **Owed to payees** and **Kept by the label**; see [Periods, statements and payouts](32-periods-statements-and-payouts.md).

"Received without a breakdown" is income from statements that only report the amount paid out: the legacy Zebralution layout, the spreadsheet template, entries made by hand and anything migrated from another system.

## Lines that are left out

The import page lists them under **Lines left out**, with the reason:

- lines that were imported before (same source, same line),
- lines that are not sales, such as a Bandcamp payout to your bank account,
- Bandcamp physical and merch sales, when the label chose **Skip physical and merch sales** in its settings.

## Possible duplicates

The same money sometimes reaches the books twice in different shapes: you entered a statement by hand as one sum per release, and months later import the detailed file. The amounts match, the lines do not, so the "already imported" check cannot see it.

Every import is therefore compared with what is booked already. Two things count as a possible duplicate:

- The lines of the file for one release, type, format group and paid month **add up to** bookings from another source, to the cent.
- One line has the **same paid date and amount** as an existing booking of that release.

When something is found, the import stops with the status **Duplicates to review** and nothing is counted yet. For each proposal you choose:

| Choice                                                              | Result                                                                                                                                                      |
| ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Merge: keep the lines of this file, remove the existing booking** | The detailed lines replace the earlier booking. If that booking had a contract, payee or ledger of its own, the lines take those over. This is the default. |
| **Leave these lines of the file out, keep the existing booking**    | The file's lines for this release are not imported.                                                                                                         |
| **Not a duplicate: keep both**                                      | Both stay. Use it when two payments really were the same amount.                                                                                            |

The decisions are kept on the import page under **Duplicates decided at import**.

## The template

On the upload form, choosing the spreadsheet template as the source shows the link **Download the CSV template**. The file has these columns. Only `total` is required.

| Column                                 | Example                      | Notes                                                |
| -------------------------------------- | ---------------------------- | ---------------------------------------------------- |
| `timestamp`                            | `2026-03-31`                 | Sale date                                            |
| `paid timestamp`                       | `2026-04-15`                 | Paid date                                            |
| `transaction type`                     | `income`                     | `income`, `expense` or `transfer`                    |
| `total`                                | `125.50`                     | Costs are negative amounts                           |
| `currency`                             | `EUR`                        | Defaults to the label's currency                     |
| `payee`                                | `Example Artist`             | For transfers such as advances                       |
| `artist`, `release name`, `track name` |                              | Matched to the catalog, created when missing         |
| `catalog number`, `isrc`, `upc`        | `EX001`                      | Preferred over names for matching                    |
| `item id`, `contract id`               |                              | To book on a specific item or contract               |
| `memo`                                 | `Sync fee`                   | Free text                                            |
| `source`                               | `Publisher`                  | Who paid                                             |
| `category`, `format`                   | `Master Use License`, `Sync` | From the lists in [Transactions](31-transactions.md) |
| `quantity`, `price`                    | `1`                          |                                                      |
| `retailer`                             | `Publisher`                  |                                                      |
| `external id`                          |                              | The other side's reference                           |
| `region`                               | `DE`                         | Two-letter country code                              |

## Undoing an import

Open the import and choose **Delete import**. That removes exactly the transactions the import created, nothing else. Releases and tracks it created stay, because other data may refer to them by now.

To import a corrected file, delete the old import first and upload the new file.

## From the command line

```sh
php artisan royalties:import statement.csv --label=example-records --paid=2026-11-15
php artisan royalties:import report.xlsx --label=example-records --importer=bandcamp --rate=0.92
```

`--label` takes the label's slug or id. When the command finds possible duplicates it leaves the import in **Duplicates to review**, to be decided in the interface.

## Good to know

- A transaction counts in the period its **paid date** falls in, not the period it was sold in. Enter the date the money arrived, not the date on the statement.
- Lines in a foreign currency without a conversion rate are stored but count as zero until a rate is set. A period lists them as "unconverted currencies".
- By default 1% is taken off each Bandcamp line to cover payout fees. Switch it off under **Settings → Label** if you do not want that.
- An import runs while you wait. A file with tens of thousands of lines takes a minute or two.
- Statement files are kept on the configured file storage, so an import can be traced back to the file it came from.
