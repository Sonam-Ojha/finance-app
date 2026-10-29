{{-- An admin's notification sent by email. The admin page's Email preview
     (admin/notifications) draws this same layout. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Expense Tracker
</x-mail::header>
</x-slot:header>

**{{ $greeting }}**

<p style="font-size: 18px; font-weight: bold; color: #3d4852; margin: 16px 0 8px;">{{ $title }}</p>

{!! nl2br(e($body)) !!}

<x-mail::button :url="url('/dashboard')">
Open Expense Tracker
</x-mail::button>

<x-slot:subcopy>
<x-mail::subcopy>
You are getting this because email notifications are on for your Expense Tracker account. You can turn them off in Settings.
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Expense Tracker
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
