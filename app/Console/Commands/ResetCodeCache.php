<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Throwable;

#[Signature('royalties:reset-code-cache')]
#[Description('Have the web server forget the PHP code it has compiled, so that it runs the code on disk')]
class ResetCodeCache extends Command
{
    /**
     * OPcache belongs to the web server's PHP, which the command line
     * cannot reach. So the site itself is asked, at an address only this
     * installation can sign.
     */
    public function handle(): int
    {
        $address = rtrim((string) config('app.url'), '/').URL::temporarySignedRoute('updates.code-cache', now()->addMinute(), absolute: false);

        try {
            $answer = Http::timeout(20)->acceptJson()->get($address);
        } catch (Throwable $exception) {
            $this->error('The site could not be reached at '.config('app.url').': '.mb_substr($exception->getMessage(), 0, 200));

            return self::FAILURE;
        }

        if (! $answer->successful() || ! is_bool($answer->json('reset'))) {
            $this->error("The site answered with status {$answer->status()} instead of emptying its code cache.");

            return self::FAILURE;
        }

        $this->line($answer->json('reset') ? 'The web server compiles the code anew.' : 'The web server keeps no compiled code.');

        return self::SUCCESS;
    }
}
