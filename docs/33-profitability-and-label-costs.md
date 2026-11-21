# Profitability and overheads

The profitability report shows what the label itself is left with per release, after payee shares and its own costs. Overheads are the running costs of the label that belong to no release, entered once or as recurring costs.

## Two kinds of cost

Label Office separates costs by who bears them:

| Cost                    | Who bears it                          | Where it is entered                               | Effect                                                                  |
| ----------------------- | ------------------------------------- | ------------------------------------------------- | ----------------------------------------------------------------------- |
| Recoupable expense      | Label and payees, each by their split | **Accounting → Transactions**, **New expense**    | Deducted from the release's income before the split.                    |
| Label cost on a release | The label alone                       | **Accounting → Transactions**, **New label cost** | Does not touch payee earnings. Lowers the label result of that release. |
| Overhead                | The label alone                       | **Label → Overheads**                             | Belongs to no release. Lowers the label result as a whole.              |

Technically all three are expense transactions. A label cost is any expense the label bears alone: the second and third have **Recoup from payees** switched off, and the third also has no release. See [Transactions](31-transactions.md).

## The profitability report

Open **Label → Profitability**. Each row is a release:

| Column                  | Meaning                                                                                     |
| ----------------------- | ------------------------------------------------------------------------------------------- |
| **Income**              | Everything the release brought in.                                                          |
| **Recoupable expenses** | Costs deducted before the split, so payees carry their share.                               |
| **Net**                 | Income plus recoupable expenses (expenses are negative).                                    |
| **Payee share**         | What payees earn from the net under the contracts that apply.                               |
| **Label costs**         | Every expense the label bears alone, with or without a release.                             |
| **Label result**        | Net minus payee share, less the label costs.                                                |
| **Margin**              | Label result as a share of income. Empty when there is no income.                           |
| **Advances given**      | Transfers given to payees for this release (negative transfers only). For information only. |

```text
label result = net − payee share + label costs      (costs are negative)
margin       = label result ÷ income
```

Releases are sorted by label result, best first; the headings re-sort the releases. Two special rows can follow at the end:

- **Not assigned to a release**: income and recoupable expenses without a release, and advances given without a release.
- **Overheads**: label costs without a release, that is everything from **Label → Overheads**.

The last row is the **Total**. The four figures above the table repeat its **Income**, **Payee share**, **Label costs** and **Label result**.

### Filters

| Filter                              | What it does                                                            |
| ----------------------------------- | ----------------------------------------------------------------------- |
| **By sale date** / **By paid date** | Which date the range applies to. Sale date is the default.              |
| **From**, **To**                    | The date range. Without dates the report covers everything ever booked. |
| **All artists**                     | Only releases of one artist.                                            |
| **Clear filters**                   | Appears when a filter is set and resets all of them.                    |

**Download CSV** saves the table as shown by the filters, with amounts rounded to four decimals and the margin in percent.

### Reading it

- **Payee share is what payees earned, not what you paid out.** Payouts do not appear in this report.
- **Advances are not costs.** An advance is a royalty paid ahead. It is recouped from the payee share, so counting it as a cost would count it twice. Until it is recouped it is money the label has out, which is why the column exists.
- **A negative label result** means the label's part of the net did not cover the costs it bears alone.
- **Use the same date as your question.** "What did this release make in 2026?" is a sale-date question. "Does this match the periods I settled?" is a paid-date question, because periods count by paid date.

### Example

Example Records, 1 January to 31 March 2026. Example Release (EX001) pays Example Artist 50% and Second Payee 10%.

| Transaction                                              | Amount |
| -------------------------------------------------------- | ------ |
| Income on EX001                                          | 2,000  |
| Expense "Mastering" on EX001, recouped from payees       | −400   |
| Label cost "Artwork/Design" on EX001, borne by the label | −300   |
| Transfer "Advance" to Example Artist for EX001           | −1,000 |
| Overhead "Example Hosting", 40 a month, three payments   | −120   |

The report shows:

| Release         | Income | Recoupable expenses | Net   | Payee share | Label costs | Label result | Margin | Advances given |
| --------------- | ------ | ------------------- | ----- | ----------- | ----------- | ------------ | ------ | -------------- |
| Example Release | 2,000  | −400                | 1,600 | 960         | −300        | 340          | 17%    | −1,000         |
| Overheads       | 0      | 0                   | 0     | 0           | −120        | −120         |        | 0              |
| Total           | 2,000  | −400                | 1,600 | 960         | −420        | 220          | 11%    | −1,000         |

Step by step for Example Release: net is 2,000 − 400 = 1,600. Payees earn 60% of it, 960. The label's part is 640, minus the 300 artwork it pays alone: 340. That is 17% of the 2,000 income. The advance of 1,000 does not change the result; Example Artist's share of 800 goes towards recouping it.

