{{-- The email-verification code (App\Notifications\EmailCodeNotification). --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

Use this code to confirm your email address:

<p style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #002e62; text-align: center; margin: 24px 0;">{{ $code }}</p>

It works for {{ $minutes }} minutes. Do not share it with anyone — Expense Tracker will never ask you for it.

<x-slot:subcopy>
<x-mail::subcopy>
If you did not create an Expense Tracker account, you can ignore this email.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Expense Tracker
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
