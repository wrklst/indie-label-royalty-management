<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Services\Royalties\RecurringExpenseBooker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('royalties:book-recurring-expenses')]
#[Description('Book the recurring label costs that have come due')]
class BookRecurringExpenses extends Command
{
    public function handle(RecurringExpenseBooker $booker): int
    {
        foreach (Label::query()->orderBy('id')->get() as $label) {
            $created = $booker->bookDue($label);

            if ($created > 0) {
                $this->line("{$label->name}: {$created} recurring costs booked.");
            }
        }

        return self::SUCCESS;
    }
}
