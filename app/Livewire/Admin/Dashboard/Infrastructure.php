<?php

namespace App\Livewire\Admin\Dashboard;

use Illuminate\View\View;
use Livewire\Component;

/**
 * Reserved for infrastructure this panel does not manage yet.
 *
 * Deliberately empty and deliberately present: this is where a VPN node fleet, inference
 * workers, regional capacity or egress usage will live. It is a real tab today so the
 * dashboard does not visibly change shape the day those land — the view is replaced, the
 * navigation is not.
 *
 * Nothing about the running Laravel app belongs here — queue depth, failed jobs and the
 * scheduler heartbeat are on the Overview tab, since they describe this app rather than
 * the estate it will eventually manage.
 */
class Infrastructure extends Component
{
    public string $selectedRange;

    public function render(): View
    {
        return view('livewire.admin.dashboard.infrastructure');
    }
}
