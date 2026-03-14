<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateAdminUser extends Command
{
    protected $signature = 'agency:admin';
    protected $description = 'Create a new admin user interactively';

    public function handle()
    {
        $this->info('Create Admin User');
        $this->line('-----------------');

        // Prompt for email with validation
        $email = $this->askValidEmail('Email address');

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return Command::FAILURE;
        }

        // Prompt for password with validation
        $password = $this->askValidPassword('Password (min 8 characters)');
        $confirmPassword = $this->secret('Confirm password');

        // Validate password confirmation
        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match.');
            return Command::FAILURE;
        }

        // Create user
        $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => bcrypt($password),
        ]);

        $this->info("Admin user created successfully!");
        $this->line("Login at: http://localhost/login");

        return Command::SUCCESS;
    }

    protected function askValidEmail(string $question): string
    {
        while (true) {
            $email = $this->ask($question);
            $validator = Validator::make(['email' => $email], [
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                if (!$this->confirm('Try again?', true)) {
                    throw new \RuntimeException('User cancelled');
                }
                continue;
            }

            return $email;
        }
    }

    protected function askValidPassword(string $question): string
    {
        while (true) {
            $password = $this->secret($question);
            $validator = Validator::make(['password' => $password], [
                'password' => 'required|min:8',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }
                if (!$this->confirm('Try again?', true)) {
                    throw new \RuntimeException('User cancelled');
                }
                continue;
            }

            return $password;
        }
    }
}
