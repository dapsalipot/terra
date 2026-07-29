<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-pine border border-transparent rounded-full font-semibold text-sm text-cream hover:bg-pine/90 focus:outline-none focus:ring-2 focus:ring-moss focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
