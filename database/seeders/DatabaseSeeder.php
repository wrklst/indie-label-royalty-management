<?php

namespace Database\Seeders;

use App\Enums\LabelRole;
use App\Models\Label;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $label = Label::factory()->create(['name' => 'Demo Label', 'slug' => 'demo-label', 'contact_email' => $user->email]);
        $label->users()->attach($user, ['role' => LabelRole::Admin->value]);
        $user->forceFill(['current_label_id' => $label->id])->save();
    }
}
