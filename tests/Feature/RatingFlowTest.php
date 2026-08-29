<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Auction;
use App\Models\Car;
use App\Models\RentalAd;
use App\Models\RentalBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * يغطي هذا الملف بالكامل التقييمات "الصارمة": لا تقييم بلا معاملة فعلية
 * مكتملة، ولا تكرار، ولا تقييم لطرف لم يشارك فعلياً بالمعاملة.
 */
class RatingFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestUsers;

    public function test_cannot_rate_without_a_transaction_reference(): void
    {
        $rater = $this->individualUser();
        $target = $this->individualUser();

        $this->actingAs($rater, 'sanctum')
            ->postJson("/api/users/{$target->id}/ratings", [
                'rating' => 5,
            ])
            ->assertStatus(422);
    }

    public function test_cannot_rate_an_ad_that_is_not_sold(): void
    {
        $seller = $this->individualUser();
        $buyer = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $ad = Ad::factory()->for($car)->for($seller)->create(['status' => 'active']);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/users/{$seller->id}/ratings", [
                'rating' => 5,
                'type' => 'ad',
                'transaction_id' => $ad->id,
            ])
            ->assertStatus(422);
    }

    public function test_can_rate_a_sold_ad_between_its_real_parties(): void
    {
        $seller = $this->individualUser();
        $buyer = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $ad = Ad::factory()->for($car)->for($seller)->sold($buyer)->create();

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/users/{$seller->id}/ratings", [
                'rating' => 5,
                'comment' => 'تجربة ممتازة',
                'type' => 'ad',
                'transaction_id' => $ad->id,
            ])
            ->assertStatus(201);
    }

    public function test_cannot_rate_the_same_ad_transaction_twice(): void
    {
        $seller = $this->individualUser();
        $buyer = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $ad = Ad::factory()->for($car)->for($seller)->sold($buyer)->create();

        $this->actingAs($buyer, 'sanctum');

        $this->postJson("/api/users/{$seller->id}/ratings", [
            'rating' => 5,
            'type' => 'ad',
            'transaction_id' => $ad->id,
        ])->assertStatus(201);

        $this->postJson("/api/users/{$seller->id}/ratings", [
            'rating' => 4,
            'type' => 'ad',
            'transaction_id' => $ad->id,
        ])->assertStatus(422);
    }

    public function test_cannot_rate_a_third_party_using_someone_elses_sold_ad(): void
    {
        $seller = $this->individualUser();
        $buyer = $this->individualUser();
        $unrelatedUser = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $ad = Ad::factory()->for($car)->for($seller)->sold($buyer)->create();

        // buyer يحاول يقيّم شخص ثالث ما إله علاقة بهذه الصفقة، مستخدماً
        // transaction_id حقيقي — يجب أن يُرفض لأن unrelatedUser ليس أحد طرفي الصفقة.
        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/users/{$unrelatedUser->id}/ratings", [
                'rating' => 1,
                'type' => 'ad',
                'transaction_id' => $ad->id,
            ])
            ->assertStatus(422);
    }

    public function test_cannot_rate_an_auction_before_settlement_is_completed(): void
    {
        $seller = $this->individualUser();
        $winner = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()->for($car)->for($seller)->create([
            'status' => 'closed',
            'winner_id' => $winner->id,
            'final_price_status' => 'accepted', // قُبل السعر لكن الصفقة لم تُستكمل بعد
        ]);

        $this->actingAs($winner, 'sanctum')
            ->postJson("/api/users/{$seller->id}/ratings", [
                'rating' => 5,
                'type' => 'auction',
                'transaction_id' => $auction->id,
            ])
            ->assertStatus(422);
    }

    public function test_can_rate_a_completed_auction(): void
    {
        $seller = $this->individualUser();
        $winner = $this->individualUser();

        $car = Car::factory()->for($seller)->create();
        $auction = Auction::factory()->for($car)->for($seller)->completedWithWinner($winner)->create();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/users/{$winner->id}/ratings", [
                'rating' => 4,
                'type' => 'auction',
                'transaction_id' => $auction->id,
            ])
            ->assertStatus(201);
    }

    public function test_cannot_rate_a_rental_booking_before_return_confirmed(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();
        $booking = RentalBooking::factory()->for($rentalAd)->for($renter)->create([
            'status' => 'confirmed',
            'delivered_at' => now(),
            'returned_at' => null,
        ]);

        $this->actingAs($renter, 'sanctum')
            ->postJson("/api/users/{$owner->id}/ratings", [
                'rating' => 5,
                'type' => 'rental_booking',
                'transaction_id' => $booking->id,
            ])
            ->assertStatus(422);
    }

    public function test_can_rate_a_completed_rental_booking(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();
        $booking = RentalBooking::factory()->for($rentalAd)->for($renter)->completed()->create();

        $this->actingAs($renter, 'sanctum')
            ->postJson("/api/users/{$owner->id}/ratings", [
                'rating' => 5,
                'type' => 'rental_booking',
                'transaction_id' => $booking->id,
            ])
            ->assertStatus(201);
    }

    public function test_ratable_endpoint_lists_only_my_completed_unrated_transactions(): void
    {
        $seller = $this->individualUser();
        $buyer = $this->individualUser();
        $unrelatedUser = $this->individualUser();

        $car1 = Car::factory()->for($seller)->create();
        $mySoldAd = Ad::factory()->for($car1)->for($seller)->sold($buyer)->create();

        $car2 = Car::factory()->for($unrelatedUser)->create();
        Ad::factory()->for($car2)->for($unrelatedUser)->sold()->create();

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/ratings/ratable')
            ->assertStatus(200);

        $ids = collect($response->json('ratable'))
            ->where('type', 'ad')
            ->pluck('transaction_id');

        $this->assertTrue($ids->contains($mySoldAd->id));
        $this->assertEquals(1, $ids->count());
    }
}
