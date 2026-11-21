# Periods, statements and payouts

An accounting period is the time span you settle with your payees, usually a quarter or a half year. This page covers creating a period, checking it, how the figures are calculated, generating and publishing statements, and recording payouts, with a full example over two periods.

## The routine

1. Import the statements that were paid in the period and enter expenses and advances. See [Importing statements](10-importing-statements.md) and [Transactions](31-transactions.md).
2. Create the period under **Royalties → Periods**.
3. Work through **1. Check the data** on the period page until nothing is listed.
4. Generate the statements, read them, publish them.
5. Pay your payees and record each payment as a payout.

## Creating a period

1. Open **Royalties → Periods** and choose **New period**.
2. Enter a **Name** and the dates **From** and **To**. The form suggests the quarter after your latest period, or the last full quarter if you have none.
3. Choose **Create period**.

Rules:

- A transaction counts in the period its **paid date** falls in. See [Transactions](31-transactions.md) for why.
- Periods may not overlap, because a transaction may only count in one period. They may follow one another without a gap, and a period may be a single day.
- You can create earlier periods afterwards. Balances always carry forward by date, not by the order in which periods were created.

**Edit** on the period page changes the name and dates. Once a period has payouts, its dates can no longer change.

## Checking a period

The period page is a live calculation. Its heading shows how many transactions were paid in the period, and four figures summarise it:

| Figure           | Meaning                                                                                          |
| ---------------- | ------------------------------------------------------------------------------------------------ |
| **Income**       | Income paid in the period. Below it: **Expenses**, the recoupable ones.                          |
| **Payee share**  | What all payees earned in the period. Below it: **Label keeps**, after the label's own costs.    |
| **Payable**      | What you owe payees in total, including positive balances **carried over** from earlier periods. |
| **Still to pay** | Payable minus the payouts recorded for this period. Below it: **Paid**.                          |

The panel **1. Check the data** lists anything that earns nothing for payees until it is fixed:

| Message                                                                               | What it means                                                                                                                                       | How to fix it                                                                                                            |
| ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| "… transactions are not assigned to a release."                                       | Income or recoupable expenses without a release. No contract can apply.                                                                             | **Assign items** opens **Catalog → Items** to assign the items they were imported on; **show transactions** lists them.  |
| "… transactions have no conversion rate to EUR and are left out."                     | Transactions in another currency without a rate. They are not counted at all.                                                                       | **Show them**, then enter the rate on each.                                                                              |
| "… transactions are on releases without a contract, so the label keeps them in full." | The release is known, but no contract is set on the transaction, item, track or release. Up to twelve of the releases are listed with their totals. | **Assign contracts** opens the catalog manager. See [Contracts, payees and ledgers](30-contracts-payees-and-ledgers.md). |

When all is well it says "Everything is assigned to a release, covered by a contract and converted." An empty period says "No transactions were paid in this period."

These are warnings, not locks. You can generate statements with open issues, but the amounts concerned stay with the label.

## Where the money went

Below the four figures at the top, a period shows the path of its income in one panel, from the label's point of view:

| Line                                             | Meaning                                                                             |
| ------------------------------------------------ | ----------------------------------------------------------------------------------- |
| **Paid by customers**                            | What platforms and buyers paid, for the lines whose statement reports it            |
| **Kept by distributors, platforms and agencies** | The distributor's share, Bandcamp's revenue share, a collecting agency's commission |
| **Kept by payment providers**                    | Payment fees, where reported                                                        |
| **Other deductions**                             | Taxes, shipping, mechanicals passed on and payout fees                              |
| **Received without a breakdown**                 | Income whose statement does not say what was deducted before                        |
| **Received by the label**                        | The income of the period                                                            |
| **Costs**                                        | Expenses paid in the period, recoupable ones and the label's own                    |
| **Owed to payees**                               | Their shares under the contracts, before earlier balances and payouts               |
| **Kept by the label**                            | What remains                                                                        |

Which statements report deductions is listed in [Importing statements](10-importing-statements.md).

## How the figures are calculated

For every payee and every ledger:

```text
net            = income + recoupable expenses        (expenses are negative)
share          = net × split                          (per release, track and contract)
balance        = brought forward + share + transfers − payouts
```

In detail:

