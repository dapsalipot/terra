@props(['label' => 'Photos', 'multiple' => false, 'max' => 10, 'hint' => null, 'prompt' => null, 'sub' => null])
@php($model = $attributes->wire('model')->value())

{{-- Drag-drop uploader. The real <input type="file"> stays in the DOM (visually hidden) so it
     keeps working without JS and Livewire's own upload plumbing is untouched. --}}
<div x-data="{
        over: false,
        drop(e) {
            this.over = false;
            this.$refs.input.files = e.dataTransfer.files;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
     }">
    @if($label)
        <span class="block text-sm font-semibold text-ink/70 mb-1.5">{{ $label }}</span>
    @endif

    <label @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop($event)"
           :class="over ? 'border-moss bg-moss/5' : 'border-ink/20 hover:border-ink/35 hover:bg-ink/[.02]'"
           class="flex flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed px-6 py-7 cursor-pointer transition-colors">
        <svg viewBox="0 0 24 24" class="w-6 h-6 text-ink/65" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 16V4M7.5 8.5L12 4l4.5 4.5M4 16v2.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16"/>
        </svg>
        {{-- Overridable wording: a payment receipt and a trip gallery are not the same ask,
             and "Drag a photo here" on a proof-of-payment field reads as the wrong thing. --}}
        <span class="text-sm font-semibold">{{ $prompt ?? 'Drag '.($multiple ? 'photos' : 'a photo').' here' }}</span>
        <span class="text-xs text-ink/65">{{ $sub ?? 'or click to browse'.($multiple ? ' · up to '.$max : '') }}</span>
        <input x-ref="input" type="file" accept="image/*" {{ $multiple ? 'multiple' : '' }}
               {{ $attributes->only(['wire:model', 'dusk']) }} class="sr-only">
    </label>

    @if($hint)<p class="text-[11px] text-ink/65 mt-1.5">{{ $hint }}</p>@endif

    <p wire:loading wire:target="{{ $model }}" class="text-xs text-ink/65 mt-2">Uploading…</p>

    {{-- Previews.

         Two kinds of value land here: a live Livewire upload (TemporaryUploadedFile) and a
         plain storage path carried over from a copied trip. Only the first was previewed
         before, so copying a trip looked like it had lost every photo. --}}
    @php($value = data_get($this, $model))
    @php($src = fn ($v) => match (true) {
        $v instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile => $v->temporaryUrl(),
        is_string($v) && $v !== '' => \Illuminate\Support\Facades\Storage::disk('public')->url($v),
        default => null,
    })

    @if($multiple)
        @if(is_array($value) && count($value))
            <div class="grid grid-cols-4 gap-2 mt-3">
                @foreach($value as $i => $file)
                    @continue(! $url = $src($file))
                    <div class="relative" wire:key="up-{{ $model }}-{{ $i }}">
                        <img src="{{ $url }}" alt="" class="aspect-square w-full object-cover rounded-lg">
                        <span class="absolute bottom-1 left-1 rounded bg-ink/65 px-1.5 text-[10px] font-bold text-cream">{{ $i + 1 }}</span>
                        @if(is_string($file))
                            <span class="absolute top-1 right-1 rounded bg-moss px-1.5 text-[10px] font-bold text-cream">kept</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @else
        @if($url = $src($value))
            <div class="relative mt-3">
                <img src="{{ $url }}" alt="" class="h-36 w-full object-cover rounded-xl">
                @if(is_string($value))
                    <span class="absolute top-2 right-2 rounded bg-moss px-2 py-0.5 text-[10px] font-bold text-cream">Kept from the original</span>
                @endif
            </div>
        @endif
    @endif
</div>
