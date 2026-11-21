# Connections

The outside services a label works with are set up under **Settings → Connections**, by an admin of the label: the web search for the airplay research, the AI service that reads receipts, and Bandcamp. The installation's own admin also chooses the file storage there. Nothing on this page needs access to the server.

## How every connection works

- **Not connected**: a form. **Test and connect** tests what you entered against the service and stores it only when the test passes.
- **Connected**: a card that names the service and shows a hint of what is stored, such as the last four characters of a key. The cross on the card disconnects: a dialog **Disconnect {service}?** says what is deleted, and **Disconnect** confirms. The stored secrets are deleted, and the form comes back for a new connection.

Secrets are stored encrypted and never shown again. To change a connection, disconnect it and connect anew.

## Web search

The airplay research looks tracks up on Google through [DataForSEO](https://dataforseo.com).

1. Create an account at DataForSEO and add credit.
2. In their dashboard open **API Access**. It shows an **API login** and an **API password**. The API password is not the one you sign in to their website with.
3. Enter both under **Web search** and choose **Test and connect**.

The login is checked with DataForSEO; one it refuses is not stored.

Which countries the research searches in is a setting of the label: **Settings → Label → Airplay research countries**. What the research does and costs is described in [Airplay](23-airplay.md).

## Receipt reading

An AI service can read vendor, date, amount and category off the receipts you upload under **Royalties → Overheads**. Without a service, receipts are stored and booked by hand.

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

## Bandcamp

Shows whether the label is connected to Bandcamp and links to the connection, which has its own page. See [Bandcamp](11-bandcamp.md).

## File storage

This section is shown only to the **admin of the installation**: the first account that was created with `php artisan royalties:create-user`. It decides where the files of every label on the installation go.

While nothing is connected here, the server's `.env` decides, as described in [Configuration](03-configuration.md). The section then shows that connection as a card, marked as coming from the environment file, and offers **Connect a different storage**, which opens the form.

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
- A web search login in the server's environment file is used for labels that have none of their own; the page says so.
- Adding another AI service is a small change in the code; see [Extending](51-extending.md).
