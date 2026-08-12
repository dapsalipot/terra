<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>Your seat on {{ $tripTitle }} is held for {{ $remaining }} more.</strong></p>

    <p>After that it is released and someone else can take it. If you have already paid, upload your receipt so {{ $hostName }} can confirm you.</p>

    <p><a href="{{ $url }}" style="display:inline-block; background:#2C3826; color:#FBF9F4; text-decoration:none; padding:12px 22px; border-radius:999px; font-weight:600;">Pay now</a></p>
</x-mail.layout>
