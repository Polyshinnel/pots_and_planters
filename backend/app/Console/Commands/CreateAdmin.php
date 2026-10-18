<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Interactively create an authorized Filament and Horizon administrator';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively with a TTY.');

            return self::FAILURE;
        }

        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: function (string $value): ?string {
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'Enter a valid email address.';
            }

            return User::where('email', $value)->exists() ? 'This email already exists.' : null;
        });
        $secret = password('Password (at least 12 characters)', required: true,
            validate: fn (string $value): ?string => strlen($value) >= 12 ? null : 'Use at least 12 characters.');
        password('Confirm password', required: true,
            validate: fn (string $value): ?string => hash_equals($secret, $value) ? null : 'Passwords do not match.');

        // is_admin is deliberately excluded from mass assignment.
        $user = new User(['name' => $name, 'email' => $email, 'password' => $secret]);
        $user->is_admin = true;
        $user->save();
        $this->info('Administrator created. Sign in at '.url('/admin'));

        return self::SUCCESS;
    }
}
