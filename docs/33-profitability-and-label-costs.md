# Profitability and label costs

The profitability report shows what the label itself is left with per release, after payee shares and its own costs. Label costs are the running costs of the label that belong to no release, entered once or as recurring costs.

## Two kinds of cost

Label Office separates costs by who bears them:

| Cost                    | Who bears it                          | Where it is entered                                | Effect                                                                  |
| ----------------------- | ------------------------------------- | -------------------------------------------------- | ----------------------------------------------------------------------- |
| Recoupable expense      | Label and payees, each by their split | **Royalties → Transactions**, **Expense**          | Deducted from the release's income before the split.                    |
| Label cost on a release | The label alone                       | **Royalties → Transactions**, **One-time payment** | Does not touch payee earnings. Lowers the label result of that release. |
| Label overhead          | The label alone                       | **Royalties → Label costs**                        | Belongs to no release. Lowers the label result as a whole.              |

Technically all three are expense transactions. The second and third have **Recoup from payees** switched off; the third also has no release. See [Transactions](31-transactions.md).

## The profitability report

Open **Royalties → Profitability**. Each row is a release:

| Column                  | Meaning                                                           |
| ----------------------- | ----------------------------------------------------------------- |
| **Income**              | Everything the release brought in.                                |
| **Recoupable expenses** | Costs deducted before the split, so payees carry their share.     |
| **Net**                 | Income plus recoupable expenses (expenses are negative).          |
| **Payee share**         | What payees earn from the net under the contracts that apply.     |
| **Label costs**         | One-time payments and other costs the label bears alone.          |
| **Label result**        | Net minus payee share, less the label costs.                      |
| **Margin**              | Label result as a share of income. Empty when there is no income. |
| **Advances given**      | Transfers given to payees for this release. For information only. |

```text
label result = net − payee share + label costs      (costs are negative)
margin       = label result ÷ income
```

Releases are sorted by label result, best first; the headings re-sort the releases. Two special rows can follow at the end:

- **Not assigned to a release**: income and recoupable expenses without a release.
- **Label overheads**: label costs without a release, that is everything from **Royalties → Label costs**.

The last row is the **Total**. The four figures above the table repeat its **Income**, **Payee share**, **Label costs** and **Label result**.

### Filters

| Filter                              | What it does                                                            |
| ----------------------------------- | ----------------------------------------------------------------------- |
| **By sale date** / **By paid date** | Which date the range applies to. Sale date is the default.              |
| **From**, **To**                    | The date range. Without dates the report covers everything ever booked. |
| **All artists**                     | Only releases of one artist.                                            |

**Download CSV** saves the table as shown by the filters, with amounts rounded to four decimals and the margin in percent.

### Reading it

- **Payee share is what payees earned, not what you paid out.** Payouts do not appear in this report.
- **Advances are not costs.** An advance is a royalty paid ahead. It is recouped from the payee share, so counting it as a cost would count it twice. Until it is recouped it is money the label has out, which is why the column exists.
- **A negative label result** means the label's part of the net did not cover the costs it bears alone.
- **Use the same date as your question.** "What did this release make in 2026?" is a sale-date question. "Does this match the periods I settled?" is a paid-date question, because periods count by paid date.

### Example

Example Records, 1 January to 31 March 2026. Example Release (EX001) pays Example Artist 50% and Second Payee 10%.

| Transaction                                                    | Amount |
| -------------------------------------------------------------- | ------ |
| Income on EX001                                                | 2,000  |
| Expense "Mastering" on EX001, recouped from payees             | −400   |
| One-time payment "Artwork/Design" on EX001, borne by the label | −300   |
| Transfer "Advance" to Example Artist for EX001                 | −1,000 |
| Label cost "Example Hosting", 40 a month, three payments       | −120   |

The report shows:

| Release         | Income | Recoupable expenses | Net   | Payee share | Label costs | Label result | Margin | Advances given |
| --------------- | ------ | ------------------- | ----- | ----------- | ----------- | ------------ | ------ | -------------- |
| Example Release | 2,000  | −400                | 1,600 | 960         | −300        | 340          | 17%    | −1,000         |
| Label overheads | 0      | 0                   | 0     | 0           | −120        | −120         |        | 0              |
| Total           | 2,000  | −400                | 1,600 | 960         | −420        | 220          | 11%    | −1,000         |

Step by step for Example Release: net is 2,000 − 400 = 1,600. Payees earn 60% of it, 960. The label's part is 640, minus the 300 artwork it pays alone: 340. That is 17% of the 2,000 income. The advance of 1,000 does not change the result; Example Artist's share of 800 goes towards recouping it.

## Label costs

**Royalties → Label costs** is for what it costs to run the label: subscriptions, software, hosting, accounting, fees. These costs never reduce what payees earn.

The page shows the costs paid in the current and the previous year, what the recurring costs still running add up to per year, the list **Recurring costs** and the list **Booked costs** (the latest 200).

### A one-off cost

1. Choose **One-time cost**.
2. Fill in **What is it for**, the **Amount** as a positive number, the **Currency** and the date **Paid on**.
3. If the currency is not your label currency, enter the **Rate**, or leave it empty: the cost is then converted at the European Central Bank's reference rate of the day it was paid.
4. Optionally pick a **Category** (Subscriptions, Software, Hosting, Accounting, Legal, Bank Fees, Insurance, Office, Travel, Marketing, Fee, Other) and add a **Note**.
5. Choose **Book cost**.

