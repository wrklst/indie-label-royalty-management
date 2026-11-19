<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('royalties:create-user
    {email : The address the person signs in with}
    {--name= : Their name. Without it the part of the address before the @ is used}')]
#[Description('Create an account for someone who will set up a label. Nobody can register on their own')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error("“{$email}” is not an email address.");

            return self::FAILURE;
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error("{$email} has an account already.");

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email' => $email,
            'password' => Str::password(32),
        ]);
        // The first account looks after the installation: it may change the file storage.
        $user->forceFill(['email_verified_at' => now(), 'installation_admin' => User::query()->where('installation_admin', true)->doesntExist()])->save();

        $this->info("Account created. {$email} signs in at ".route('login.code.create').' with a code sent by email.');

        return self::SUCCESS;
    }
}
