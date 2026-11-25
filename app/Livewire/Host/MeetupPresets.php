<?php

namespace App\Livewire\Host;

use App\Models\HostMeetupPreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class MeetupPresets extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $label = '';

    public string $address = '';

    public string $lat = '';

    public string $lng = '';

    public string $note = '';

    public $image = null;

    public string $saved = '';

    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:160'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'label' => 'meetup name',
            'lat' => 'latitude',
            'lng' => 'longitude',
            'image' => 'photo',
        ];
    }

    protected function messages(): array
    {
        return [
            'lat.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'lng.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'lat.numeric' => 'The latitude should be a number like 14.6220.',
            'lng.numeric' => 'The longitude should be a number like 121.0530.',
        ];
    }

    public function edit(int $id): void
    {
        $preset = Auth::user()->meetupPresets()->findOrFail($id);

        $this->editingId = $preset->id;
        $this->label = $preset->label;
        $this->address = (string) $preset->address;
        $this->lat = (string) ($preset->lat ?? '');
        $this->lng = (string) ($preset->lng ?? '');
        $this->note = (string) $preset->note;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'label', 'address', 'lat', 'lng', 'note', 'image', 'saved']);
    }

    public function save(): void
    {
        $this->validate();

        $preset = $this->editingId
            ? Auth::user()->meetupPresets()->findOrFail($this->editingId)
            : new HostMeetupPreset(['host_id' => Auth::id()]);

        if ($this->image) {
            if ($preset->image && Storage::disk('public')->exists($preset->image)) {
                Storage::disk('public')->delete($preset->image);
            }
            $preset->image = $this->image->store('meetups', 'public');
        }

        $preset->fill([
            'host_id' => Auth::id(),
            'label' => $this->label,
            'address' => $this->address ?: null,
            'note' => $this->note ?: null,
            'lat' => $this->lat !== '' ? $this->lat : null,
            'lng' => $this->lng !== '' ? $this->lng : null,
        ])->save();

        $this->cancel();
        $this->saved = 'Meetup point saved.';
    }

    public function delete(int $id): void
    {
        $preset = Auth::user()->meetupPresets()->findOrFail($id);

        if ($preset->image && Storage::disk('public')->exists($preset->image)) {
            Storage::disk('public')->delete($preset->image);
        }

        $preset->delete();
    }

    public function render()
    {
        return view('livewire.host.meetup-presets', [
            'presets' => Auth::user()->meetupPresets()->get(),
        ])->layout('components.layouts.host', ['title' => 'Tarana · Meetup points']);
    }
}
