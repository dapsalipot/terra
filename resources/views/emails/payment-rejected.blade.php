<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>{{ $hostName }} could not verify your payment for {{ $tripTitle }}.</strong></p>

    @if($reason)
        <p style="background:#FBEDE7; border-left:3px solid #BC4B26; padding:12px 14px; margin:16px 0;">{{ $reason }}</p>
    @endif

    <p>Your seat is still held. Send the payment again from your booking page and upload the receipt.</p>

    <p><a href="{{ $url }}" style="display:inline-block; background:#2C3826; color:#FBF9F4; text-decoration:none; padding:12px 22px; border-radius:999px; font-weight:600;">Try again</a></p>
</x-mail.layout>
