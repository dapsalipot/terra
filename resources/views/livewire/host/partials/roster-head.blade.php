{{-- Column labels, from sm up. On a phone the row is a stacked card and headers would label
     nothing. --}}
<div class="hidden sm:grid gap-3 px-4 py-2.5 bg-ink/[.02] border-b border-ink/8
            text-[10px] uppercase tracking-[0.09em] font-semibold text-ink/70
            {{ $mode === 'checkin' ? 'grid-cols-[1fr_11rem_6rem]' : 'grid-cols-[1fr_12rem_8.5rem_2.75rem]' }}">
    <span>Traveller</span>
    <span>{{ $mode === 'money' ? 'Status' : ($mode === 'manifest' ? 'Details' : 'Arrived') }}</span>
    <span class="{{ $mode === 'checkin' ? '' : 'text-right' }}">{{ $mode === 'money' ? 'Balance' : ($mode === 'manifest' ? 'Contact' : 'Time') }}</span>
    @if($mode !== 'checkin')<span></span>@endif
</div>
