{{-- The list is an HTML block so Markdown never formats text typed by users, such as supplier names. --}}
<x-mail::message>
{{ $intro }}

<table width="100%" cellpadding="6" style="border-collapse: collapse; margin-bottom: 24px;">
<tr style="text-align: left;"><th>Supplier</th><th>Certificate</th><th>Expires</th></tr>
@foreach ($items as $item)
<tr style="border-top: 1px solid #e8e5ef;"><td><a href="{{ $item['url'] }}">{{ $item['supplier'] }}</a></td><td>{{ $item['type'] }}</td><td>{{ $item['expires_on'] }} ({{ trans_choice('in :count day|in :count days', $item['days_left']) }})</td></tr>
@endforeach
</table>

<x-mail::button :url="$url">
Open the SRM
</x-mail::button>
</x-mail::message>
