<?php

use App\Enums\LabelRole;
use App\Models\DataImport;
use App\Models\Label;
use App\Models\User;
use App\Support\CurrentLabel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a label and make it the current tenant for the test.
 */
function currentLabel(array $attributes = []): Label
{
    $label = Label::factory()->create($attributes);

    app(CurrentLabel::class)->set($label, LabelRole::Admin);

    return $label;
}

/**
 * Create a user who administers the given label and has it selected.
 */
function labelAdmin(Label $label): User
{
    $user = User::factory()->create(['current_label_id' => $label->id]);
    $label->users()->attach($user, ['role' => LabelRole::Admin->value]);

    return $user;
}

/**
 * Store a fixture file as a pending data import.
 */
function pendingImport(Label $label, string $importer, string $fixture, array $attributes = []): DataImport
{
    Storage::fake('local');
    Storage::disk('local')->put("imports/{$fixture}", file_get_contents(base_path("tests/Fixtures/{$fixture}")));

    return DataImport::factory()->create([
        'label_id' => $label->id,
        'importer' => $importer,
        'status' => 'pending',
        'file_path' => "imports/{$fixture}",
        ...$attributes,
    ]);
}

/**
 * Write an Excel workbook the way spreadsheet software stores it: text in
 * the shared string table, numbers inline, dates as serial numbers with a
 * date style. Use ['date' => '2026-03-31'] for a date cell and null to
 * leave a cell out.
 *
 * @param  list<list<string|int|float|array{date: string}|null>>  $rows
 */
function xlsxFile(array $rows): string
{
    $strings = [];
    $sheet = '';

    foreach ($rows as $rowIndex => $row) {
        $sheet .= '<row r="'.($rowIndex + 1).'">';

        foreach ($row as $columnIndex => $value) {
            if ($value === null) {
                continue;
            }

            $reference = chr(65 + $columnIndex).($rowIndex + 1);

            if (is_array($value)) {
                $serial = CarbonImmutable::parse('1899-12-30', 'UTC')->diffInDays(CarbonImmutable::parse($value['date'], 'UTC'));
                $sheet .= "<c r=\"{$reference}\" s=\"1\"><v>".(int) $serial.'</v></c>';
            } elseif (is_string($value)) {
                $strings[$value] ??= count($strings);
                $sheet .= "<c r=\"{$reference}\" t=\"s\"><v>{$strings[$value]}</v></c>";
            } else {
                $sheet .= "<c r=\"{$reference}\"><v>{$value}</v></c>";
            }
        }

        $sheet .= '</row>';
    }

    $namespace = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', "<workbook {$namespace} xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets><sheet name=\"Statement\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>");
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', "<styleSheet {$namespace}><cellXfs count=\"2\"><xf numFmtId=\"0\"/><xf numFmtId=\"14\" applyNumberFormat=\"1\"/></cellXfs></styleSheet>");
    $zip->addFromString('xl/sharedStrings.xml', "<sst {$namespace}>".implode('', array_map(fn (string $text): string => '<si><t>'.htmlspecialchars($text, ENT_XML1).'</t></si>', array_keys($strings))).'</sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', "<worksheet {$namespace}><sheetData>{$sheet}</sheetData></worksheet>");
    $zip->close();

    return $path;
}
