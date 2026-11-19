# Introduction

Label Royalties is royalty accounting and release management for independent record labels, seen from the label's side: what came in, what each artist is owed, what the label keeps. This page explains the ideas the rest of the handbook builds on and walks through a first session.

## What the software does

- **Imports the statements you already get.** Distributor statements, Bandcamp sales reports, neighbouring rights statements and a generic spreadsheet template are read as CSV or Excel. Releases, tracks and artists are created from the files.
- **Splits income by contract.** A contract says which payee gets which share. Expenses can be recouped before anyone is paid.
- **Closes accounting periods.** A period shows what every payee has earned, what is still unrecouped and what is payable. Statements are frozen snapshots a payee can read in their own portal.
- **Keeps the catalog together.** Every release holds its metadata, cover, audio masters and documents. Audio plays in the browser.
- **Tracks airplay.** Radio plays from neighbouring rights statements are listed per track and can be researched on the web.

## The words used everywhere

| Word                | Meaning                                                                                                                               |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| **Label**           | One tenant of the installation, with its own catalog, contracts, transactions and team. An installation can hold several labels.      |
| **Admin**           | A user who runs a label and sees everything in it.                                                                                    |
| **Payee**           | Anyone who is paid a share: an artist, a producer, a publisher, another label. A payee can be given a login to read their statements. |
| **Release / track** | The catalog. A track belongs to one release; the same recording on several releases is recognised by its ISRC.                        |
| **Item**            | What is sold: a release or track in a format group (digital, physical, rights …). Transactions are matched to items.                  |
| **Contract**        | An agreement with percentage splits per payee. It can be set on a release, a track, an item or a single transaction.                  |
| **Ledger**          | The running balance of a payee under a contract. A negative ledger is unrecouped and carries forward.                                 |
| **Transaction**     | One line of money: income, an expense, or a transfer such as an advance or a payout.                                                  |
| **Paid date**       | The day the money reached the label. It decides which period a transaction counts in.                                                 |
| **Period**          | A span of paid dates you account for, for example a quarter.                                                                          |
| **Statement**       | What one payee earned in one period, frozen at the moment it was generated.                                                           |

## How the pieces fit together

1. A statement file is imported. Every line becomes a transaction on a release, a track and an item.
2. The transaction's contract is looked up: the one on the transaction itself, else the item's, else the track's, else the release's. Income without a contract stays with the label.
3. The contract's splits book each payee's share on a ledger.
4. A period sums the ledgers for its paid dates. Positive ledgers are payable, negative ones carry forward.
5. You generate statements, publish them, and record payouts.

The arithmetic is explained with numbers in [Periods, statements and payouts](32-periods-statements-and-payouts.md).

## A first session

This assumes the software is installed and you can sign in. If not, start with [Installation](02-installation.md).

1. **Create your label.** After the first sign-in you are asked for the label's name and accounting currency. The currency cannot be changed once transactions exist.
2. **Import a statement.** Open **Royalties → Data imports**, choose **Import a statement** and upload a file from your distributor. The source is detected from the columns. See [Importing statements](10-importing-statements.md).
3. **Look at what was created.** **Catalog → Releases** now lists the releases named in the file. Open one to see its tracks and its income by track and retailer.
4. **Add a payee and a contract.** Under **Catalog → Payees** create the artist, then under **Catalog → Contracts** a contract that gives them, say, 50%. See [Contracts, payees and ledgers](30-contracts-payees-and-ledgers.md).
5. **Assign the contract.** Open **Catalog → Catalog manager** and set the contract on the release. Every transaction of that release now follows it.
6. **Create a period.** Under **Royalties → Periods** create the quarter the statement was paid in. The period lists each payee with their share.
7. **Generate and publish statements**, then record the payout once you have paid.

> **Note:** Nothing is final until you generate statements. You can import, delete an import, change contracts and look at the period again as often as you like.

## Where to find help

- The **?** button in the top bar opens the handbook page for the screen you are on, beside your work.
- **Overview → Handbook** shows every chapter with a search field.
- Something not working: see [Troubleshooting](52-troubleshooting.md).

## Good to know

- Amounts are stored at full precision. Streaming lines worth fractions of a cent are never rounded away; rounding happens only where a figure is shown.
- Buyer names, emails and addresses in Bandcamp reports are never stored.
- The handbook pages are Markdown files in the `docs` folder of the source code, so they can be read on GitHub as well.
