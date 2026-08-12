<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>{{ $hostName }} confirmed your spot on {{ $tripTitle }}.</strong></p>

    <p>
        {{ $when }}@if($meetup) · meeting at {{ $meetup }}@endif
    </p>

    @if($due > 0)
        <p style="color:#A8761F;"><strong>{{ $due }} is still to pay.</strong> You can send it from your booking page.</p>
    @else
        <p>Nothing further to pay before you go.</p>
    @endif

    <p><a href="{{ $url }}" style="display:inline-block; background:#2C3826; color:#FBF9F4; text-decoration:none; padding:12px 22px; border-radius:999px; font-weight:600;">Open your booking</a></p>

    <p style="font-size:13px; color:#8A857A;">Save that link — it is how you get back to this booking.</p>
</x-mail.layout>
