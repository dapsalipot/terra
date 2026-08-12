<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>A seat opened on {{ $tripTitle }}.</strong></p>

    <p>{{ $when }} · led by {{ $hostName }}</p>

    <p>Seats go to whoever books first, so this is worth doing now rather than later.</p>

    <p><a href="{{ $url }}" style="display:inline-block; background:#2C3826; color:#FBF9F4; text-decoration:none; padding:12px 22px; border-radius:999px; font-weight:600;">Book the seat</a></p>

    <p style="font-size:13px; color:#8A857A;">You asked us to tell you when this trip had space. We only send this once.</p>
</x-mail.layout>
