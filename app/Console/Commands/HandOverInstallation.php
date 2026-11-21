<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('royalties:hand-over-installation {email : The account that looks after the installation from now on}')]
#[Description('Hand the role of installation admin (file storage, updates, local AI address) to another account')]
class HandOverInstallation extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("{$email} has no account.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($user): void {
            User::query()->where('installation_admin', true)->whereKeyNot($user->id)->update(['installation_admin' => false]);
            $user->forceFill(['installation_admin' => true])->save();
        });

        $this->info("{$user->email} is now the installation admin.");

        return self::SUCCESS;
    }
}
