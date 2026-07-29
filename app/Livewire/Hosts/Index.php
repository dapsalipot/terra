<?php

namespace App\Livewire\Hosts;

use App\Models\User;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $hosts = User::where('role', 'host')
            ->whereNotNull('handle')
            ->with('hostProfile')
            ->get();

        return view('livewire.hosts.index', compact('hosts'))
            ->layout('components.layouts.site');
    }
}
