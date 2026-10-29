<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    protected $signature = 'app:create-user';

    protected $description = 'Create a user who can sign in to the SRM';

    public function handle(): int
    {
        $input = [
            'name' => text('Name', required: true),
            'email' => Str::lower(text('Email', required: true)),
            'password' => password('Password', required: true),
            'password_confirmation' => password('Confirm password', required: true),
        ];

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($validator->safe()->only(['name', 'email', 'password']));

        $this->components->info("User {$user->email} created.");

        return self::SUCCESS;
    }
}
