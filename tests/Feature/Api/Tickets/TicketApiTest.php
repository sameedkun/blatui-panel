<?php

namespace Tests\Feature\Api\Tickets;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\Device\DeviceService;
use App\Support\DeviceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TicketApiTest extends TestCase
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

    public function test_categories_only_lists_active_ones(): void
    {
        [, $token] = $this->authenticatedUser();
        TicketCategory::factory()->create(['name' => 'Billing']);
        TicketCategory::factory()->inactive()->create(['name' => 'Retired']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/tickets/categories')
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Billing'));
        $this->assertFalse($names->contains('Retired'));
    }

    public function test_index_lists_only_the_callers_own_tickets(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $other = User::factory()->app()->create();

        $mine = Ticket::factory()->for($user)->create(['subject' => 'Mine']);
        Ticket::factory()->for($other)->create(['subject' => 'Not mine']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($mine->id, $response->json('data.0.id'));
    }

    public function test_store_creates_a_ticket_and_ignores_a_client_supplied_priority(): void
    {
        // Auto-assignment's eligibility check (AssignmentService::scopeToEligible())
        // resolves these permissions by name even when the category has zero
        // agents attached, so they must exist in the DB regardless.
        Permission::firstOrCreate(['name' => 'tickets.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tickets.manage', 'guard_name' => 'web']);

        [$user, $token] = $this->authenticatedUser();
        $category = TicketCategory::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/tickets', [
                'subject' => 'Cannot log in',
                'message' => 'I get an error every time.',
                'category_id' => $category->id,
                'priority' => 'urgent',
            ])
            ->assertCreated();

        $response->assertJsonPath('data.subject', 'Cannot log in')
            ->assertJsonPath('data.priority', 'medium')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonCount(1, 'data.messages');

        $this->assertDatabaseHas('tickets', [
            'user_id' => $user->id,
            'subject' => 'Cannot log in',
            'priority' => 'medium',
            'category_id' => $category->id,
        ]);
    }

    public function test_store_rejects_an_inactive_category(): void
    {
        [, $token] = $this->authenticatedUser();
        $inactive = TicketCategory::factory()->inactive()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/tickets', [
                'subject' => 'Subject',
                'message' => 'Message',
                'category_id' => $inactive->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_store_accepts_attachments(): void
    {
        [, $token] = $this->authenticatedUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/tickets', [
                'subject' => 'Broken screenshot attached',
                'message' => 'See attached.',
                'attachments' => [UploadedFile::fake()->image('screenshot.png')],
            ])
            ->assertCreated();

        $this->assertCount(1, $response->json('data.messages.0.attachments'));
    }

    public function test_show_scopes_to_the_callers_own_ticket(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $other = User::factory()->app()->create();
        $foreignTicket = Ticket::factory()->for($other)->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/tickets/'.$foreignTicket->id)
            ->assertStatus(404);

        $mine = Ticket::factory()->for($user)->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/tickets/'.$mine->id)
            ->assertOk()
            ->assertJsonPath('data.id', $mine->id);
    }

    public function test_reply_adds_a_message_and_reopens_a_pending_ticket(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $ticket = Ticket::factory()->for($user)->create(['status' => 'pending']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/tickets/{$ticket->id}/reply", ['message' => 'Still broken, any update?'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status->value);
        $this->assertNotNull($ticket->last_user_response_at);
        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'author_type' => 'user',
            'message' => 'Still broken, any update?',
        ]);
    }

    public function test_reply_is_rejected_on_a_closed_ticket(): void
    {
        [$user, $token] = $this->authenticatedUser();
        $ticket = Ticket::factory()->for($user)->closed()->create();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/tickets/{$ticket->id}/reply", ['message' => 'Reopen please?'])
            ->assertStatus(409)
            ->assertJsonPath('errors.code', 'TICKET_CLOSED');
    }

    public function test_reply_scopes_to_the_callers_own_ticket(): void
    {
        [, $token] = $this->authenticatedUser();
        $other = User::factory()->app()->create();
        $foreignTicket = Ticket::factory()->for($other)->create(['status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/tickets/{$foreignTicket->id}/reply", ['message' => 'Hello?'])
            ->assertStatus(404);
    }
}
