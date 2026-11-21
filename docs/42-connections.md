# Connections

The services a label works with and the logins of the outside services it uses are set up under **Settings → Connections**, by an admin of the label: Zebralution, Bandcamp, Subvert and Rightz Audio, the web search for the airplay research and the AI service that reads receipts. The installation's own admin also chooses the file storage there. Nothing on this page needs access to the server.

## How the page works

**Settings → Connections**, by an admin of the label, shows everything the label works with as cards. Every card says what the service is, what it is for and whether it is connected, and holds its own switch, options or credentials. A card saves only what it shows: saving one never changes another card or **Settings → Label**.

There are two kinds of card:

- A **service** the label can work with: Zebralution, Bandcamp, Subvert and Rightz Audio. Each has a switch, **The label works with …**, and a **Save** button.
- A **login** to an outside service: the web search, receipt reading and, for the installation's admin, the file storage.

A login card has two states.

- **Not connected**: a form. **Test and connect** tests what you entered against the service and stores it only when the test passes.
- **Connected**: the card names the service and shows a hint of what is stored, such as the last four characters of a key. The cross on the card disconnects: a dialog **Disconnect {card}?** says what is deleted, and **Disconnect** confirms. The stored secrets are deleted and the form comes back for a new connection.

Secrets are stored encrypted and never shown again.

### Changing a connection

- **Receipt reading**: **Change model** switches the model without entering the key again; see below.
- **Web search** and **File storage**, when they come from the server's environment file: the card says so and offers **Connect a different login** or **Connect a different storage**. The environment's connection stays in use until the new one passes its test.
- **Bandcamp**: the account, the start date and the weekly sync are changed on the Bandcamp page, and **Load accounts from Bandcamp again** fetches the list anew; see [Bandcamp](11-bandcamp.md).
- Anything else: disconnect it and connect anew.

## Services

A label that never chose works with all four, so nothing disappears for an existing label. Switching a service off hides what is built for it and keeps everything already imported or entered. The generic delivery package and the metadata CSV are never hidden.

| Service          | Kind           | What the switch shows                                                                                                                                                                                                               |
| ---------------- | -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Zebralution**  | Distributor    | The **Zebralution ZIP** under **Delivery** on a release, its genres as suggestions on the release form, its statement layouts in the source list on **Import a statement**, and the link to its analytics export on **Play counts** |
| **Bandcamp**     | Shop           | The **Bandcamp ZIP** under **Delivery**, its statement layouts in the source list, the weekly sync and the Bandcamp page                                                                                                            |
| **Subvert**      | Shop           | The **Subvert ZIP** under **Delivery**                                                                                                                                                                                              |
| **Rightz Audio** | Rights society | Its statement layouts in the source list; its airplay comes in with them                                                                                                                                                            |

A download of a switched-off service answers "not found", and the weekly Bandcamp sync skips the label. A statement of a switched-off service is still recognised when uploaded with **Detect automatically**.

### Zebralution