- **Income and expenses** are the transactions whose paid date is inside the period. Each uses the most specific contract set on the transaction, item, track or release.
- **Expenses borne by the label** are left out of the net. They only reduce what the label keeps.
- **Brought forward** is the ledger's balance before the period: everything earned, transferred and paid out earlier.
- **Transfers** are advances (negative) and paybacks (positive) paid in the period.
- **Payouts** are the payments recorded for this period.
- A ledger below zero is **unrecouped**. It is not payable, it carries forward, and it is never set off against the payee's other ledgers.

The columns of the payee table under **2. Statements and payouts** add up the ledgers of each payee:

| Column           | Meaning                                                                                        |
| ---------------- | ---------------------------------------------------------------------------------------------- |
| **Carried over** | Sum of the ledgers that were above zero before the period. Unrecouped ledgers count as 0 here. |
| **Share**        | Earned in the period, on all ledgers.                                                          |
| **Transfers**    | Transfers paid in the period.                                                                  |
| **Payable**      | For each ledger, brought forward + share + transfers; only ledgers above zero are added up.    |
| **Paid**         | Payouts recorded for this period.                                                              |
| **Still to pay** | Sum of the ledgers above zero after payouts.                                                   |
| **Statement**    | **–** (none), **Draft** or **Published**.                                                      |

A payee with a ledger below zero is marked **unrecouped ledger**.

## The per-payee view

Click a payee's name in the table to open their breakdown for the period. It shows the same content as a statement, but live:

- **Summary**: **Brought forward**, **Share this period**, **Paid out**, **Still payable**, and a table of all ledgers.
- One tab per ledger: earnings per release and track with **Contract**, **Split**, **Income**, **Expenses**, **Net** and **Share**, followed by how the ledger got from **Balance brought forward** to its **Balance**.
- **Income**: grouped by release, track and format, with units.
- **Expenses**: the recoupable costs and the payee's part of each.
- **Transfers & payouts**: every advance, payback and payout in the period.

Use this view to answer "why is this number what it is" before you generate anything.

## Generating statements

A statement is a frozen copy of a payee's figures for a period. Later imports, edits or contract changes do not alter it.

- **Generate all statements** on the period page creates one for every payee who has earnings, transfers, payouts or a balance up to this period. Payees with nothing at all get none.
- The icon in a payee's row, or **Generate statement** on their breakdown, creates one for that payee only.

A new statement is a **Draft**: only admins can see it.

### Revisions

Generating again creates a new revision instead of changing the old statement:

- If the current statement is a draft, it is archived and replaced by the new draft.
- If the current statement is published, it stays with the payee and the new draft waits next to it. When you publish the revision, the earlier one is archived.
- A statement that replaced an earlier one is marked **Revised** in the list.
- **Regenerate all statements** also archives the draft of a payee who no longer has anything to state.

**Royalties → Statements** lists the current statements with **Payee**, **Period**, **Status**, **Generated**, **Balance** and **Payable**. Tick **Include archived revisions** to see the history. An archived revision cannot be published. The button **My statements** above the list shows what payees see of the published ones.

## Publishing and downloading

Open a statement and choose **Publish to payee**. From then on the payee sees it under **My statements**, see [Team, roles and sign-in](40-team-roles-and-sign-in.md). **Unpublish** takes it back.

Publishing sends no email. Tell your payees yourself.

**Download CSV** saves the statement as a CSV file, the only download format. It opens in any spreadsheet program and contains, in this order:

1. the payee, period and currency,
2. **Summary**: Previous balance, Income, Expenses, Net, Your share, Transfers, Payable, Paid, Balance,
3. one block per ledger, with a line per release and track, the ledger total, its transfers and payouts,
4. **Income** by release, track, contract and format with quantity,
5. **Expenses** by release, track, contract and category,
6. **Transfers and payouts** with date and memo.

Amounts in the file are rounded to four decimals. Payees can download the same file from **My statements**.

## Payouts

A payout is the record of a payment you have made. Nothing is sent to a bank from here.

1. On the period page, choose the payout icon (**Book payout**) in the payee's row. It only appears when something is still to pay.
2. Enter the **Amount**, the **Paid date** and an optional **Memo** such as the bank reference.
3. Choose **Book payout**.

Rules:

- **A payout cannot exceed what is payable.** The form shows "Up to … is payable." A higher amount is refused. Amounts have at most two decimals.
- **A payout is deducted in the period it settles**, which is the period you record it on, not the period of its **Paid date**. A payment made in April for the first quarter is recorded on the first-quarter period and reduces that period's **Still to pay**.
- It is booked against the payee's ledgers, largest balance first. Unrecouped ledgers are never touched.
- An amount already paid on a later period cannot be paid again on an earlier one.

