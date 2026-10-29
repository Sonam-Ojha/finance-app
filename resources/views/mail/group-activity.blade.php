{{-- Something happened in a group, for a member with no phone signed in (App\Notifications\GroupActivity). --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

{{ $headline }}

@foreach($lines as $line)
**{{ $line }}**

@endforeach
<x-mail::button :url="$url">
Open the group
</x-mail::button>

You can also find it under Groups in the Expense Tracker app — sign in on your phone to get these as notifications instead.

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
