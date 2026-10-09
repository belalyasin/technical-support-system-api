<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $registration = [
        'name' => 'Sami Customer',
        'email' => 'sami@example.com',
        'phone' => '0591234567',
        'password' => 'secret123',
    ];

    public function test_registration_creates_a_customer_account(): void
    {
        $this->postJson('/api/register', $this->registration)->assertCreated();

        $user = User::where('email', 'sami@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->isStaff());
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_registration_cannot_grant_a_staff_role(): void
    {
        Role::findOrCreate('super admin', 'user');

        $this->postJson('/api/register', $this->registration + ['role' => 'super admin'])
            ->assertStatus(400)
            ->assertJson(['message' => 'Only an administrator can create accounts with that role.']);

        $this->assertDatabaseMissing('users', ['email' => 'sami@example.com']);
    }

    public function test_login_returns_a_token_that_authenticates_requests(): void
    {
        $user = $this->customer(['password' => Hash::make('secret123')]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('roles.0', 'user')
            ->assertJsonMissingPath('user.verification_code');

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))
            ->getJson('/api/current-user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $user = $this->customer(['password' => Hash::make('secret123')]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/current-user')->assertUnauthorized();
        $this->getJson('/api/conversations')->assertUnauthorized();
        $this->getJson('/api/default-messages')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->customer();
        $token = $user->createToken('User-Token')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_forgot_password_emails_a_code_without_revealing_it(): void
    {
        Mail::fake();
        $user = $this->customer();

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $this->assertArrayNotHasKey('code', $response->json());
        Mail::assertSent(VerificationCodeMail::class, fn ($mail) => $mail->hasTo($user->email)
            && preg_match('/^\d{4}$/', $mail->code) === 1
            && Hash::check($mail->code, $user->fresh()->verification_code));
    }

    public function test_forgot_password_gives_the_same_answer_for_an_unknown_email(): void
    {
        Mail::fake();
        $known = $this->postJson('/api/forgot-password', ['email' => $this->customer()->email])->json();

        $unknown = $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json();

        $this->assertSame($known, $unknown);
        Mail::assertSentCount(1);
    }

    public function test_password_can_be_reset_with_the_emailed_code(): void
    {
        $user = $this->customer();
        $user->createToken('User-Token');
        $code = $user->issueVerificationCode();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => 'new-secret-1',
            'password_confirmation' => 'new-secret-1',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-1', $user->password));
        $this->assertNull($user->verification_code);
        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_password_reset_rejects_a_wrong_or_expired_code(): void
    {
        $user = $this->customer();
        $code = $user->issueVerificationCode();
        $payload = ['email' => $user->email, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'];
        $wrong = $code === '1234' ? '4321' : '1234';

        $this->postJson('/api/reset-password', $payload + ['code' => $wrong])->assertStatus(400);

        $this->travel(16)->minutes();
        $this->postJson('/api/reset-password', $payload + ['code' => $code])->assertStatus(400);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_a_user_can_change_their_own_email_with_a_code_sent_to_the_new_address(): void
    {
        Mail::fake();
        $user = $this->customer();
        $other = $this->customer();
        Sanctum::actingAs($user);

        $this->postJson('/api/initiateEmailChange', ['new_email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonMissingPath('verificationCode');

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('new@example.com');
        });

        $this->postJson('/api/confirmEmailChange', ['new_email' => 'new@example.com', 'code' => $code])->assertOk();

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNotSame('new@example.com', $other->fresh()->email);
    }

    public function test_email_change_needs_the_right_code(): void
    {
        Mail::fake();
        $user = $this->customer();
        Sanctum::actingAs($user);
        $originalEmail = $user->email;
        $code = $user->issueVerificationCode();
        $wrong = $code === '1234' ? '4321' : '1234';

        $this->postJson('/api/confirmEmailChange', ['new_email' => 'new@example.com', 'code' => $wrong])
            ->assertStatus(400);

        $this->assertSame($originalEmail, $user->fresh()->email);
    }

    public function test_a_user_can_change_their_own_phone_number(): void
    {
        Mail::fake();
        $user = $this->customer();
        Sanctum::actingAs($user);

        $this->postJson('/api/initiatePhoneChange', ['new_phone' => '0597654321'])->assertOk();

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code, $user) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        $this->postJson('/api/confirmPhoneChange', ['new_phone' => '0597654321', 'code' => $code])->assertOk();

        $this->assertSame('0597654321', $user->fresh()->phone);
    }
}
