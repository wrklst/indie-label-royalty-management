# Transactions

Every amount in Label Royalties is a transaction: a line of income, an expense or a transfer. This page explains the types, the fields that decide how a transaction is counted, and how to enter and find transactions under **Royalties → Transactions**.

## Types

| Type     | What it is                                                                                                   | Sign                                                                      |
| -------- | ------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------- |
| Income   | Money a release or track earned: streams, downloads, physical sales, licences.                               | Positive. Returns and corrections are negative.                           |
| Expense  | A cost. Either recouped from payees or borne by the label alone, see below.                                  | Negative. Enter a cost as `-250`.                                         |
| Transfer | Money moved between the label and one payee outside of royalties: an advance, a payback, an opening balance. | Negative: given to the payee. Positive: owed to or returned by the payee. |

Most income arrives through imports, see [Importing statements](10-importing-statements.md) and [Bandcamp](11-bandcamp.md). Expenses and transfers are usually entered by hand.

### Recoupable expenses and expenses borne by the label

An expense has a switch, **Recoup from payees**:

- **On (recoupable).** The cost is deducted from the income of its release or track before the split, so every payee carries their share of it.
- **Off (borne by the label).** The label pays the cost alone. Payees earn as if it did not exist. The list marks these expenses with the badge **label cost**. Typical cases are one-time payments and buy-outs.

Example: a release earns 1,000 and has a mastering bill of 200. The artist has a 50% split.

|                    | Recoup from payees on | Recoup from payees off |
| ------------------ | --------------------- | ---------------------- |
| Net that is split  | 1,000 − 200 = 800     | 1,000                  |
| Artist share (50%) | 400                   | 500                    |
| Label keeps        | 400                   | 500 − 200 = 300        |

Only an expense can be switched off this way. Income and transfers ignore the switch.

A label-borne expense on a release counts against that release in the profitability report. One without a release is a label overhead; those are easier to enter under **Royalties → Label costs**. See [Profitability and label costs](33-profitability-and-label-costs.md).

### Transfers

A transfer always belongs to one payee and changes the balance of one of their ledgers directly. It is not income of a release and is not split.

- An advance of 500 is a transfer of `-500`. It lowers what the payee is owed; their later earnings recoup it.
- A payee paying 100 back to the label is a transfer of `100`.
- An opening balance carried over from another system is a transfer with the category **Previous Balance**, positive if you owe the payee and negative if they are unrecouped.

The **Ledger** field decides which balance changes. Left on **Automatic**, the transfer uses the ledger of the payee's split on the selected release, or the primary ledger. See [Contracts, payees and ledgers](30-contracts-payees-and-ledgers.md).

Payments of royalties you owe are not transfers. Record them as payouts on the period, where the amount is checked against what is payable. See [Periods, statements and payouts](32-periods-statements-and-payouts.md).

## Sale date and paid date

Every transaction has two dates:

- **Sale date** (just **Date** on expenses and transfers; column **Sold**): when the sale or cost happened.
- **Paid date** (column **Paid**): when the money arrived at the label or left it.

**The paid date decides which period a transaction counts in.** A distributor reports January streams in a statement that is paid in March or later. You can only pay your artists from money you have received, so a period settles what was paid in it, not what was sold in it.

Where each date is used:

| Place                                     | Date used                                          |
| ----------------------------------------- | -------------------------------------------------- |
| Periods, statements, balances             | Paid date, always                                  |
| Mechanicals, PPD and reserves tools       | Paid date, always                                  |
| Profitability report                      | Sale date by default; can be switched to paid date |
| Transaction list filter **From** / **To** | Sale date by default; can be switched to paid date |

## Currencies and conversion

Each label has one accounting currency, chosen when the label is created and shown under **Settings → Label**. All balances and statements are in that currency. It cannot be changed once the label has transactions.

A transaction keeps its original **Total** and **Currency**. When the currency differs from the label currency, the form shows **Rate to EUR** (or whatever your currency is): how much one unit of the transaction currency is worth in the label currency.

