<?php

namespace Tests\Unit;

use App\Models\Ad;
use App\Models\Car;
use App\Models\User;
use App\Services\VehicleFraudDetectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VehicleFraudDetectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        DB::purge();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('make');
            $table->string('model');
            $table->integer('year');
            $table->string('condition');
            $table->text('description')->nullable();
            $table->string('vehicle_identifier');
            $table->timestamps();
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('cars');
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('price', 10, 2);
            $table->string('status')->default('active');
            $table->string('approval_status')->default('approved');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('ads');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_it_marks_strong_fraud_signal_when_same_vehicle_is_listed_multiple_times_with_conflicting_prices_and_descriptions(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('secret'),
        ]);

        $sharedIdentifier = 'VIN1234567890';
        $cars = collect([
            [
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2020,
                'condition' => 'used',
                'description' => 'سيارة ممتازة بحالة جيدة',
                'vehicle_identifier' => $sharedIdentifier,
                'user_id' => $user->id,
            ],
            [
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2020,
                'condition' => 'used',
                'description' => 'سيارة جديدة تماماً مع ضمان',
                'vehicle_identifier' => $sharedIdentifier,
                'user_id' => $user->id,
            ],
            [
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2020,
                'condition' => 'used',
                'description' => 'مصدومة وغير صالحة للتشغيل',
                'vehicle_identifier' => $sharedIdentifier,
                'user_id' => $user->id,
            ],
        ])->map(function (array $attributes) {
            $car = Car::create($attributes);
            Ad::create([
                'car_id' => $car->id,
                'user_id' => $car->user_id,
                'price' => 1000000 + $car->id * 100000,
                'status' => 'active',
                'approval_status' => 'approved',
            ]);

            return $car;
        });

        $service = new VehicleFraudDetectionService();
        $analysis = $service->analyze($cars->first());

        $this->assertTrue($analysis['has_duplicates']);
        $this->assertSame(3, $analysis['occurrences']);
        $this->assertTrue($analysis['price_conflict']);
        $this->assertTrue($analysis['description_conflict']);
        $this->assertTrue($analysis['is_strong_fraud_signal']);
    }
}