## Overheads

**Label → Overheads** is for what it costs to run the label: subscriptions, software, hosting, accounting, fees. These are label costs that belong to no release, and they never reduce what payees earn.

The page shows three figures: **Costs in {this year}**, **Costs in {last year}** and **Recurring, per year**. The last adds up the recurring costs still running, each converted into your label currency at the rate you entered for it or, without one, today's reference rate; a cost whose rate cannot be had is left out and the hint under the figure says how many. Below are the list **Recurring costs** (25 to a page), the **Receipts** that wait for a cost (25 to a page) and the list **Booked costs** (50 to a page). **Booked costs** can be searched by name, memo or category and sorted by any heading; the sort covers every cost, not only the page you see.

### A one-off cost

1. Choose **One-off cost**.
2. Fill in **What is it for**, the **Amount** as a positive number, the **Currency** and the **Paid date**.
3. If the currency is not your label currency, enter the **Rate to EUR** (your label currency), or leave it empty: the cost is then converted at the European Central Bank's reference rate of the day it was paid, see [Currencies and conversion](31-transactions.md#currencies-and-conversion).
4. Optionally pick a **Category** (Subscriptions, Software, Hosting, Accounting, Legal, Bank Fees, Insurance, Office, Travel, Marketing, Fee, Other) and add a **Memo**.
5. Choose **Book cost**.

You type the amount positive, as everywhere in the product. The cost is stored as an expense transaction with a negative amount, borne by the label, without a release. Its sale date and paid date are both the **Paid date**.

### A refund or a credit

When money comes back, for example a supplier's credit note, tick **Refund or credit** in the one-off dialog, in the edit dialog of a booked cost or in the dialog that books a receipt. The amount is then stored positive and reduces the costs instead of adding to them. The same switch is on the expense form under **Accounting → Transactions**, see [Transactions](31-transactions.md). A recurring cost has no such switch.

### A recurring cost

1. Choose **Recurring cost**.
2. Fill in **What is it for**, the **Amount each time**, the **Currency** (and **Rate** if needed), **Category** and **Memo** as above.
3. Choose how often it **Repeats**: Monthly, Quarterly or Yearly.
4. Enter the date of the **First payment**. **Last payment** is optional; left empty, the cost runs until you stop it.
5. Choose **Create recurring cost**.

Due dates are counted from the first payment: a monthly cost starting on 31 January is due on 31 January, 28 February, 31 March, 30 April and so on. It stays at the end of the month instead of drifting to the 28th.

Due dates in the past are booked right away. A monthly cost whose first payment was two years ago gets a payment for every month since as soon as you add it. One run books at most 600 payments per label; the rest follow with the next run.

The list **Recurring costs** shows the name, **Category**, **Amount**, **Since**, the **Next payment** and how many payments are **Booked**; every heading sorts it.

- **Change** edits the name, amount, currency, rate, category, memo and **Last payment**. It applies to payments from now on; payments already booked keep their amounts. The rhythm and the first payment cannot be changed. Stop the cost and add a new one instead.
- The bin on a row ends or removes the cost. It opens **Delete {cost name}?** and offers what fits:
    - A cost with payments booked: **Stop, keep the payments** ends the cost, so no further payments are booked and the ones already booked stay in the accounts. **Delete with its N payments** removes the cost together with those N payments, for a cost entered by mistake; receipts attached to them go back to the list of receipts.
    - A cost nothing was booked for yet: **Delete** removes it.
- Stopping removes the cost from the list; it cannot be started again. Add a new one instead.

### How recurring costs are booked

Each due date becomes one ordinary expense transaction, dated on the due date, borne by the label, without a release, with the name of the cost as its source. Each due date is booked exactly once. This happens:

- once a day through the scheduled command `royalties:book-recurring-expenses`, which runs at 04:00,
- whenever you open **Label → Overheads**,
- when you add or change a recurring cost.

The daily run needs Laravel's scheduler, which is set up once per installation by calling `php artisan schedule:run` every minute from cron. See [Installation](02-installation.md) and [Command line](50-command-line.md). You can also run the command by hand; it is safe to run as often as you like:

```sh
php artisan royalties:book-recurring-expenses
```

It goes through every label of the installation and prints a line for each label where something was booked.

### Changing booked costs

Every entry under **Booked costs** is an ordinary expense. The edit button opens it in the dialog you booked it in, with the amount positive as you typed it, with **Refund or credit** ticked for a credit: **What is it for**, **Amount**, **Currency**, **Rate to EUR**, **Category**, **Memo** and **Paid date**; save with **Save cost**. Changing a cost keeps its sale date unless that was the paid date. The delete button opens **Delete {cost name}?** and removes the cost from the label result; a receipt attached to it goes back to the list of receipts. A deleted payment of a recurring cost is not booked again for that date.

