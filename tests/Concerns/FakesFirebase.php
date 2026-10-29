<?php

namespace Tests\Concerns;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/** Firebase set up with a throwaway key, and FCM answered by Http::fake(). */
trait FakesFirebase
{
    private string $firebaseKeyPath;

    protected function fakeFirebase(): void
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // Windows builds of PHP ship their openssl.cnf beside the binary and do not find it on their own.
        $config = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($config)) $options['config'] = $config;
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);

        $this->firebaseKeyPath = storage_path('framework/testing/firebase-' . uniqid() . '.json');
        @mkdir(dirname($this->firebaseKeyPath), 0777, true);
        file_put_contents($this->firebaseKeyPath, json_encode([
            'type'         => 'service_account',
            'project_id'   => 'expense-tracker-test',
            'client_email' => 'firebase-adminsdk@expense-tracker-test.iam.gserviceaccount.com',
            'private_key'  => $pem,
            'token_uri'    => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.firebase.credentials' => $this->firebaseKeyPath]);
        $this->beforeApplicationDestroyed(fn () => @unlink($this->firebaseKeyPath));

        Http::fake(fn (Request $request) => str_contains($request->url(), 'oauth2.googleapis.com/token')
            ? Http::response(['access_token' => 'ya29.test-token', 'expires_in' => 3600])
            : Http::response(['name' => 'projects/expense-tracker-test/messages/1']));
    }

    protected function phone(User $user, string $token = 'phone-token'): void
    {
        DeviceToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);
    }

    /** @return list<array{title: string, body: string}> the pushes FCM was asked to send */
    protected function pushes(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), 'messages:send'))
            ->map(fn (Request $r) => $r['message']['notification'])
            ->values()->all();
    }
}
