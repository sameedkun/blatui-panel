<?php

namespace Tests\Unit;

use App\Support\ActivityPresenter;
use PHPUnit\Framework\TestCase;

class ActivityPresenterTest extends TestCase
{
    public function test_technical_properties_are_pushed_to_the_end(): void
    {
        $properties = collect([
            'user_agent' => 'Mozilla/5.0',
            'reason' => 'spam',
            'ip' => '203.0.113.5',
            'count' => 3,
        ]);

        $this->assertSame(
            ['reason', 'count', 'ip', 'user_agent'],
            ActivityPresenter::orderProperties($properties)->keys()->all(),
        );
    }

    public function test_ip_always_sorts_before_user_agent_regardless_of_source_order(): void
    {
        $properties = collect(['user_agent' => 'Mozilla/5.0', 'ip' => '203.0.113.5']);

        $this->assertSame(
            ['ip', 'user_agent'],
            ActivityPresenter::orderProperties($properties)->keys()->all(),
        );
    }

    public function test_non_technical_properties_keep_their_original_order(): void
    {
        $properties = collect(['count' => 3, 'reason' => 'spam', 'bulk' => true]);

        $this->assertSame(
            ['count', 'reason', 'bulk'],
            ActivityPresenter::orderProperties($properties)->keys()->all(),
        );
    }

    public function test_a_properties_list_with_no_technical_fields_is_untouched(): void
    {
        $properties = collect(['reason' => 'spam', 'initiated_by' => 'admin']);

        $this->assertSame(
            ['reason', 'initiated_by'],
            ActivityPresenter::orderProperties($properties)->keys()->all(),
        );
    }
}
