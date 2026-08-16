<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\RentalAd;
use Illuminate\Database\Seeder;

class RentalAdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Car::count() === 0) {
            $this->call(CarSeeder::class);
        }

        $cars = Car::skip(2)->take(6)->get();
        $conditions = [
            'يشترط وجود هوية شخصية ورخصة قيادة سارية، والتأمين على المستأجر طوال مدة الإيجار.',
            'السيارة تسلم بخزان ممتلئ وتعاد بنفس الحالة، وأي مخالفة مرورية يتحملها المستأجر.',
            'الإيجار داخل المدينة فقط، ويمكن الاتفاق على السفر خارج المحافظة مسبقًا.',
            'الدفع مقدمًا، مع تأمين مسترد عند إعادة السيارة بدون أضرار.',
            'مسموح حتى 200 كم يوميًا، وما زاد عن ذلك يحسب باتفاق منفصل.',
            'السيارة مناسبة للمشاوير العائلية والشركات، مع إمكانية الحجز الشهري.',
        ];

        foreach ($cars as $index => $car) {
            RentalAd::create([
                'car_id' => $car->id,
                'user_id' => $car->user_id,
                'daily_price' => [35, 55, 45, 120, 150, 40][$index],
                'monthly_price' => [850, 1300, 1100, 3000, 4200, 950][$index],
                'rental_conditions' => $conditions[$index],
                'location' => $car->location,
                'status' => ['active', 'pending', 'active', 'rented', 'active', 'rejected'][$index],
            ]);
        }
    }
}
