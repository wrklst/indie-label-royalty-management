# Label settings

Everything a label decides for itself is under **Settings → Label**, open to admins of the label. Nothing here needs access to the server. This page goes through the sections of that screen.

## Label

| Field                   | What it does                                                                                                                                             |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Label name**          | Shown in the label switcher, on statements and in emails.                                                                                                |
| **Accounting currency** | The currency all totals are shown in. Changing it does not convert existing transactions, so it can only be changed while the label has no transactions. |
| **Contact email**       | The label's contact address. It starts as the address of whoever created the label.                                                                      |

## Logo and signet

Two images of the label, each uploaded by dropping a file on its field or choosing one. PNG, JPEG or WebP, up to 4 MB.

| Image      | Where it is shown                                                |
| ---------- | ---------------------------------------------------------------- |
| **Logo**   | At the top of statements, for you and for payees in their portal |
| **Signet** | The small square mark in the sidebar, next to the label's name   |

Dropping a new file replaces the stored image. The bin removes it. A transparent PNG works on light and dark backgrounds. SVG files are not accepted.

## Bandcamp imports

Two options that apply to uploaded Bandcamp reports and to the automatic sync alike:

- **Do not deduct 1% from Bandcamp net amounts.** By default 1% is taken off each Bandcamp line to cover payout fees. Tick it to book the net amount as reported.
- **Skip physical and merch sales.** Imports downloads only, for labels that account physical sales elsewhere.

The connection itself (client ID and secret) is on its own page: follow the link **connect your Bandcamp account** or open **Settings → Bandcamp**. See [Bandcamp](11-bandcamp.md).

## Distributors

Tick the distributors the label works with. What is built for a distributor is only shown when it is ticked:

| Distributor | What it switches on                                                                                                                                                                                                                                                                      |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Zebralution | The **Zebralution ZIP** under **Delivery** on a release, its genres as suggestions on the release form, its statement layouts in the list on **Data imports → Import a statement**, the link to its analytics export on **Play counts**, and the section **Zebralution delivery** below. |

A label that never chose has all of them ticked. Switching one off hides its options and keeps what was imported or entered. A statement of a distributor that is switched off is still recognised when uploaded with **Detect automatically**.

Statements of any other distributor go in through the template layout; see [Importing statements](10-importing-statements.md). To add a distributor of your own, see [Extending](51-extending.md).

**Zebralution delivery** holds what goes into every metadata sheet: the number Zebralution gave the label, the country of origin, the price group, and who holds the rights in compositions and lyrics.

## Airplay research

- The login for the web search itself is a connection: **Settings → Connections**. See [Connections](42-connections.md).
- **Airplay research countries**: two-letter country codes separated by spaces or commas, for example `DE GB US`. Every track is searched once per country, and each search is billed.

See [Airplay](23-airplay.md) for what the research does and what it costs.

## Accounting features

Three optional tools. Each one adds an entry under **Royalties** in the sidebar. Nothing is calculated on its own: you run a tool for a range of paid dates and it creates ordinary transactions.

| Feature         | Extra settings                                                                                                                                                                                                          |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Mechanicals** | **Include physical sales**: count sold physical copies as units, next to downloads. **Statutory rate conversion**: what one US dollar is in the label's currency, needed when the label does not account in US dollars. |
| **PPD**         | none                                                                                                                                                                                                                    |
| **Reserves**    | none                                                                                                                                                                                                                    |

**Ignore negative quantities** applies to Mechanicals and PPD: by default returns reduce the units they are calculated on.

The tools are explained in [Mechanicals, PPD and reserves](34-mechanicals-ppd-and-reserves.md).

## Territories

Groups of countries for contract splits that only apply in part of the world. A split without a territory applies everywhere.

1. Enter a **Territory name**, for example `GSA`.
2. Enter the **Countries** as two-letter codes: `DE AT CH`.
3. Choose **Add territory**.

A territory that contract splits refer to cannot be deleted: without it those splits would apply worldwide and pay out on every sale. Change the splits first. See [Contracts, payees and ledgers](30-contracts-payees-and-ledgers.md).

## Other settings pages

| Page                                                 | What is there                                                                                                          |
| ---------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **Settings → Team**                                  | Who has access to the label, and as what. See [Team, roles and sign-in](40-team-roles-and-sign-in.md).                 |
| **Settings → Connections**                           | Web search, receipt reading and, for the installation's admin, the file storage. See [Connections](42-connections.md). |
| **Settings → Bandcamp**                              | The Bandcamp connection and its sync.                                                                                  |
| **Settings → Profile**, **Security**, **Appearance** | Your own account: name, email, password, passkeys, two-factor authentication, light or dark mode.                      |

## Good to know

- Settings belong to one label. In an installation with several labels each has its own currency and its own connections.
- Categories and formats are the same for every label of an installation; they are not editable here.
- What the server itself needs (database, mail) is not in the interface: see [Configuration](03-configuration.md). Where files are stored is set under [Connections](42-connections.md) by the person who runs the installation.
