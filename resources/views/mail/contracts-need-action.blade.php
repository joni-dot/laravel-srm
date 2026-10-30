{{-- The list is an HTML block so Markdown never formats text typed by users, such as contract titles. --}}
<x-mail::message>
{{ $intro }}

<table width="100%" cellpadding="6" style="border-collapse: collapse; margin-bottom: 24px;">
<tr style="text-align: left;"><th>Contract</th><th>Supplier</th><th>Date</th></tr>
@foreach ($items as $item)
<tr style="border-top: 1px solid #e8e5ef;"><td><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></td><td>{{ $item['supplier'] }}</td><td>{{ $item['action'] }}: {{ $item['action_by'] }} ({{ trans_choice('in :count day|in :count days', $item['days_left']) }})</td></tr>
@endforeach
</table>

<x-mail::button :url="$url">
Open the SRM
</x-mail::button>
</x-mail::message>
