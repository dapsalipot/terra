@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full border-ink/15 focus:border-moss focus:ring-moss rounded-xl shadow-sm bg-surface']) }}>
