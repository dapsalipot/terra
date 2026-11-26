<?php

namespace App\Livewire\Hosts;

use App\Models\Post;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\PageMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public User $user;

    public string $postBody = '';

    /** @var array uploaded files for a single multi-image post */
    public $postImages = [];

    #[Url(as: 'tab')]
    public string $tab = 'trips';

    public function mount(User $user): void
    {
        abort_unless($user->isHost(), 404);
        $this->user = $user->load('hostProfile');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['trips', 'wall', 'reviews', 'about'], true) ? $tab : 'trips';
    }

    public function follow(): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }
        Auth::user()->follow($this->user);
    }

    public function unfollow(): void
    {
        Auth::user()->unfollow($this->user);
    }

    public function getIsOwnerProperty(): bool
    {
        return Auth::id() === $this->user->id;
    }

    public function getIsFollowingProperty(): bool
    {
        return Auth::check() && Auth::user()->isFollowing($this->user);
    }

    public function removeStagedImage(int $i): void
    {
        unset($this->postImages[$i]);
        $this->postImages = array_values($this->postImages);
    }

    public function publishPost(): void
    {
        abort_unless($this->isOwner, 403);

        $this->validate([
            'postBody' => ['required', 'string', 'max:2000'],
            'postImages' => ['nullable', 'array', 'max:10'],
            'postImages.*' => ['image', 'max:8192'],
        ]);

        $post = Post::create([
            'host_id' => $this->user->id,
            'type' => 'update',
            'body' => $this->postBody,
        ]);

        foreach (array_values($this->postImages) as $i => $file) {
            $post->images()->create(['path' => $file->store('posts', 'public'), 'position' => $i]);
        }

        $this->reset(['postBody', 'postImages']);
        $this->tab = 'wall';
        $this->user->refresh();
    }

    public function deletePost(int $id): void
    {
        abort_unless($this->isOwner, 403);

        $post = Post::with('images')->where('host_id', $this->user->id)->findOrFail($id);

        foreach ($post->images as $image) {
            if (Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        }

        $post->delete(); // post_images cascade
    }

    public function render()
    {
        // What a listing would show this viewer, except on a host's own page, where they see every trip they have published.
        $visible = fn ($q) => $this->isOwner ? $q->published() : $q->listedFor(Auth::user());

        $visibleTrips = $visible($this->user->trips())->with(['place', 'host.hostProfile', 'departures', 'openDepartures', 'inclusionItems'])->latest()->get();
        $trips = Trip::collapseSchedules($visibleTrips);
        $posts = $this->user->posts()->with(['trip', 'images'])->latest()->get();

        // Reviews of the host themselves, plus reviews left on any of their trips.
        $reviews = Review::visible()
            ->with(['joiner', 'reviewable'])
            ->where(fn ($q) => $q
                ->where(fn ($h) => $h->where('reviewable_type', User::class)->where('reviewable_id', $this->user->id))
                ->orWhere(fn ($t) => $t->where('reviewable_type', Trip::class)
                    ->whereIn('reviewable_id', $this->user->trips()->select('id'))))
            ->whereNotNull('body')
            ->latest()
            ->get();

        // What is coming up across every one of their trips, soonest first: on Facebook this is the post people scroll for.
        $upcoming = TripDeparture::query()
            ->with('trip.place')
            ->whereHas('trip', fn ($t) => $visible($t)->where('host_id', $this->user->id))
            ->where('status', 'open')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        // A review or a wall post names its trip only if this viewer could find that trip. The review and the post stay
        // either way, so a private group trip's name does not leak through what was said about it.
        $visibleTripIds = $visibleTrips->modelKeys();

        // What the link looks like when it is shared, and the tab's title with it.
        $meta = PageMeta::host($this->user);

        return view('livewire.hosts.show', compact('trips', 'posts', 'reviews', 'upcoming', 'visibleTripIds'))
            ->layout('components.layouts.site', ['meta' => $meta, 'title' => $meta['title']]);
    }
}
