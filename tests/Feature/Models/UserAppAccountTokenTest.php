<?php

namespace Tests\Feature\Models;

use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserAppAccountTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_token_is_a_uuid_that_maps_back_to_the_same_account(): void
    {
        $user = User::factory()->app()->create();

        $token = $user->appAccountToken();

        $this->assertTrue(Str::isUuid($token));
        $this->assertTrue($user->is(User::findByAppAccountToken($token)));
        $this->assertTrue($user->is(User::findByAppAccountToken(strtoupper($token))));
    }

    public function test_a_soft_deleted_account_is_still_found(): void
    {
        $user = User::factory()->app()->create();
        $user->delete();

        $this->assertTrue($user->is(User::findByAppAccountToken($user->appAccountToken())));
    }

    public function test_missing_or_malformed_tokens_match_nobody(): void
    {
        User::factory()->app()->create();

        $this->assertNull(User::findByAppAccountToken(null));
        $this->assertNull(User::findByAppAccountToken('not-a-uuid'));
        $this->assertNull(User::findByAppAccountToken((string) Str::uuid()));
    }

    public function test_the_api_user_resource_exposes_the_token_for_storekit(): void
    {
        $user = User::factory()->app()->create();

        $data = (new UserResource($user))->toArray(Request::create('/'));

        $this->assertSame($user->appAccountToken(), $data['app_account_token']);
    }
}
