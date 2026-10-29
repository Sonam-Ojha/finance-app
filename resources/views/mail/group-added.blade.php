{{-- Someone added you to a group (App\Notifications\AddedToGroup). --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

{{ $addedBy }} added you to **{{ $groupLabel }}** — a group for sharing expenses. Add what you paid for, split it equally or by exact amounts, and see at a glance who owes whom.

<x-mail::button :url="$url">
Open the group
</x-mail::button>

You can also find it under Groups in the Expense Tracker app.

<x-slot:subcopy>
<x-mail::subcopy>
You are getting this because email notifications are on for your Expense Tracker account.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Expense Tracker
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
