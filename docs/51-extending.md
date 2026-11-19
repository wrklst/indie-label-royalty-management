# Extending

Two parts of the software are built to be added to with a small class: importers for statement layouts, and station drivers for the airplay research. This page is for developers; it also describes how to run the tests and what a contribution should look like.

## Adding an importer

One class per file layout. It is picked up automatically, appears in the upload form and is used by the command line.

```sh
php artisan make:importer ExampleDistributor
php artisan make:importer Zebralution2027 --source=zebralution --layout="2027 onwards"
```

The second form adds a new layout to an existing source: both layouts are then one entry in the upload form, and the columns of the uploaded file decide which class runs.

The generated class lives in `app/Services/Imports/Importers` and has three parts worth your attention:

```php
class ExampleDistributorImporter extends BaseImporter
{
    // Permanent: stored with every import. Never change it once files were imported.
    public function key(): string
    {
        return 'example-distributor';
    }

    // Headers that must be present. They identify this layout.
    public function requiredColumns(): array
    {
        return ['Sale Date', 'Net Revenue', 'ISRC'];
    }

    // One line of the file becomes one transaction. Only "total" is mandatory.
    protected function map(Row $row): ImportRow
    {
        return new ImportRow(
            total: $row->requireDecimal('Net Revenue'),
            timestamp: $row->requireDate('Sale Date', 'Y-m-d'),
            currency: $row->upper('Currency'),
            quantity: $row->decimal('Units'),
            category: 'Distribution',
            format: 'Streaming/Other',
            region: $row->region('Country'),
            provider: 'Example Distributor',
            retailer: $row->string('Store'),
            source: 'Example Distributor',
            artist: $row->string('Artist'),
            releaseName: $row->string('Album'),
            trackName: $row->string('Track'),
            upc: $row->upc('UPC'),
            isrc: $row->isrc('ISRC'),
            raw: $row->raw,
        );
    }
}
```

What you do not have to handle: the encoding, the delimiter, title lines above the header, the difference between CSV and Excel, capitalisation and underscores in header names, matching to the catalog and skipping lines imported before. That happens before and after your code runs.

- Return `true` from `requiresPaidDate()` when the file does not say when the label was paid; the upload form then asks for the date.
- Call `$this->skip('reason')` inside `map()` for a line that is not a sale. The reason is shown on the import page.
- `category` and `format` must be values from `config/royalties.php`.
- Strip personal data: do not put buyer names, emails or addresses into `raw`.

Read `BaseImporter` and the existing importers (`ZebralutionImporter` is a good model) before writing your own, and add a test with a small invented sample file under `tests/Fixtures`.

## Adding a station

The airplay research knows stations by name, country and website. That lets it recognise a result as "this is the playlist page of that station", search for the station by name, and read the show from the page.

All stations are listed in `app/Services/Airplay/Stations/StationRegistry.php`.

### A station that needs no code

Add one line:

```php
new SimpleStation('Example Radio', 'DE', ['exampleradio.de'], ['Example FM']),
```

The arguments are the name, the country, the domains of its website and other spellings of the name as they appear on statements.

### A station whose pages are worth reading

Write a class that extends `StationDriver` when the station publishes playlists in a recognisable form:

```php
class ExampleRadio extends StationDriver
{
    public function name(): string
    {
        return 'Example Radio';
    }

    public function region(): string
    {
        return 'DE';
    }

    public function domains(): array
    {
        return ['exampleradio.de'];
    }

    // Read the show from a page of this station.
    public function describe(string $pageTitle, string $pageText): array
    {
        // "Playlist: Late Show | Example Radio"
        return str_starts_with($pageTitle, 'Playlist:')
            ? ['show' => trim(explode('|', substr($pageTitle, 9))[0])]
            : [];
    }
}
```

Optional methods:

| Method                 | Purpose                                                                                                                                                  |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `aliases()`            | Other spellings of the station's name                                                                                                                    |
| `searchesEveryTrack()` | Return `true` to search this station for every track in its country, not only for tracks known to have run there. Each one is a billed search per track. |
| `queries()`            | The search words to use for this station                                                                                                                 |

Then add `new ExampleRadio` to the registry. `Bbc` and `Bayern2` are the existing examples.

## Adding an AI service

Receipts are read through the [Laravel AI SDK](https://laravel.com/docs/ai-sdk), so any provider the SDK supports can be offered. The services a label can choose are the list `SERVICES` in `app/Services/Receipts/AiConnection.php`:

```php
'mistral' => ['name' => 'Mistral', 'endpoint' => null, 'model' => 'mistral-small-latest'],
```

- The key is the SDK's driver name, or a name of your own for a service reached the OpenAI way; map that one to its address in `baseUrl()`.
- `endpoint` is the label of an extra field the service needs (a product ID, a base URL), or `null`.
- `model` is the model used when the label names none, or `null` when one has to be named.

The reading itself is the agent `App\Ai\Agents\ReceiptReader`: its instructions and the fields it returns. Tests fake it with `ReceiptReader::fake([...])`.

## Running the tests

```sh
composer test                    # formatting check, static analysis and all tests
vendor/bin/pest tests/Feature    # feature tests only
npx playwright install chromium  # once, for the interface tests
npm run build && vendor/bin/pest tests/Browser
```

`composer ci:check` runs everything the continuous integration runs: the JavaScript format and lint check, the TypeScript check, Pint, PHPStan and Pest. A change is ready when it passes.

- Tests use an in-memory database and fake file storage. They never touch your data.
- Interface tests run against the built assets, so build first.

## Conventions

- PHP is formatted with Pint (`vendor/bin/pint --dirty`), the interface with `npx vp check --fix`.
- PHPStan errors are fixed in the code, not ignored.
- Tests use only invented names, codes and amounts. Never commit real statements, real artist names or real sales figures.
- Routes are called from the interface through the generated Wayfinder functions in `resources/js/routes` and `resources/js/actions`, never as hard-coded addresses.
- Every model that belongs to a label uses the `BelongsToLabel` trait, which scopes all queries to the current label. Background jobs and commands have no current label and say which label they mean.

## The handbook

The handbook pages are the Markdown files in `docs`, named `NN-title.md`. The number orders them and its first digit is the chapter. The first heading is the title. Link to another page by its file name, for example `[Airplay](23-airplay.md)`; the application turns that into a handbook link, and the same link works on GitHub.

Which page the **?** button opens for a screen is decided by the list `HELP_TOPICS` in `resources/js/components/help/help-store.ts`.

## Good to know

- Adding a package changes what everyone has to install. Open an issue before adding a dependency.
- Money is calculated with `App\Support\Decimal` on strings, never with floating point numbers.
