{{--
    Who to ring if this traveller is hurt.

    On the manifest row, not only in the drawer. The manifest is the page a host reads from on the
    mountain, and an emergency contact one tap away behind a drawer is not at hand when somebody is
    on the ground. A traveller with nobody to call is stated outright in terra rather than shown
    as a blank — a legacy booking made before this was asked for is exactly the gap to notice.
--}}
<span class="block text-xs truncate {{ $p->hasEmergencyContact() ? 'text-ink/70' : 'text-terra font-semibold' }}">
    @if($p->hasEmergencyContact())
        In an emergency: {{ $p->emergency_contact_name }} · {{ $p->emergency_contact_phone }}
    @else
        No emergency contact
    @endif
</span>
