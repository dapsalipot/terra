<x-mail.layout>
    <p>Hi {{ $hostName }},</p>

    <p><strong>{{ $joiner }} sent a payment for {{ $tripTitle }}.</strong></p>

    <p>
        {{ $when }} · {{ $amount }}
        @if($reference)<br>Reference: <span style="font-family: ui-monospace, monospace;">{{ $reference }}</span>@endif
    </p>

    <p>Check it against your own records, then confirm or reject it on the roster.</p>

    <p><a href="{{ $url }}" style="display:inline-block; background:#2C3826; color:#FBF9F4; text-decoration:none; padding:12px 22px; border-radius:999px; font-weight:600;">Open the roster</a></p>
</x-mail.layout>
