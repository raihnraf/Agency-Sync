<?php

namespace Tests\Unit\Console;

use App\Console\Commands\CreateAdminUser;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_prompts_for_email(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_command_prompts_for_password(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertExitCode(Command::SUCCESS);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }

    public function test_command_validates_email_format(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'not-an-email')
            ->expectsOutput('The email must be a valid email address.')
            ->expectsQuestion('Try again?', 'no')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseMissing('users', [
            'email' => 'not-an-email',
        ]);
    }

    public function test_command_checks_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsOutput('A user with email admin@example.com already exists.')
            ->assertExitCode(Command::FAILURE);

        $this->assertEquals(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_command_prompts_retry_on_validation_failure(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'not-an-email')
            ->expectsOutput('The email must be a valid email address.')
            ->expectsQuestion('Try again?', 'yes')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_command_validates_password_length(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'pass')
            ->expectsOutput('The password must be at least 8 characters.')
            ->expectsQuestion('Try again?', 'no')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_command_confirms_password(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'different123')
            ->expectsOutput('Passwords do not match.')
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_command_rejects_mismatched_passwords(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'different123')
            ->expectsOutput('Passwords do not match.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_creates_user_successfully(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'name' => 'Admin',
        ]);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }

    public function test_command_outputs_login_url(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->expectsOutput('Admin user created successfully!')
            ->expectsOutput('Login at: http://localhost/login')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_created_user_can_login(): void
    {
        $this->artisan('agency:admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsQuestion('Confirm password', 'password123')
            ->assertExitCode(Command::SUCCESS);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);

        // Verify password is correctly hashed
        $this->assertTrue(\Hash::check('password123', $user->password));
    }
}
