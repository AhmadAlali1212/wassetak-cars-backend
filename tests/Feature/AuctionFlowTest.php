<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Car;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * يغطي هذا الملف بالتحديد الأجزاء التي عدّلناها أو أصلحناها بالمزادات:
 * - إصلاح 404 على GET /auctions/{id} لأي مزاد غير active
 * - الإغلاق الذاتي (self-heal) للمزاد المنتهي وقته عند فتحه
 * - rate limiting على المزايدة
 */
class AuctionFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestUsers;

    public function test_show_returns_404_for_stranger_on_non_active_auction(): void
    {
        $seller = $this->individualUser();
        $winner = $this->individualUser();
        $stranger = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()
            ->for($car)
            ->for($seller)
            ->completedWithWinner($winner)
            ->create();

        // قبل الإصلاح: كان هذا يرجع 404 لأي طالب، حتى لو صاحب المزاد نفسه.
        // الآن: يجب أن يبقى محجوباً فقط عن شخص غريب تماماً عن الصفقة.
        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}")
            ->assertStatus(404);
    }

    public function test_show_allows_owner_and_winner_after_close(): void
    {
        $seller = $this->individualUser();
        $winner = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()
            ->for($car)
            ->for($seller)
            ->completedWithWinner($winner)
            ->create();

        $this->actingAs($seller, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}")
            ->assertStatus(200)
            ->assertJsonPath('auction.id', $auction->id);

        $this->actingAs($winner, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}")
            ->assertStatus(200);
    }

    public function test_show_allows_a_user_who_placed_a_bid_even_after_close(): void
    {
        $seller = $this->individualUser();
        $bidder = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()->for($car)->for($seller)->create([
            'status' => 'closed',
        ]);
        Bid::factory()->for($auction)->for($bidder)->create();

        $this->actingAs($bidder, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}")
            ->assertStatus(200);
    }

    public function test_show_self_heals_an_auction_whose_end_time_passed_but_status_still_active(): void
    {
        $seller = $this->individualUser();
        $bidder = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()
            ->for($car)
            ->for($seller)
            ->expiredButStillActiveFlag()
            ->create();
        Bid::factory()->for($auction)->for($bidder)->create([
            'amount' => 1500,
            'is_deposit_held' => true,
        ]);

        // قبل الإصلاح: هذا المزاد يبقى "active" للأبد حتى يمر عليه الـ cron
        // يدوياً، ويُرجع 404 لأي أحد رغم انتهاء وقته فعلياً.
        $response = $this->actingAs($bidder, 'sanctum')
            ->getJson("/api/auctions/{$auction->id}");

        $response->assertStatus(200);
        $this->assertNotEquals('active', $response->json('auction.status'));
    }

    public function test_placing_a_bid_is_rate_limited(): void
    {
        $seller = $this->individualUser();
        $bidder = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()->for($car)->for($seller)->create([
            'starting_price' => 1000,
            'current_price' => 1000,
        ]);

        $this->actingAs($bidder, 'sanctum');

        // throttle:10,1 — الطلب الحادي عشر خلال نفس الدقيقة يجب أن يُرفض.
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson("/api/auctions/{$auction->id}/bid", [
                'amount' => 1000 + $i,
            ]);
        }

        $this->postJson("/api/auctions/{$auction->id}/bid", [
            'amount' => 1050,
        ])->assertStatus(429);
    }

    public function test_my_auctions_endpoint_returns_auctions_regardless_of_status(): void
    {
        $seller = $this->individualUser();
        $otherSeller = $this->individualUser();

        $car1 = Car::factory()->for($seller)->create();
        $car2 = Car::factory()->for($seller)->create();
        $otherCar = Car::factory()->for($otherSeller)->create();

        $active = Auction::factory()->for($car1)->for($seller)->create(['status' => 'active']);
        $closed = Auction::factory()->for($car2)->for($seller)->create(['status' => 'closed']);
        Auction::factory()->for($otherCar)->for($otherSeller)->create(['status' => 'active']);

        $response = $this->actingAs($seller, 'sanctum')
            ->getJson('/api/my-auctions')
            ->assertStatus(200);

        $ids = collect($response->json('auctions.data') ?? $response->json('auctions'))
            ->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertTrue($ids->contains($closed->id));
    }
}