```text
converted amount = total × rate
120.00 USD × 0.90 = 108.00 EUR
```

A transaction in the label currency needs no rate.

A transaction in another currency without a rate is saved but **not counted anywhere**. The list shows it with a red badge such as `120.00 USD · no rate`, the filter **Unconverted** finds it, and the period page reports it. Open it, enter the rate and save.

## Categories and formats

**Category** says what kind of income, cost or transfer it is. The list offered depends on the type:

| Type             | Categories                                                                                                                                                                                                                                                                                                                                                                                                 |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Income           | 3rd Party License, Album License, Artist Copies, Direct to Fan/Show, Direct to Fan/Webstore, Distribution, Grant Funding, Master Use License, Master/Sync License (all in), Mechanicals, PPD, Publishing Royalties, Publishing Sync License, Refund, Reserves, Rights, Sample Usage License, Sub-Label, Ticket Sales/Show Fee, Wholesale                                                                   |
| Expense          | Advertising, Artwork/Design, Bio, Distribution Fee, Event, Fee, License Fee, Manufacturing (CD), Manufacturing (LP), Manufacturing (Misc.), Marketing, Mastering, Mechanicals, Mixing, Photos, PR, Radio, Recording - Mixing, Recording - Musician, Recording - Producer, Recording - Studio, Remix, Return Processing Fee, Rights, Shipping/Freight, Storage Fee, Studio, Ticket Buy, Tour Support, Video |
| Transfer         | Advance, Artist Merch Given, Artist Payback to Label, Event, Legal Services, Mastering, Mechanicals, Payout, Previous Balance, Studio, Tour Support                                                                                                                                                                                                                                                        |
| Label costs page | Subscriptions, Software, Hosting, Accounting, Legal, Bank Fees, Insurance, Office, Travel, Marketing, Fee, Other                                                                                                                                                                                                                                                                                           |

**Format** says how the music was sold. The form offers it for income. Every format belongs to one group:

| Group      | Formats                                                                                                                                                                                                                                                                                                                                                       |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Digital    | Ad-Supported Audio Stream, Ad-Supported Video Stream, Audiobook (Digital), Background, Breakage (Digital), Cloud/Locker, Creation, Digital/Other, Download Album, Download Track, Download/Other, eBook, Non-interactive Radio, Radio/Other, Streaming/Other, Subscription Audio Stream, Subscription Video Stream, User Generated Content (UGC), Video/Other |
| Physical   | 10" LP, 2xLP, 7" Single, Audiobook (Physical), Book, Box Set, Bundle (Physical), CD, DVD, Cassette, Flexidisc, Hardback, LP, Merchandise, Paperback, Physical/Other, Poster, T-Shirt                                                                                                                                                                          |
| Rights     | Performance Rights, Neighboring Rights, Rights/Other, Publishing Rights, Mechanical Rights, Sync, Blanket License                                                                                                                                                                                                                                             |
| Publishing | Mechanical, Performance, Publishing/Other, Print                                                                                                                                                                                                                                                                                                              |
| Reserves   | Reserves (Held), Reserves (Liquidated)                                                                                                                                                                                                                                                                                                                        |

Categories and formats do not change who is paid; contracts do that. Formats matter for the optional tools: mechanicals count download formats (and physical ones on request), and reserves are calculated on physical formats. See [Mechanicals, PPD and reserves](34-mechanicals-ppd-and-reserves.md). The lists come from `config/royalties.php`.

## Entering a transaction by hand

The buttons at the top of **Royalties → Transactions** open the same form with a different starting point: **Income**, **Expense**, **Transfer** and **One-time payment** (an expense with **Recoup from payees** switched off).

1. Choose the button for what you want to book.
2. In **Amount**, enter the **Total** with the right sign and the **Currency**. Enter the **Rate** if the currency is not your label currency.
3. Enter the **Sale date** (or **Date**) and the **Paid date**.
4. Pick a **Category**.
5. Fill in the second panel:
    - Income or expense: pick the **Release**, and a **Track** if it belongs to one track only. Leave **Track** on **Whole release** otherwise. For an expense, set **Recoup from payees** and optionally **Paid to**.
    - Transfer: pick the **Payee** (required), optionally a **Ledger** and a **Release**.
