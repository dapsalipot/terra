<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class WallFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function host(): User
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['bio' => 'Beginner-friendly climbs']);

        return $host;
    }

    public function test_a_joiner_can_follow_a_host(): void
    {
        $host = $this->host();
        $joiner = User::factory()->create(['role' => 'joiner']);

        $this->browse(function (Browser $browser) use ($host, $joiner) {
            $browser->loginAs($joiner)
                ->visit('/@'.$host->handle)
                ->assertSee('Follow')
                ->click('@follow-toggle')
                ->waitForText('Following')
                ->assertSee('1'); // follower count
        });

        $this->assertTrue($joiner->fresh()->isFollowing($host));
    }

    public function test_a_host_can_post_to_their_own_wall(): void
    {
        $host = $this->host();

        $this->browse(function (Browser $browser) use ($host) {
            $browser->loginAs($host)
                ->visit('/@'.$host->handle)
                // the composer lives on the Wall tab, so open it the way a host would
                ->click('@tab-wall')
                ->waitFor('@post-body')
                ->type('@post-body', 'Opening 12 spots for Fuji, Jul 27. Sunrise start!')
                ->click('@post-publish')
                ->waitForText('Opening 12 spots for Fuji')
                ->assertSeeIn('@wall', 'Opening 12 spots for Fuji');
        });
    }
}
