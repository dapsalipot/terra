<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>Your booking on {{ $tripTitle }} has been cancelled.</strong></p>

    @if($reason)
        <p style="background:#FBEDE7; border-left:3px solid #BC4B26; padding:12px 14px; margin:16px 0;">{{ $reason }}</p>
    @endif

    @if($paid > 0)
        <p>You had paid {{ $paid }} — arrange the refund with {{ $hostName }} directly.</p>
    @endif
</x-mail.layout>
