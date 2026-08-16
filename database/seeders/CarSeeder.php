<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::count() === 0) {
            $this->call(AdminUserSeeder::class);
        }

        $userIds = User::pluck('id')->values();

        $cars = [
            ['make' => 'تويوتا', 'model' => 'كورولا', 'year' => 2021, 'condition' => 'used', 'location' => 'دمشق', 'description' => 'سيارة اقتصادية بحالة ممتازة، فحص كامل، استهلاك وقود منخفض ومناسبة للاستخدام اليومي.'],
            ['make' => 'كيا', 'model' => 'سبورتاج', 'year' => 2022, 'condition' => 'used', 'location' => 'حلب', 'description' => 'سيارة عائلية واسعة، مكيفة، فرش نظيف، صيانة دورية منتظمة.' ],
            ['make' => 'هيونداي', 'model' => 'أفانتي', 'year' => 2020, 'condition' => 'used', 'location' => 'حمص', 'description' => 'سيارة مريحة وموفرة، مناسبة للمدينة والسفر، جاهزة للتسجيل.'],
            ['make' => 'مرسيدس', 'model' => 'C200', 'year' => 2019, 'condition' => 'used', 'location' => 'اللاذقية', 'description' => 'سيارة فاخرة بنظام أمان متقدم، محرك قوي، داخلية جلد نظيفة.'],
            ['make' => 'بي إم دبليو', 'model' => 'X5', 'year' => 2021, 'condition' => 'used', 'location' => 'طرطوس', 'description' => 'دفع رباعي فاخر، مناسبة للرحلات، شاشة كبيرة وكاميرات محيطية.' ],
            ['make' => 'نيسان', 'model' => 'صني', 'year' => 2018, 'condition' => 'used', 'location' => 'حماة', 'description' => 'سيارة عملية بسعر مناسب، قطع تبديل متوفرة، حالة ميكانيكية جيدة.' ],
            ['make' => 'شيفروليه', 'model' => 'كروز', 'year' => 2017, 'condition' => 'used', 'location' => 'دمشق', 'description' => 'سيارة سيدان أنيقة، مكيفة، مناسبة للعائلة والعمل.', ],
            ['make' => 'فورد', 'model' => 'إكسبلورر', 'year' => 2020, 'condition' => 'used', 'location' => 'حلب', 'description' => 'سيارة عائلية كبيرة بسبعة مقاعد، مناسبة للسفر والطرق الطويلة.'],
            ['make' => 'هوندا', 'model' => 'سيفيك', 'year' => 2023, 'condition' => 'new', 'location' => 'دمشق', 'description' => 'سيارة شبه جديدة، عداد منخفض، تصميم رياضي وتجهيزات حديثة.'],
            ['make' => 'أودي', 'model' => 'A4', 'year' => 2020, 'condition' => 'used', 'location' => 'اللاذقية', 'description' => 'سيارة ألمانية راقية، قيادة سلسة، صيانة موثقة.'],
        ];

        foreach ($cars as $index => $car) {
            Car::create(array_merge($car, [
                'user_id' => $userIds[$index % $userIds->count()],
                'images' => [
                    'https://placehold.co/600x400?text=' . urlencode($car['make'] . ' ' . $car['model']),
                    'https://placehold.co/600x400?text=' . urlencode('سيارة ' . ($index + 1)),
                ],
            ]));
        }
    }
}
