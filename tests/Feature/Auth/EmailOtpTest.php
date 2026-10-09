<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailOtpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registering_sends_a_code_and_redirects_to_the_verify_screen_without_logging_in(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('verification.otp'));

        $this->assertGuest();

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertNotNull($user->email_otp);
        $this->assertNotNull($user->email_otp_expires_at);
    }

    #[Test]
    public function the_correct_code_verifies_the_account_and_logs_in(): void
    {
        $user = User::factory()->unverified()->create([
            'email_otp' => Hash::make('123456'),
            'email_otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['otp_user_id' => $user->id])
            ->post(route('verification.otp.verify'), ['code' => '123456'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->email_otp);
    }

    #[Test]
    public function the_wrong_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create([
            'email_otp' => Hash::make('123456'),
            'email_otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['otp_user_id' => $user->id])
            ->post(route('verification.otp.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function an_expired_code_is_rejected(): void
    {
        $user = User::factory()->unverified()->create([
            'email_otp' => Hash::make('123456'),
            'email_otp_expires_at' => now()->subMinute(),
        ]);

        $this->withSession(['otp_user_id' => $user->id])
            ->post(route('verification.otp.verify'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    #[Test]
    public function resending_issues_a_fresh_code(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email_otp' => Hash::make('123456'),
            'email_otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['otp_user_id' => $user->id])
            ->post(route('verification.otp.resend'))
            ->assertRedirect();

        $this->assertFalse(Hash::check('123456', $user->fresh()->email_otp));
    }

    #[Test]
    public function visiting_the_verify_screen_without_a_pending_registration_redirects_to_register(): void
    {
        $this->get(route('verification.otp'))->assertRedirect(route('register'));
    }

    #[Test]
    public function logging_in_with_correct_credentials_but_an_unverified_email_is_sent_back_to_the_code_screen(): void
    {
        $user = User::factory()->unverified()->create(['password' => Hash::make('password')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('verification.otp'));

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->email_otp);
    }

    #[Test]
    public function an_already_verified_user_in_session_cannot_be_reverified_this_way(): void
    {
        $user = User::factory()->create(); // email_verified_at already set by the factory

        $this->withSession(['otp_user_id' => $user->id])
            ->get(route('verification.otp'))
            ->assertRedirect(route('register'));
    }
}
