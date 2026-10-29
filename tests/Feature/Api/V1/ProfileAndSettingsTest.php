<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Carbon;

class ProfileAndSettingsTest extends ApiTestCase
{
    public function test_a_birthday_is_saved_kept_and_cleared(): void
    {
        $this->signIn(['email' => 'me@example.com']);

        $this->patchJson('/api/v1/me/profile', ['name' => 'Rishi', 'email' => 'me@example.com', 'birthday' => '1998-05-14'])
            ->assertOk()
            ->assertJsonPath('data.birthday', '1998-05-14');

        // Older app versions send only name and email.
        $this->patchJson('/api/v1/me/profile', ['name' => 'Rishi D', 'email' => 'me@example.com'])
            ->assertOk()
            ->assertJsonPath('data.birthday', '1998-05-14');
        $this->getJson('/api/v1/me')->assertJsonPath('data.birthday', '1998-05-14');

        $this->patchJson('/api/v1/me/profile', ['birthday' => null])->assertOk()->assertJsonPath('data.birthday', null);
    }

    public function test_an_impossible_birthday_is_rejected(): void
    {
        $this->signIn();
        $message = 'Choose a real date of birth.';

        $this->patchJson('/api/v1/me/profile', ['birthday' => Carbon::now()->addMonth()->toDateString()])
            ->assertUnprocessable()->assertJsonPath('errors.birthday.0', $message);
        // Not yet a year old.
        $this->patchJson('/api/v1/me/profile', ['birthday' => Carbon::now()->subMonths(6)->toDateString()])
            ->assertJsonPath('errors.birthday.0', $message);
        $this->patchJson('/api/v1/me/profile', ['birthday' => Carbon::now()->subYears(130)->toDateString()])
            ->assertJsonPath('errors.birthday.0', $message);
        $this->patchJson('/api/v1/me/profile', ['birthday' => '14-05-1998'])
            ->assertJsonPath('errors.birthday.0', $message);

        $this->patchJson('/api/v1/me/profile', ['birthday' => Carbon::now()->subYears(119)->toDateString()])->assertOk();
    }

    public function test_the_user_payload_is_the_same_everywhere(): void
    {
        $this->signIn();
        $keys = ['id', 'name', 'email', 'email_verified', 'onboarded', 'has_pin', 'has_password', 'currency', 'theme_mode', 'avatar_url', 'birthday', 'email_notifications', 'daily_reminder_at', 'budget_alert_push', 'monthly_summary'];

        $this->assertSame($keys, array_keys($this->getJson('/api/v1/me')->json('data')));
        $this->assertSame($keys, array_keys($this->getJson('/api/v1/bootstrap')->json('data.user')));
        $this->getJson('/api/v1/bootstrap')
            ->assertJsonPath('data.user.has_password', true)
            ->assertJsonPath('data.user.email_notifications', true)
            ->assertJsonPath('data.currencies', []);
    }

    public function test_email_notifications_can_be_turned_off_and_on(): void
    {
        $user = $this->signIn();

        $this->putJson('/api/v1/settings/email-notifications', ['enabled' => false])->assertNoContent();
        $this->assertFalse($user->fresh()->email_notifications);
        $this->getJson('/api/v1/me')->assertJsonPath('data.email_notifications', false);

        $this->putJson('/api/v1/settings/email-notifications', ['enabled' => true])->assertNoContent();
        $this->assertTrue($user->fresh()->email_notifications);

        $this->putJson('/api/v1/settings/email-notifications', [])->assertUnprocessable();
    }
}
