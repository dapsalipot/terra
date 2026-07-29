<div class="max-w-5xl mx-auto px-6 py-10">
    <h1 class="font-serif font-semibold text-3xl mb-6">Hosts on Peeps</h1>
    <div class="grid md:grid-cols-3 gap-6">
        @foreach($hosts as $host)
            <a href="{{ route('hosts.show', $host) }}"
               class="block rounded-2xl border border-ink/5 bg-surface p-5 hover:shadow-lg transition-shadow">
                <h3 class="font-serif font-semibold text-lg">{{ $host->name }}</h3>
                <p class="text-sm text-ink/60 mt-1 line-clamp-2">{{ $host->hostProfile?->bio }}</p>
            </a>
        @endforeach
    </div>
</div>
