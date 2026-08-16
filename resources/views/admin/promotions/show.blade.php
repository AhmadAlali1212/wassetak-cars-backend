<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            تفاصيل العرض الترويجي: {{ $promotion->title }} رقم {{ $promotion->id }}
        </h2>
    </x-slot>

    <div class="py-12" dir="rtl">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="mb-4">
                        <a href="{{ route('admin.promotions.index') }}"
                            class="text-indigo-600 hover:text-indigo-900">
                            رجوع إلى قائمة العروض
                        </a>
                    </div>

                    @php
                    $typeLabels = [
                    'discount' => 'خصم',
                    'cashback' => 'استرداد نقدي',
                    'bundle_offer' => 'عرض باقة',

                    ];

                    $statusLabels = [
                    'pending' => 'بانتظار المراجعة',
                    'active' => 'فعال',
                    'rejected' => 'مرفوض',
                    'expired' => 'منتهي',
                    ];
                    @endphp

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <p class="text-sm text-gray-600">رقم العرض:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $promotion->id }}</p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">عنوان العرض:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $promotion->title }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <p class="text-sm text-gray-600">الوصف:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $promotion->description ?? 'غير محدد' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">نوع العرض:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $typeLabels[$promotion->type] ?? $promotion->type ?? 'غير محدد' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">قيمة العرض:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($promotion->value !== null)
                                {{ number_format($promotion->value, 2) }}
                                @else
                                غير محدد
                                @endif
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">الكمية المتاحة:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $promotion->quantity ?? 'غير محدود' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">الحالة:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($promotion->status === 'active')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    فعال
                                </span>
                                @elseif($promotion->status === 'paused')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    متوقف مؤقتاً
                                </span>
                                @elseif($promotion->status === 'expired')
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    منتهي
                                </span>
                                @else
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                    {{ $promotion->status ?? 'غير محدد' }}
                                </span>
                                @endif
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">تاريخ البداية:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $promotion->start_date ? \Carbon\Carbon::parse($promotion->start_date)->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">تاريخ النهاية:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $promotion->end_date ? \Carbon\Carbon::parse($promotion->end_date)->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">تاريخ الإنشاء:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $promotion->created_at ? $promotion->created_at->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-sm text-gray-600">آخر تعديل:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ $promotion->updated_at ? $promotion->updated_at->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex">
                        <a href="{{ route('admin.promotions.edit', $promotion) }}"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            تعديل العرض
                        </a>

                        <form action="{{ route('admin.promotions.destroy', $promotion) }}"
                            method="POST"
                            class="inline me-3"
                            onsubmit="return confirm('هل أنت متأكد من حذف هذا العرض؟ لا يمكن التراجع عن هذا الإجراء.');">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                                حذف العرض
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>