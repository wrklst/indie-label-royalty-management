<?php

namespace App\Support;

/**
 * How many rows a list shows per page. The rule: the main list of a page
 * has 50 rows, a list under it (a secondary list on a record page, or one
 * of several on a page) has 25, and the transactions list 100.
 */
final class PageSize
{
    public const int MAIN = 50;

    public const int SECONDARY = 25;

    public const int TRANSACTIONS = 100;
}
