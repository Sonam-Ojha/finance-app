{{-- Support answered a help request (App\Notifications\SupportReplied). --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

We replied to your request **“{{ $subject }}”**:

<x-mail::panel>
{!! nl2br(e($reply)) !!}
</x-mail::panel>

<x-mail::button :url="$url">
Open the conversation
</x-mail::button>

Write back from Help & support on the website, or in the app under Settings.

<x-slot:subcopy>
<x-mail::subcopy>
You are getting this because you asked Expense Tracker support for help.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Expense Tracker
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
