{{-- The birthday email (App\Notifications\BirthdayWish). --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

<p style="font-size: 44px; text-align: center; margin: 8px 0;">🎂</p>

<p style="font-size: 20px; font-weight: bold; color: #002e62; text-align: center; margin: 0 0 16px;">Happy birthday{{ $first ? ", {$first}" : '' }}!</p>

Wishing you a wonderful year ahead — good health, good times, and money that goes where you want it to.

Thank you for keeping track with us. Treat yourself today; you have earned it.

<x-slot:subcopy>
<x-mail::subcopy>
You are getting this because you added your date of birth to Expense Tracker. You can remove it in Account information.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Expense Tracker
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
