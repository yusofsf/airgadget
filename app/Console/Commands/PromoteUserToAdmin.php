<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    protected $signature = 'user:promote-admin {phone : Registered mobile number}';

    protected $description = 'Promote an existing registered user to store administrator';

    public function handle(): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        $user = User::where('phone_number', $phone)->first();

        if (! $user) {
            $this->error('No registered user was found for that mobile number.');

            return self::FAILURE;
        }

        if (! $this->confirm("Promote user {$user->id} ({$phone}) to administrator?")) {
            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();
        $this->info('The user is now an administrator.');

        return self::SUCCESS;
    }
}
