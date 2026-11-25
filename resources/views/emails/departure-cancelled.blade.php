<x-mail.layout>
    <p>Hi {{ $name }},</p>

    <p><strong>{{ $hostName }} has cancelled {{ $tripTitle }} on {{ $when }}.</strong></p>

    @if($reason)
        <p style="background:#FBEDE7; border-left:3px solid #BC4B26; padding:12px 14px; margin:16px 0;">{{ $reason }}</p>
    @endif

    @if($paid > 0)
        <p><strong>You had paid {{ $paid }}.</strong> Tarana never held that money — arrange the refund with {{ $hostName }} directly.</p>
    @endif

    <p>Nothing is owed from here, and your seat has been released.</p>
</x-mail.layout>
