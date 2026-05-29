<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\LinkPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LinkPageDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_a_virtual_design_and_release_the_card(): void
    {
        $user = User::factory()->create([
            'slug' => 'cliente',
        ]);
        $card = Card::create([
            'user_id' => $user->id,
            'name' => 'Tarjeta principal',
            'slug' => 'tarjeta-principal',
            'status' => CardStatus::Designing,
        ]);
        $linkPage = $this->linkPageFor($user, $card);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/link-pages/{$linkPage->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.deleted_design.id', $linkPage->id)
            ->assertJsonPath('data.deleted_design.card_id', $card->id)
            ->assertJsonPath('data.released_card.id', $card->id)
            ->assertJsonPath('data.released_card.status', CardStatus::Draft->value)
            ->assertJsonPath('data.released_card.link_page_id', null)
            ->assertJsonPath('data.released_card.public_path', null);

        $this->assertDatabaseMissing('link_pages', ['id' => $linkPage->id]);
        $this->assertSame(CardStatus::Draft, $card->refresh()->status);
        $this->assertDatabaseHas('card_status_logs', [
            'card_id' => $card->id,
            'from_status' => CardStatus::Designing->value,
            'to_status' => CardStatus::Draft->value,
        ]);
    }

    public function test_user_can_delete_a_virtual_design_from_the_card_context(): void
    {
        $user = User::factory()->create([
            'slug' => 'cliente',
        ]);
        $card = Card::create([
            'user_id' => $user->id,
            'name' => 'Tarjeta secundaria',
            'slug' => 'tarjeta-secundaria',
            'status' => CardStatus::Designing,
        ]);
        $linkPage = $this->linkPageFor($user, $card);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/cards/{$card->id}/link-page");

        $response
            ->assertOk()
            ->assertJsonPath('data.deleted_design.id', $linkPage->id)
            ->assertJsonPath('data.released_card.id', $card->id)
            ->assertJsonPath('data.released_card.status', CardStatus::Draft->value);

        $this->assertDatabaseMissing('link_pages', ['id' => $linkPage->id]);
        $this->assertNull($card->refresh()->linkPage()->first());
    }

    private function linkPageFor(User $user, Card $card): LinkPage
    {
        return LinkPage::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'slug' => "diseno-{$card->id}",
            'profile' => [
                'name' => 'Diseño virtual',
                'subtitle' => '',
                'cover_position' => 'center',
                'footer' => 'TapCloudi',
            ],
            'theme' => [
                'background' => '#0f1115',
                'card' => '#171a21',
                'text' => '#f6f7fb',
            ],
            'socials' => [],
            'links' => [],
            'banners' => [],
            'status' => 'active',
        ]);
    }
}
