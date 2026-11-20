<?php

namespace App\Console\Commands;

use App\Services\Releases\ArtistCredits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('royalties:credit-remixers')]
#[Description('Credit as remixer every artist of the catalog that a track title names, such as "Title (Example Artist Remix)"')]
class CreditRemixers extends Command
{
    public function handle(): int
    {
        $credited = ArtistCredits::creditRemixers();

        foreach ($credited as $line) {
            $this->line($line);
        }

        $this->info(count($credited).' remixer credits added.');

        return self::SUCCESS;
    }
}
