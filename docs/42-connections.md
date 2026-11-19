# Connections

The outside services a label works with are set up under **Settings → Connections**, by an admin of the label: the web search for the airplay research, the AI service that reads receipts, and Bandcamp. The installation's own admin also chooses the file storage there. Nothing on this page needs access to the server.

Every secret you enter is stored encrypted and is never shown again. An empty secret field keeps the stored one.

## Web search

The airplay research looks tracks up on Google through [DataForSEO](https://dataforseo.com).

1. Create an account at DataForSEO and add credit.
2. In their dashboard open **API Access**. It shows an **API login** and an **API password**. The API password is not the one you sign in to their website with.
3. Enter both under **Web search** and choose **Save web search**.

The login is checked with DataForSEO when you save; one it refuses is not stored. Clear the login field and save to remove the connection.

Which countries the research searches in is a setting of the label: **Settings → Label → Airplay research countries**. What the research does and costs is described in [Airplay](23-airplay.md).

## Receipt reading

An AI service can read vendor, date, amount and category off the receipts you upload under **Royalties → Label costs**. Without a service, receipts are stored and booked by hand.

| Service                                                   | What you enter                                    |
| --------------------------------------------------------- | ------------------------------------------------- |
| **Infomaniak AI Tools**                                   | An API token, your **Product ID** and a **Model** |
| **OpenAI**, **Anthropic**, **Mistral**, **Google Gemini** | An API key. The model is optional.                |
| **Other OpenAI-compatible service**                       | An API key, the **Base URL** and a **Model**      |

### Infomaniak

Infomaniak runs open models in Swiss data centres.

1. In the Infomaniak Manager open **AI Tools** and note the number of your product: that is the **Product ID**.
2. Create an **API token** in the Manager with access to AI Tools.
3. Choose **Infomaniak AI Tools** as the service and enter the token and the product ID.
4. Enter a **Model** that understands images. If you are not sure of the name, enter any: saving asks Infomaniak which models your product offers and lists them when yours is not among them.

Saving checks the token and the product ID with Infomaniak before anything is stored.

### What is sent

- An image receipt is sent as the image.
- A PDF is sent as its text. A scanned PDF, which has no text, is sent as a picture of its first page.
- Nothing else is sent: no other data of the label goes to the service.

Receipts are billed to your account with the service, per document read. Choose **None: do not read receipts** to remove the connection.

> **Note:** Reading PDFs needs the programs `pdftotext` and `pdftoppm` on the server (package `poppler-utils`). Images work without them.

## Bandcamp

Shows whether the label is connected to Bandcamp and links to the connection, which has its own page. See [Bandcamp](11-bandcamp.md).

## File storage

This section is shown only to the **admin of the installation**: the first account that was created with `php artisan royalties:create-user`. It decides where the files of every label on the installation go.

| Choice                           | Meaning                                                                                        |
| -------------------------------- | ---------------------------------------------------------------------------------------------- |
| **As the environment file says** | The server's `.env` decides, as described in [Configuration](03-configuration.md)              |
| **On this server**               | New files go to the server's own disk                                                          |
| **In an S3-compatible bucket**   | New files go to a bucket: Amazon S3, Hetzner Object Storage, Infomaniak, Backblaze B2, MinIO … |

For a bucket you enter its name, the endpoint (for every provider other than Amazon), the region, and the access key and secret key. Saving writes, reads and deletes a small test file in the bucket; a bucket that fails this is not stored.

Two things to know before changing it:

- The choice applies to **new files**. Files stored before stay where they are. Copy them over on the server with `php artisan royalties:move-files --to=s3` (or `--to=local`); see [Command line](50-command-line.md).
- Pointing at a **different bucket** than the one in use makes the files in the old bucket unreachable until they are copied into the new one. Copy first, switch after.

## Good to know

- Connections belong to one label. In an installation with several labels each enters its own logins and keys.
- All stored secrets are encrypted with the installation's `APP_KEY`. If that key is lost, they have to be entered again.
- A web search login in the server's environment file is used for labels that have none of their own; the page says so.
- Adding another AI service is a small change in the code; see [Extending](51-extending.md).
