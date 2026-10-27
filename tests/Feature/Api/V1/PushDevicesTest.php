<?php

namespace Tests\Feature\Api\V1;

use App\Models\AdminNotification;
use App\Models\DeviceToken;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class PushDevicesTest extends ApiTestCase
{
    public function test_a_phone_registers_and_removes_its_token(): void
    {
        $user = $this->signIn();

        $this->postJson('/api/v1/me/devices', ['token' => 'phone-1', 'platform' => 'android'])->assertNoContent();
        $this->postJson('/api/v1/me/devices', ['token' => 'phone-1'])->assertNoContent(); // again: still one row
        $this->assertSame(1, DeviceToken::where('user_id', $user->id)->count());

        $this->postJson('/api/v1/me/devices', ['token' => 'x', 'platform' => 'fridge'])->assertUnprocessable();
        $this->postJson('/api/v1/me/devices', [])->assertUnprocessable();

        $this->deleteJson('/api/v1/me/devices', ['token' => 'phone-1'])->assertNoContent();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_a_phone_belongs_to_whoever_signed_in_last(): void
    {
        $first = $this->signIn(['email' => 'first@example.com']);
        $this->postJson('/api/v1/me/devices', ['token' => 'shared-phone'])->assertNoContent();

        $second = $this->makeUser(['email' => 'second@example.com']);
        Sanctum::actingAs($second);
        $this->postJson('/api/v1/me/devices', ['token' => 'shared-phone'])->assertNoContent();
        $this->assertSame($second->id, DeviceToken::firstOrFail()->user_id);

        // The first user cannot remove the second one's phone.
        Sanctum::actingAs($first);
        $this->deleteJson('/api/v1/me/devices', ['token' => 'shared-phone'])->assertNoContent();
        $this->assertSame(1, DeviceToken::count());
    }

    public function test_the_inbox_shows_broadcasts_since_joining_and_messages_to_this_user(): void
    {
        $user  = $this->signIn(['created_at' => Carbon::parse('2026-10-01')]);
        $other = $this->makeUser(['email' => 'other@example.com']);

        $old = AdminNotification::create(['title' => 'Before you joined', 'body' => 'b', 'audience' => 'all', 'status' => 'sent']);
        $old->forceFill(['created_at' => Carbon::parse('2026-09-01')])->save();
        AdminNotification::create(['title' => 'For everyone', 'body' => 'b', 'audience' => 'all', 'status' => 'sent']);
        AdminNotification::create(['title' => 'For you', 'body' => 'b', 'audience' => 'user', 'target_user_id' => $user->id, 'status' => 'sent']);
        AdminNotification::create(['title' => 'For someone else', 'body' => 'b', 'audience' => 'user', 'target_user_id' => $other->id, 'status' => 'sent']);

        $titles = collect($this->getJson('/api/v1/notifications')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertEqualsCanonicalizing(['For everyone', 'For you'], $titles);
        $this->assertSame(['id', 'title', 'body', 'sent_at'], array_keys($this->getJson('/api/v1/notifications')->json('data.0')));
    }
}
