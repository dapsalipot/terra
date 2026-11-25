{{--
    One shell for every transactional email.

    Deliberately plain HTML with inline styles: mail clients strip <style> blocks, ignore most of
    what a Tailwind build produces, and the app's CSS is not loaded here at all. The brand carries
    through the wordmark and the two colours, not through a layout that half the inboxes will
    discard anyway.
--}}
<div style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#1F1C15; line-height:1.55; max-width:560px; margin:0 auto; padding:24px;">
    <p style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; font-size:20px; font-weight:600; letter-spacing:-0.02em; color:#2C3826; margin:0 0 24px;">Terra</p>

    {{ $slot }}

    <hr style="border:0; border-top:1px solid #E6E2D8; margin:28px 0 16px;">
    <p style="font-size:12px; color:#8A857A; margin:0;">
        Terra never holds your money — you pay your host directly.
    </p>
</div>
