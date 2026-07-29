<?php

namespace App\Livewire\Host;

use App\Models\HostProfile;
use App\Support\Accents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    // Identity
    public string $name = '';
    public string $handle = '';
    public string $tagline = '';
    public string $location = '';
    public string $bio = '';

    // Look
    public string $accent = Accents::DEFAULT;
    public $avatar = null;
    public $cover = null;

    // Links + money
    public array $links = ['instagram' => '', 'facebook' => '', 'website' => ''];
    public string $currency = 'PHP';
    /** Each: label, detail, qr (stored path). */
    public array $payment_methods = [];
    /** Staged QR uploads, keyed by payment-method index. Merged into payment_methods on save. */
    public array $payment_qr = [];

    public string $saved = '';

    public function mount(): void
    {
        $user = Auth::user();
        $profile = $user->hostProfile;

        $this->name = $user->name;
        $this->handle = (string) $user->handle;
        $this->tagline = (string) $profile?->tagline;
        $this->location = (string) $profile?->location;
        $this->bio = (string) $profile?->bio;
        $this->accent = $profile?->accent ?: Accents::DEFAULT;
        $this->currency = $profile?->currency ?: 'PHP';
        $this->links = array_merge($this->links, $profile?->links ?? []);
        $this->payment_methods = $profile?->payment_methods ?: [['label' => '', 'detail' => '', 'qr' => null]];
    }

    public function addPaymentMethod(): void
    {
        $this->payment_methods[] = ['label' => '', 'detail' => '', 'qr' => null];
    }

    public function removePaymentMethod(int $i): void
    {
        unset($this->payment_methods[$i], $this->payment_qr[$i]);
        $this->payment_methods = array_values($this->payment_methods);
        $this->payment_qr = array_values($this->payment_qr);
    }

    /** Drop a saved QR without touching the rest of the method. */
    public function removeQr(int $i): void
    {
        if ($path = $this->payment_methods[$i]['qr'] ?? null) {
            $this->deleteIfOwned($path);
        }

        $this->payment_methods[$i]['qr'] = null;
        unset($this->payment_qr[$i]);
    }

    public function save(): void
    {
        $user = Auth::user();

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'handle' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('users', 'handle')->ignore($user->id)],
            'tagline' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:600'],
            'accent' => ['required', Rule::in(Accents::keys())],
            'currency' => ['required', 'string', 'size:3'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'cover' => ['nullable', 'image', 'max:8192'],
            'links.*' => ['nullable', 'string', 'max:120'],
            'payment_methods.*.label' => ['nullable', 'string', 'max:40'],
            'payment_methods.*.detail' => ['nullable', 'string', 'max:80'],
            'payment_qr.*' => ['nullable', 'image', 'max:5120'],
        ]);

        $user->fill(['name' => $this->name, 'handle' => $this->handle]);

        if ($this->avatar) {
            $this->deleteIfOwned($user->avatar);
            $user->avatar = $this->avatar->store('avatars', 'public');
        }

        $user->save();

        $profile = $user->hostProfile ?: new HostProfile(['user_id' => $user->id]);

        if ($this->cover) {
            $this->deleteIfOwned($profile->cover_photo);
            $profile->cover_photo = $this->cover->store('covers', 'public');
        }

        $profile->fill([
            'user_id' => $user->id,
            'tagline' => $this->tagline ?: null,
            'location' => $this->location ?: null,
            'bio' => $this->bio ?: null,
            'accent' => $this->accent,
            'currency' => strtoupper($this->currency),
            'links' => array_filter($this->links),
            // drop rows the host left blank
            'payment_methods' => array_values(array_filter(
                $this->withStagedQrCodes(),
                fn ($m) => filled($m['label'] ?? null) || filled($m['detail'] ?? null),
            )),
        ])->save();

        $this->reset(['avatar', 'cover']);
        $this->saved = 'Profile updated.';
    }

    /**
     * Store any staged QR uploads and fold their paths back into the payment methods,
     * keeping whatever was already saved when no new file was chosen.
     *
     * @return array<int, array{label:string, detail:string, qr:?string}>
     */
    private function withStagedQrCodes(): array
    {
        // Mutate the component's own array, not a copy: if in-memory state drifts from what
        // was persisted, a later removeQr() sees a null path and orphans the file on disk.
        foreach ($this->payment_qr as $i => $file) {
            if (! $file || ! isset($this->payment_methods[$i])) {
                continue;
            }

            $this->deleteIfOwned($this->payment_methods[$i]['qr'] ?? null);
            $this->payment_methods[$i]['qr'] = $file->store('qr', 'public');
        }

        $this->reset('payment_qr');

        return $this->payment_methods;
    }

    /** Remove a superseded upload so old covers/avatars don't pile up on disk. */
    private function deleteIfOwned(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function render()
    {
        return view('livewire.host.profile', [
            'user' => Auth::user()->fresh('hostProfile'),
            'palette' => Accents::PALETTE,
        ])->layout('components.layouts.host', ['title' => 'Peeps · Profile']);
    }
}
