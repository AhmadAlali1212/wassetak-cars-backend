<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            لوحة تحكم المدير
        </h2>
    </x-slot>

    <div class="py-10" dir="rtl">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-sm font-medium text-gray-500">إجمالي المستخدمين</h3>
                    <p class="mt-2 text-3xl font-bold text-indigo-600">{{ $totalUsers }}</p>
                    <p class="mt-3 text-sm text-gray-600">موثقون: {{ $verifiedUsers }} | غير موثقين: {{ $unverifiedUsers }}</p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-sm font-medium text-gray-500">إعلانات البيع</h3>
                    <p class="mt-2 text-3xl font-bold text-green-600">{{ $activeAds }}</p>
                    <p class="mt-3 text-sm text-gray-600">قيد المراجعة: {{ $pendingAds }}</p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-sm font-medium text-gray-500">المزادات والتأجير</h3>
                    <p class="mt-2 text-3xl font-bold text-purple-600">{{ $activeAuctions }}</p>
                    <p class="mt-3 text-sm text-gray-600">إعلانات التأجير النشطة: {{ $activeRentalAds }}</p>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-5">
                    <h3 class="text-sm font-medium text-gray-500">المعاملات المالية</h3>
                    <p class="mt-2 text-3xl font-bold text-red-600">{{ number_format($totalTransactions, 2) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">أحدث المستخدمين</h3>
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">إدارة المستخدمين</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($latestUsers as $user)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $user->name }}</p>
                                    <p class="text-sm text-gray-600">{{ $user->email }}</p>
                                </div>
                                <span class="text-sm text-gray-500">{{ $user->accountType->description ?? 'غير محدد' }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-600">لا يوجد مستخدمون بعد.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">أحدث السيارات</h3>
                        <a href="{{ route('admin.ads.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">عرض الإعلانات</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($latestCars as $car)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $car->make }} {{ $car->model }} - {{ $car->year }}</p>
                                    <p class="text-sm text-gray-600">{{ $car->location }} | {{ $car->condition === 'new' ? 'جديدة' : 'مستعملة' }}</p>
                                </div>
                                <span class="text-sm text-gray-500">{{ $car->user->name ?? 'غير محدد' }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-600">لا توجد سيارات بعد.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">أحدث إعلانات البيع</h3>
                        <a href="{{ route('admin.ads.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">إدارة الإعلانات</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($latestAds as $ad)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $ad->car->make ?? '' }} {{ $ad->car->model ?? '' }}</p>
                                    <p class="text-sm text-gray-600">{{ $ad->user->name ?? 'غير محدد' }} | {{ ucfirst($ad->status) }}</p>
                                </div>
                                <span class="font-semibold text-gray-900">{{ number_format($ad->price, 2) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-600">لا توجد إعلانات بيع بعد.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">أحدث إعلانات التأجير</h3>
                        <a href="{{ route('admin.rental-ads.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">إدارة التأجير</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($latestRentalAds as $rentalAd)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $rentalAd->car->make ?? '' }} {{ $rentalAd->car->model ?? '' }}</p>
                                    <p class="text-sm text-gray-600">{{ $rentalAd->location }} | {{ ucfirst($rentalAd->status) }}</p>
                                </div>
                                <span class="font-semibold text-gray-900">{{ number_format($rentalAd->daily_price ?? 0, 2) }} / يوم</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-600">لا توجد إعلانات تأجير بعد.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">أحدث المزادات</h3>
                        <a href="{{ route('admin.auctions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">إدارة المزادات</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($latestAuctions as $auction)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $auction->car->make ?? '' }} {{ $auction->car->model ?? '' }}</p>
                                    <p class="text-sm text-gray-600">{{ $auction->user->name ?? 'غير محدد' }} | {{ ucfirst($auction->status) }}</p>
                                </div>
                                <span class="font-semibold text-gray-900">{{ number_format($auction->current_price, 2) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-600">لا توجد مزادات بعد.</p>
                        @endforelse
                    </div>
                </section>

                <section class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">أحدث العروض الترويجية</h3>
                        <a href="{{ route('admin.promotions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">إدارة العروض</a>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @forelse($latestPromotions as $promotion)
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $promotion->title }}</p>
                                    <p class="text-sm text-gray-600">{{ $promotion->user->name ?? 'غير محدد' }} | {{ ucfirst($promotion->status) }}</p>
                                </div>
                                <span class="font-semibold text-gray-900">{{ $promotion->value ? number_format($promotion->value, 2) : '-' }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-600">لا توجد عروض بعد.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">أحدث المعاملات</h3>
                    <a href="{{ route('admin.transactions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">إدارة المعاملات</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المستخدم</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">النوع</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المبلغ</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الوصف</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($latestTransactions as $transaction)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $transaction->user->name ?? 'غير محدد' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $transaction->type }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $transaction->status }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap font-semibold">{{ number_format($transaction->amount, 2) }}</td>
                                    <td class="px-4 py-3">{{ $transaction->description ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-sm text-gray-600">لا توجد معاملات بعد.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
