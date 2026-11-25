{{--
    One shell for every transactional email.

    Deliberately plain HTML with inline styles: mail clients strip <style> blocks, ignore most of
    what a Tailwind build produces, and the app's CSS is not loaded here at all. The brand carries
    through the icon, the wordmark and the two colours, not through a layout that half the inboxes will
    discard anyway.
--}}
<div style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#1F1C15; line-height:1.55; max-width:560px; margin:0 auto; padding:24px;">
    <p style="margin:0 0 24px;">
        <img src="{{ url('/apple-touch-icon.png') }}" width="28" height="28" alt="" style="vertical-align:middle; border:0; border-radius:7px; margin-right:8px;">
        <span style="vertical-align:middle; font-family: Fredoka, ui-rounded, 'SF Pro Rounded', Nunito, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size:22px; font-weight:600; letter-spacing:-0.01em; text-transform:lowercase; color:#2C3826;">Tarana</span>
    </p>

    {{ $slot }}

    <hr style="border:0; border-top:1px solid #E6E2D8; margin:28px 0 16px;">
    <p style="font-size:12px; color:#8A857A; margin:0;">
        Tarana never holds your money — you pay your host directly.
    </p>
</div>
