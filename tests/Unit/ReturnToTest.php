<?php

use App\Support\ReturnTo;

test('a local path with its query is accepted', function (string $path) {
    expect(ReturnTo::path($path))->toBe($path);
})->with([
    'a page' => ['/releases/3'],
    'a list with filters' => ['/transactions?type=expense&from=2026-01-01'],
    'the root' => ['/'],
    'a fragment' => ['/releases/3#files'],
]);

test('anything that could leave this application is refused', function (mixed $value) {
    expect(ReturnTo::path($value))->toBeNull();
})->with([
    'a protocol-relative address' => ['//evil.example'],
    'a protocol-relative address with a path' => ['//evil.example/releases'],
    'an absolute address' => ['https://evil.example'],
    'an absolute address of this host spelled out' => ['http://localhost/releases'],
    'a backslash after the slash' => ['/\\evil.example'],
    'a slash and a backslash' => ['/\\/evil.example'],
    'a backslash inside' => ['/releases\\3'],
    'a script' => ['javascript:alert(1)'],
    'a data address' => ['data:text/html,<script>alert(1)</script>'],
    'no leading slash' => ['releases/3'],
    'a tab inside the double slash' => ["/\t/evil.example"],
    'a newline' => ["/releases\nLocation: https://evil.example"],
    'a space in front' => [' /releases'],
    'empty' => [''],
    'nothing' => [null],
    'a list' => [['/releases']],
]);
