<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The id the app gives an expense or income it queued offline (its outbox),
 * sent as `client_id` and as the `Idempotency-Key` header, the same on every
 * resend. If the first send was saved but its answer was lost, the resend
 * finds that entry instead of saving a second one (users.id + client_id is
 * unique on app_expenses and app_incomes).
 */
class ClientIds
{
    public static function from(Request $request): ?string
    {
        $id = trim((string) ($request->input('client_id') ?? $request->header('Idempotency-Key') ?? ''));

        return $id !== '' && strlen($id) <= 64 ? $id : null;
    }

    /**
     * @template T of Model
     * @param  class-string<T>  $model
     * @return T|null
     */
    public static function find(string $model, int $userId, ?string $clientId): ?Model
    {
        return $clientId === null ? null : $model::where('user_id', $userId)->where('client_id', $clientId)->first();
    }
}
