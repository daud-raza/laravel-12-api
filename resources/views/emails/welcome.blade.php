<x-mail::message>
# Welcome, {{ $user->name }}!

Thanks for joining **Task Manager**. You can now create tasks, organise them into categories, add tags, and track your progress.

<x-mail::button :url="config('app.url')">
Open Task Manager
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
