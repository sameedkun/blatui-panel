<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function authenticatedUser(array $attributes = []): array
    {
        $user = User::factory()->app()->create($attributes);
        $token = $user->createToken('device');

        app(DeviceService::class)->register($user, new DeviceData(fingerprint: 'device-a'), $token->accessToken, '127.0.0.1');

        return [$user, $token->plainTextToken];
    }

    public function test_guest_can_submit_feedback_with_an_email(): void
    {
        $this->postJson('/api/v1/feedback', [
            'message' => 'Love the app, one suggestion...',
            'email' => 'guest@example.com',
        ])->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'user_id' => null,
            'email' => 'guest@example.com',
            'message' => 'Love the app, one suggestion...',
            'type' => 'general',
        ]);
    }

    public function test_guest_submission_without_an_email_is_rejected(): void
    {
        $this->postJson('/api/v1/feedback', ['message' => 'No way to reach me back.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_does_not_need_to_supply_an_email(): void
    {
        [$user, $token] = $this->authenticatedUser(['email' => 'jane@example.com']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/feedback', ['message' => 'Feature request incoming.'])
            ->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'user_id' => $user->id,
            'email' => 'jane@example.com',
            'message' => 'Feature request incoming.',
        ]);
    }

    public function test_authenticated_users_own_email_wins_over_a_client_supplied_one(): void
    {
        [$user, $token] = $this->authenticatedUser(['email' => 'real@example.com']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/feedback', [
                'message' => 'Trying to spoof my email.',
                'email' => 'spoofed@example.com',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('feedback', [
            'user_id' => $user->id,
            'email' => 'real@example.com',
        ]);
        $this->assertDatabaseMissing('feedback', ['email' => 'spoofed@example.com']);
    }

    public function test_type_can_be_set_and_is_validated(): void
    {
        $this->postJson('/api/v1/feedback', [
            'message' => 'Something is broken.',
            'email' => 'guest@example.com',
            'type' => 'bug',
        ])->assertCreated();

        $this->assertDatabaseHas('feedback', ['type' => 'bug']);

        $this->postJson('/api/v1/feedback', [
            'message' => 'Bad type.',
            'email' => 'guest@example.com',
            'type' => 'not-a-real-type',
        ])->assertStatus(422)->assertJsonValidationErrors('type');
    }
}
