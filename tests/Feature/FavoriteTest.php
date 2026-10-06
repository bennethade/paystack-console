<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPaystack();
    }

    public function test_endpoints_can_be_favorited_and_unfavorited(): void
    {
        $user = $this->developer();

        $this->actingAs($user)->postJson(route('explorer.favorites.toggle', 'bank.list'))
            ->assertOk()->assertJson(['favorite' => true, 'key' => 'bank.list']);
        $this->assertTrue($user->favoriteEndpoints()->where('key', 'bank.list')->exists());

        $this->actingAs($user)->postJson(route('explorer.favorites.toggle', 'bank.list'))
            ->assertJson(['favorite' => false]);
        $this->assertFalse($user->favoriteEndpoints()->exists());
    }

    public function test_favorites_appear_in_the_sidebar_for_that_user_only(): void
    {
        $user = $this->developer();
        $other = $this->developer();
        $user->favoriteEndpoints()->attach(Endpoint::where('key', 'transaction.verify')->value('id'));

        // The history page has no favourites card of its own, so this is the sidebar section.
        $this->actingAs($user)->get('/api-explorer/history')->assertSee('★ Favorites');
        $this->actingAs($other)->get('/api-explorer/history')->assertDontSee('★ Favorites');
    }

    public function test_favorites_are_unique_per_user(): void
    {
        $user = $this->developer();
        $id = Endpoint::where('key', 'transaction.verify')->value('id');

        $user->favoriteEndpoints()->syncWithoutDetaching([$id]);
        $user->favoriteEndpoints()->syncWithoutDetaching([$id]);

        $this->assertSame(1, $user->favoriteEndpoints()->count());
    }
}
