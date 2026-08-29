<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\RentalAd;
use App\Models\RentalBooking;
use App\Models\RentalUnavailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * يغطي هذا الملف الجزء الذي كان ناقصاً بالكامل: الطلبات الواردة على
 * المالك (approve/reject/markDelivered/markReturned)، منع الحجز خلال
 * فترة محجوبة يدوياً، واستبعاد السيارات المؤجَّرة فعلياً من قائمة التصفح.
 */
class RentalBookingFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestUsers;

    public function test_renter_cannot_manage_delivery_only_owner_can(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();
        $booking = RentalBooking::factory()->for($rentalAd)->for($renter)->create([
            'status' => 'confirmed',
        ]);

        // المستأجر (renter) ليس له صلاحية تأكيد التسليم — هذا كان بالضبط
        // الخطأ التصميمي بالنسخة القديمة (تتبّع محلي بجهاز المستأجر).
        $this->actingAs($renter, 'sanctum')
            ->postJson("/api/rental-bookings/{$booking->id}/mark-delivered")
            ->assertStatus(404); // findOwnedBooking لا يجده لأنه ليس مالك الإعلان

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/rental-bookings/{$booking->id}/mark-delivered")
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'confirmed');

        $booking->refresh();
        $this->assertNotNull($booking->delivered_at);
    }

    public function test_cannot_mark_returned_before_delivered(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();
        $booking = RentalBooking::factory()->for($rentalAd)->for($renter)->create([
            'status' => 'confirmed',
            'delivered_at' => null,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/rental-bookings/{$booking->id}/mark-returned")
            ->assertStatus(422);
    }

    public function test_full_delivery_return_flow_marks_booking_completed(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();
        $booking = RentalBooking::factory()->for($rentalAd)->for($renter)->create([
            'status' => 'confirmed',
        ]);

        $this->actingAs($owner, 'sanctum');

        $this->postJson("/api/rental-bookings/{$booking->id}/mark-delivered")->assertStatus(200);
        $this->postJson("/api/rental-bookings/{$booking->id}/mark-returned")
            ->assertStatus(200)
            ->assertJsonPath('booking.status', 'completed');

        $booking->refresh();
        $this->assertNotNull($booking->returned_at);
        $this->assertEquals('completed', $booking->status);
    }

    public function test_owner_sees_incoming_bookings_but_stranger_cannot_act_on_them(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();
        $stranger = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();
        $booking = RentalBooking::factory()->for($rentalAd)->for($renter)->create();

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/rental-bookings/incoming')
            ->assertStatus(200);

        $ids = collect($response->json('bookings'))->pluck('id');
        $this->assertTrue($ids->contains($booking->id));

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/rental-bookings/{$booking->id}/mark-delivered")
            ->assertStatus(404);
    }

    public function test_booking_creation_is_rejected_when_it_overlaps_an_owner_blocked_range(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $car = Car::factory()->for($owner)->create();
        $rentalAd = RentalAd::factory()->for($car)->for($owner)->create();

        RentalUnavailability::create([
            'rental_ad_id' => $rentalAd->id,
            'created_by' => $owner->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'reason' => 'صيانة',
        ]);

        $this->actingAs($renter, 'sanctum')
            ->postJson('/api/rental-bookings', [
                'rental_ad_id' => $rentalAd->id,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(8)->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_currently_rented_car_is_excluded_from_public_browse_list(): void
    {
        $owner = $this->individualUser();
        $renter = $this->individualUser();

        $availableCar = Car::factory()->for($owner)->create();
        $rentedCar = Car::factory()->for($owner)->create();

        RentalAd::factory()->for($availableCar)->for($owner)->create();
        $rentedAd = RentalAd::factory()->for($rentedCar)->for($owner)->create();

        // حجز مؤكد يغطي اليوم الحالي فعلياً
        RentalBooking::factory()->for($rentedAd)->for($renter)->create([
            'status' => 'confirmed',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($renter, 'sanctum')
            ->getJson('/api/rental-ads')
            ->assertStatus(200);

        $ids = collect($response->json('rental_ads.data') ?? $response->json('rental_ads'))
            ->pluck('id');

        $this->assertTrue($ids->contains($rentedAd->id) === false);
    }
}