The cost is stored as an expense transaction with a negative amount, borne by the label, without a release. Its sale date and paid date are both the **Paid on** date.

### A recurring cost

1. Choose **Recurring cost**.
2. Fill in **What is it for**, the **Amount each time**, the **Currency** (and **Rate** if needed), **Category** and **Note** as above.
3. Choose how often it **Repeats**: Monthly, Quarterly or Yearly.
4. Enter the date of the **First payment**. **Last payment** is optional; left empty, the cost runs until you stop it.
5. Choose **Add recurring cost**.

Due dates are counted from the first payment: a monthly cost starting on 31 January is due on 31 January, 28 February, 31 March, 30 April and so on. It stays at the end of the month instead of drifting to the 28th.

Due dates in the past are booked right away. A monthly cost whose first payment was two years ago gets a payment for every month since as soon as you add it.

The list **Recurring costs** shows **Since**, the **Next payment** and how many payments are **Booked**.

- **Change** edits the name, amount, currency, rate, category, note and **Last payment**. It applies to payments from now on; payments already booked keep their amounts. The rhythm and the first payment cannot be changed. Stop the cost and add a new one instead.
- **Stop** ends the cost. No further payments are booked; the ones already booked stay in the accounts.

### How recurring costs are booked

Each due date becomes one ordinary expense transaction, dated on the due date, borne by the label, without a release, with the name of the cost as its source. Each due date is booked exactly once. This happens:

- once a day through the scheduled command `royalties:book-recurring-expenses`, which runs at 04:00,
- whenever you open **Royalties → Label costs**,
- when you add or change a recurring cost.

The daily run needs Laravel's scheduler, which is set up once per installation by calling `php artisan schedule:run` every minute from cron. See [Installation](02-installation.md) and [Command line](50-command-line.md). You can also run the command by hand; it is safe to run as often as you like:

```sh
php artisan royalties:book-recurring-expenses
```

It goes through every label of the installation and prints a line for each label where something was booked.

### Changing booked costs

Every entry under **Booked costs** is an ordinary expense. The edit button opens it in the transaction form, the delete button removes it. A deleted payment of a recurring cost is not booked again for that date.

## Receipts

Under **Royalties → Label costs**, the panel **Receipts** collects the receipts and invoices behind your costs.

1. Drop one or more files (PDF, JPEG, PNG or WebP, up to 20 MB each) on the drop area, or click **browse**. Each file is uploaded as soon as it is added.
2. Each receipt waits in the list until it belongs to a cost. For each one you either
    - choose **Book as cost**: a new cost is booked and the receipt is attached to it, or
    - pick a cost under **Attach to a cost…** and choose **Attach**, for a cost that is booked already, such as a payment of a recurring cost.
3. A cost with a receipt shows a paperclip in **Booked costs**; it opens the file.

### Having receipts read

When the label has an AI service under **Settings → Connections**, every uploaded receipt is read in the background: the vendor, the date, the total, the currency, a category and a short summary. See [Connections](42-connections.md) for the setup.

- What was read is shown under the file name and fills in **Book as cost**. It is a proposal: check it before you book.
- A read receipt is compared with the costs that have no receipt yet. A cost of the same amount, paid within ten days of the receipt's date, is preselected under **Attach to a cost…** and marked "(matches)".
- **Read again** (the scan icon) sends the receipt to the service once more, for example after you changed the model.
- A receipt that could not be read says why and can still be booked by hand.

Example: Example Records pays 49.90 EUR a month for hosting, booked as a recurring cost. You upload the March invoice. It is read as "Example Hosting GmbH · 14 Mar 2026 · 49.90 EUR · Hosting", and the payment booked on 15 March is preselected. One click on **Attach** files the invoice with it.

Deleting a cost does not delete its receipt: the receipt goes back to the list. Deleting a receipt removes the file and leaves the cost alone.

## Good to know

- Label costs appear in the profitability report by date like everything else. A yearly cost shows up in full on its payment date; it is not spread over twelve months.
- Label costs also reduce **Label keeps** on the period page, in the period their paid date falls in. They never reach a payee's balance or statement.
- With an artist selected in the profitability report, the rows **Label overheads** and **Not assigned to a release** disappear, because they belong to no artist.
- Transactions without a conversion rate are left out of the report. A notice above the figures says how many.
- A recurring cost in a foreign currency without a rate converts each payment at the European Central Bank's reference rate of its day, so a subscription in USD is converted anew every month. With a **Rate** entered, every payment uses that rate; payments already booked keep theirs.
- The reference rate is what the market paid, not what your bank or card charged. When the exact amount matters, open the booked payment under **Transactions** and enter the rate from your bank statement.
- When the reference rate cannot be had (no connection, or a currency the bank does not quote), the payment is booked without a rate and not counted until you enter one.
- Without the scheduler, recurring costs are only booked when someone opens the label costs page. The result is the same, just later.
- The report applies today's contracts to all dates. Changing a contract changes the payee share of past ranges in this report as well.
- The report shows what payees earned in the range. It does not know about unrecouped balances: a payee share of 800 that only recoups an advance is still shown as 800.
