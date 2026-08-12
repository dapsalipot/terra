<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>Your group chat for {{ $tripTitle }} has new messages.</strong></p>

    <p>{{ $when }}</p>

    <p><a href="{{ $url }}" style="display:inline-block; background:#2C3826; color:#FBF9F4; text-decoration:none; padding:12px 22px; border-radius:999px; font-weight:600;">Open the chat</a></p>

    <p style="font-size:13px; color:#8A857A;">
        We only send this once an hour at most, however busy the conversation gets.
    </p>
</x-mail.layout>