**Royalties → Payouts** lists all payouts with the ledgers they were **Booked to** and who recorded them.

## Deleting

| What                                      | Effect                                                                                              | Limits                                                                  |
| ----------------------------------------- | --------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| A payout (**Royalties → Payouts**)        | The amount becomes payable again.                                                                   | Statements already generated keep showing it until you regenerate them. |
| A statement (**Delete** on the statement) | The payee loses access to it. Transactions and payouts are not affected; you can generate it again. | None. Published statements can be deleted too.                          |
| A period (**Delete** on the period page)  | Its statements are deleted with it. Transactions are not touched.                                   | Not possible while the period has payouts or published statements.      |

## Worked example: an advance recouped over two periods

Example Records accounts in EUR. Example Release (EX001) has the contract "EX001 album deal":

- Example Artist 50%, booked on the ledger "EX001 advance",
- Second Payee 10%, on the primary ledger,
- the label keeps 40%.

### Period "2026 Q1" (1 January to 31 March 2026)

| Paid date | Transaction                                             | Amount |
| --------- | ------------------------------------------------------- | ------ |
| 15 Jan    | Transfer "Advance" to Example Artist, release EX001     | −1,000 |
| 10 Feb    | Expense "Mastering", recouped from payees               | −400   |
| 12 Feb    | Expense "Artwork/Design", label cost borne by the label | −300   |
| 20 Mar    | Income                                                  | 2,000  |

Net is 2,000 − 400 = 1,600. The artwork is not part of it.

| Payee          | Ledger        | Brought forward | Share | Transfers | Balance           | Payable |
| -------------- | ------------- | --------------- | ----- | --------- | ----------------- | ------- |
| Example Artist | EX001 advance | 0               | 800   | −1,000    | −200 (unrecouped) | 0       |
| Second Payee   | Primary       | 0               | 160   | 0         | 160               | 160     |

The period page shows **Income** 2,000 with **Expenses** −400, **Payee share** 960, **Label keeps** 340 after 300 own costs (1,600 − 960 − 300), and **Payable** 160.

You generate and publish both statements. Example Artist's statement shows a share of 800 and a balance of −200. On 15 April you pay Second Payee and book a payout of 160 on "2026 Q1". **Still to pay** drops to 0.

### Period "2026 Q2" (1 April to 30 June 2026)

| Paid date | Transaction | Amount |
| --------- | ----------- | ------ |
| 20 Jun    | Income      | 1,500  |

| Payee          | Ledger        | Brought forward    | Share | Transfers | Balance | Payable |
| -------------- | ------------- | ------------------ | ----- | --------- | ------- | ------- |
| Example Artist | EX001 advance | −200               | 750   | 0         | 550     | 550     |
| Second Payee   | Primary       | 0 (160 − 160 paid) | 150   | 0         | 150     | 150     |

The first 200 of Example Artist's share recoups the rest of the advance. The advance is now fully recouped and 550 is payable. **Payable** for the period is 700. **Carried over** is 0 for Example Artist, because a ledger below zero counts as 0 there; the −200 is visible in their breakdown as **Balance brought forward**.

You pay Example Artist 500 and record it on "2026 Q2". Trying to record 600 would be refused with "The payout exceeds the 550.00 payable to Example Artist for this period."

| Payee          | Payable | Paid | Still to pay |
| -------------- | ------- | ---- | ------------ |
| Example Artist | 550     | 500  | 50           |
| Second Payee   | 150     | 0    | 150          |

In the next period Example Artist starts with 50 carried over and Second Payee with 150.

## Good to know

- Figures on the period page are live. They change when you import, edit a transaction, change a contract or book a payout, also for past periods. Only statements are frozen.
- A payout recorded after a statement was generated is not in that statement. **My statements** shows it separately as paid since. Regenerate and publish to include it.
- A transaction whose paid date lies in a gap between two periods appears in no period's income and in none of the checks, but it still flows into the balance brought forward of every later period. Keep periods contiguous.
- Transactions without a conversion rate are missing from every figure on this page until they have one.
- The check counts transactions paid in this period only. Unassigned income from earlier periods is not listed again.
- Periods are not locked or closed. Nothing stops you from changing transactions of a period you have already paid out; regenerating its statements will then show different figures.
- Statements are plain CSV. There is no PDF or Excel export and no email delivery.