A cost with a release, or one that payees carry a share of, is not an overhead: it is edited as an expense on the transaction page, see [Transactions](31-transactions.md). A credit without a release is listed here too.

## Receipts

Under **Label → Overheads**, the panel **Receipts** collects the receipts and invoices behind your costs.

1. Drop one or more files (PDF, JPEG, PNG or WebP, up to 20 MB each) on the drop area (**Drop receipts here**), or click **browse**. Each file is uploaded as soon as it is added.
2. Each receipt waits in the list, 25 to a page, until it belongs to a cost. For each one you either
    - choose **Book as cost**: the dialog **Book receipt as a cost** books a new cost and attaches the receipt to it, or
    - pick a cost under **Attach to a cost…** and choose **Attach**, for a cost that is booked already, such as a payment of a recurring cost. The list offers the 200 newest costs that have no receipt yet.
3. A cost with a receipt shows a paperclip in **Booked costs**; it opens the file.

### Entering by hand or reading with AI

Receipts are entered by hand unless you choose otherwise. When the label has an AI service under **Settings → Connections**, the drop area offers two choices, **Enter by hand** (the default) and **Read with AI**; the last choice is remembered in this browser. Nothing is sent to the AI service unless you chose **Read with AI** for that upload, or press **Read with AI** on a receipt afterwards. Without a connected service the panel says that reading can be switched on under **Settings → Connections**. See [Connections](42-connections.md) for the setup.

- **Book as cost** is always available, read or not.
- A read receipt shows the vendor, the date, the total, the currency, a category and a short summary under its file name, and fills in **Book as cost**. What the AI read is a proposal: check it before you book.
- Reading happens in the background and needs the queue worker (see [Configuration](03-configuration.md)). A receipt being read says so, and you can book it by hand meanwhile.
- A receipt that is not being read has the button **Read with AI**; one that was read has **Read again**, for example after you changed the model. A receipt that could not be read says why.
- A read receipt is compared with the costs that have no receipt yet. A cost of the same amount, paid within ten days of the receipt's date, is preselected under **Attach to a cost…** and marked "(matches)".

What is sent: an image (JPEG, PNG, WebP) goes as it is. From a PDF, the text of the first three pages is extracted and at most the first 20,000 characters of it are sent. A PDF without text (a scan) is turned into a picture of its first page and that is sent. The PDF tools `pdftotext` and `pdftoppm` (package `poppler-utils`) must be installed on the server for this. Reading is limited to 30 requests a minute.

Example: Example Records pays 49.90 EUR a month for hosting, booked as a recurring cost. You upload the March invoice. It is read as "Example Hosting GmbH · 14 Mar 2026 · 49.90 EUR · Hosting", and the payment booked on 15 March is preselected. One click on **Attach** files the invoice with it.

Deleting a cost does not delete its receipt: the receipt goes back to the list. Deleting a receipt (**Delete {file name}?**) removes the file and leaves the cost alone.

## Good to know

- Overheads appear in the profitability report by date like everything else. A yearly cost shows up in full on its payment date; it is not spread over twelve months.
- Overheads also reduce **Label keeps** on the period page, in the period their paid date falls in. They never reach a payee's balance or statement.
- With an artist selected in the profitability report, the rows **Overheads** and **Not assigned to a release** disappear, because they belong to no artist.
- Transactions without a conversion rate are left out of the report. A notice above the figures says how many.
- A recurring cost in a foreign currency without a rate converts each payment at the European Central Bank's reference rate of its day (a weekend or holiday takes the last day before it), so a subscription in USD is converted anew every month. With a **Rate** entered, every payment uses that rate; payments already booked keep theirs.
- The reference rate is what the market paid, not what your bank or card charged. When the exact amount matters, open the booked payment under **Transactions** and enter the rate from your bank statement.
- When the reference rate cannot be had (the server cannot reach api.frankfurter.dev, or the bank does not quote the currency), the payment is booked without a rate and not counted until you enter one: open it under **Accounting → Transactions** (filter **Unconverted**). A one-off cost entered without a rate behaves the same. See [Troubleshooting](52-troubleshooting.md).
- Without the scheduler, recurring costs are only booked when someone opens the Overheads page. The result is the same, just later.
- The report applies today's contracts to all dates. Changing a contract changes the payee share of past ranges in this report as well.
- The report shows what payees earned in the range. It does not know about unrecouped balances: a payee share of 800 that only recoups an advance is still shown as 800.