Besides the switch, the card holds what goes into every metadata sheet: **Zebralution number** (the number Zebralution gave the label), **Country of origin**, **Price group** and **Rights in compositions and lyrics**. See [Files, audio and delivery](22-files-audio-and-delivery.md#the-zebralution-zip).

### Bandcamp

Besides the switch, the card holds the two options that apply to uploaded Bandcamp reports and to the automatic sync alike:

- **Do not deduct 1% from Bandcamp net amounts.** By default 1% is taken off each Bandcamp line to cover payout fees. Tick it to book the net amount as reported.
- **Skip physical and merch sales.** Imports downloads only, for labels that account physical sales elsewhere.

When the label is connected the card also shows the weekly sync and the last sync, with a link to the Bandcamp page; when it is not, the link reads **Connect your Bandcamp account**. The page, with the credentials, the account and the sync, is described in [Bandcamp](11-bandcamp.md).

## Web search

The airplay research looks tracks up on Google through [DataForSEO](https://dataforseo.com).

1. Create an account at DataForSEO and add credit.
2. In their dashboard open **API Access**. It shows an **API login** and an **API password**. The API password is not the one you sign in to their website with.
3. Enter both under **Web search** and choose **Test and connect**.

The login is checked with DataForSEO; one it refuses is not stored.

The same card holds the **Airplay research countries**: two-letter country codes separated by spaces or commas, for example `DE GB US`. Every track is searched once per country, and each search is billed. **Save countries** stores them, and only them. What the research does and costs is described in [Airplay](23-airplay.md).

## Receipt reading

An AI service can read vendor, date, amount and category off the receipts you upload under **Label → Overheads**. Without a service, receipts are stored and booked by hand.

| Service                                                   | What you enter                                           |
| --------------------------------------------------------- | -------------------------------------------------------- |
| **Infomaniak AI Tools**                                   | An API token, your **Product ID** and a **Model**        |
| **OpenAI**, **Anthropic**, **Mistral**, **Google Gemini** | An API key and a **Model**                               |
| **Other OpenAI-compatible service**                       | An API key, the **Base URL** and the name of a **Model** |

### Infomaniak

Infomaniak runs open models in Swiss data centres.

1. In the Infomaniak Manager open **AI Tools** and note the number of your product: that is the **Product ID**.
2. Create an **API token** in the Manager with access to AI Tools.
3. Choose **Infomaniak AI Tools** as the service and enter the token and the product ID.
4. Choose a **Model** from the list.

### Choosing a model

For Infomaniak, OpenAI, Anthropic, Mistral and Google Gemini the **Model** field is a list of the models that can read a receipt, that is, the ones that take pictures. It is ordered by price, and one is marked "recommended": the cheapest that answers in a fixed format.

Choosing a model shows:

- its description from the catalog,
- its list price per million tokens and what about a hundred receipts cost (a receipt is counted as 3,000 tokens in and 150 out),
- whether it answers in a fixed format, so the fields arrive reliably,
- whether it works a document through before answering (slower, more careful) or answers directly (fast).

Names, prices and descriptions come from [models.dev](https://models.dev), a public catalog, fetched once a day. Prices are list prices in US dollars and may differ from your own terms with the service. **Another model…** lets you type a name the list does not have. When the catalog cannot be reached, the field is a plain text field.

### The test

**Test and connect** has the chosen model read an invented sample receipt, exactly as real receipts are read. The connection is stored only when the model answers. Afterwards the card says how it did:

> Tested 19 Nov 2026: read the sample receipt correctly in 3.2 seconds.

or which fields it got wrong, in which case another model may read better. A model that cannot take pictures fails this test and is not stored; the message says what the service answered.

### Changing the model

The card of a connected service has **Change model**. It opens the same list, with the model in use selected. **Test and switch** has the new model read the sample receipt; it replaces the old one only when it answers. The key and the rest of the connection stay as they are.

### What is sent

- An image receipt is sent as the image.
- A PDF is sent as its text. A scanned PDF, which has no text, is sent as a picture of its first page.
- Nothing else is sent: no other data of the label goes to the service.

Receipts are billed to your account with the service, per document read. The cross on the card disconnects the service; receipts are then stored unread.

> **Note:** Reading PDFs needs the programs `pdftotext` and `pdftoppm` on the server (package `poppler-utils`). Images work without them.

## File storage

This card is shown only to the **admin of the installation**: the first account that was created with `php artisan royalties:create-user`. It decides where the files of every label on the installation go.

While nothing is connected here, the server's `.env` decides, as described in [Configuration](03-configuration.md). The card then shows that connection, marked as coming from the environment file, and offers **Connect a different storage**, which opens the form.

| Choice                         | Meaning                                                                                        |
| ------------------------------ | ---------------------------------------------------------------------------------------------- |
| **In an S3-compatible bucket** | New files go to a bucket: Amazon S3, Hetzner Object Storage, Infomaniak, Backblaze B2, MinIO … |
| **On this server**             | New files go to the server's own disk                                                          |

For a bucket you enter its name, the endpoint (for every provider other than Amazon), the region, and the access key and secret key. **Test and connect** writes, reads and deletes a small test file in the bucket; a bucket that fails this is not stored. The cross on the card hands the decision back to the environment file.

### Direct uploads

With a bucket, the card shows whether **direct uploads** are on.

- **Off**: every upload travels to this server first and from there to the bucket. That is limited by what the server accepts in one upload and takes twice the time for a large master.
- **On**: release files, receipts and statement files go from the browser straight to the bucket. The server only hears where the file went, checks it and files it.

A bucket takes files from a web page only when it has been told the page's address. **Allow direct uploads** tells it: the address you are using right now is added to the bucket's rules for cross-origin requests (CORS), next to any rules it already has, for uploading only. An installation reached under several addresses needs this once for each.

Things to know:

- A file lands in the folder `uploads/` of the bucket first and is moved to its place once the browser reports it. What is never reported, such as the file of a form that was not sent, is removed after a day.
- A file sent this way is checked like any other: its type is read off the file itself, and one that is too large or of the wrong type is deleted.
- If the bucket refuses the browser after all, the upload goes through the server as before. Nothing is lost.
- Some storage providers offer no way to set these rules through their API. The button then shows what the provider answered; set the rule in the provider's own console: origin = the address of this installation, method `PUT`, all headers.

### Before changing the storage

Two things to know before changing it:

- The choice applies to **new files**. Files stored before stay where they are. Copy them over on the server with `php artisan royalties:move-files --to=s3` (or `--to=local`); see [Command line](50-command-line.md).
- Pointing at a **different bucket** than the one in use makes the files in the old bucket unreachable until they are copied into the new one. Copy first, switch after.

## Good to know

- Connections belong to one label. In an installation with several labels each enters its own logins and keys.
- All stored secrets are encrypted with the installation's `APP_KEY`. If that key is lost, they have to be entered again.
- A web search login in the server's environment file is used for labels that have none of their own; the card says so.
- Adding another AI service is a small change in the code; see [Extending](51-extending.md).