6. Under **Details** you can add **Source** and **Memo**, and for income also **Format**, **Quantity**, **Unit price**, **Retailer**, **Country** and **External ID**.
7. Choose **Create income**, **Create expense** or **Create transfer**.

Notes on single fields:

- **Release** on income or an expense: without a release the amount stays with the label and is reported as unassigned.
- **Contract override**: only for a transaction that follows a different deal than its track or release.
- **Paid to** on an expense is for your reference only, for example the designer who received a one-time payment. It does not change that payee's balance.
- **Country** is a two-letter code such as `DE`. Territory splits use it.
- **Memo** is shown to the payee on their statement.
- **Source** is set to "Manual" when left empty.

To change a transaction, use the edit button in its row. An imported transaction additionally shows **Original statement line**: the line exactly as it was in the file. To delete, use the delete button; the transaction is removed from every period and balance it counted in.

## The transaction list

The list shows 100 transactions per page with the columns **Sold**, **Paid**, **Type**, **Release / track**, **Retailer**, **Format / category**, **Region**, **Units** and **Amount**. Most headings sort the list. Above the table, **Matching**, **Units** and **Total** summarise everything that matches the filters, not only the current page.

| Filter                                          | What it does                                                                                                                       |
| ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| Search field                                    | Finds text in the memo, retailer or category, in the track name, release name or catalog number, an ISRC, or an exact external ID. |
| **All types**                                   | Income, expense or transfer.                                                                                                       |
| **All releases**                                | One release.                                                                                                                       |
| **All retailers**                               | One retailer.                                                                                                                      |
| **All imports**                                 | The transactions of one data import (the latest 100 imports are offered).                                                          |
| **Sale date** / **Paid date**, **From**, **To** | A date range on the chosen date.                                                                                                   |
| **Unassigned**                                  | Income and recoupable expenses without a release.                                                                                  |
| **Unconverted**                                 | Transactions without a conversion rate.                                                                                            |

**Clear filters** resets them.

### The track filter

The list can also be limited to one track, but there is no control for it in the filter bar. You reach it from the track: open the track under **Catalog → Tracks** and follow the link **… transaction(s)** under **Income**. The address then ends in `?track=` followed by the track's number.

The track filter includes every track that shares the same ISRC, so a recording that appears on a single and on an album is shown together. It can be combined with the other filters.

## Full precision amounts

Streaming lines are often worth fractions of a cent. Label Royalties never rounds them when storing or calculating:

- Totals are stored with up to 15 decimals, and all sums, splits and balances are calculated at that precision.
- The **Amount** column of the list shows up to six decimals, for example `€0.003271`.
- Summaries and balances are shown with two decimals. That is display only; the exact value is kept.
- Statement downloads round to four decimals.

Ten thousand lines of 0.0033 are therefore worth 33.00, not 0.00.

## Good to know

- Without a paid date inside a period, a transaction is in no period. Check the paid date first when income seems to be missing.
- A positive expense increases net income. The form warns you; use a negative amount for a cost.
- Choosing a **Track** sets the release to that track's release.
- A transaction entered by hand is booked on the release's "[Additionals]" item. An imported transaction keeps the item it was imported on unless you move it to another release or track. Items are explained in [Tracks, artists and items](21-tracks-artists-and-items.md).
- Editing or deleting a transaction changes the live figures of its period immediately. Statements already generated keep their figures until you regenerate them.
- The **Unassigned** filter leaves out transfers and label-borne expenses, because neither needs a release.
- **Total** in the summary adds up everything that matches, including transfers. Filter by type for a meaningful sum.
- The category **Payout** on a transfer is only a label. A transfer never appears under **Royalties → Payouts**.
- Transactions are edited one at a time; there is no bulk edit.
