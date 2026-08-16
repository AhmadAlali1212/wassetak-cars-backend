<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            مراجعة العرض الترويجي
        </h2>
    </x-slot>

    <div class="py-12" dir="rtl">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">
                            معلومات العرض
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">
                            يمكنك مراجعة بيانات العرض وتغيير حالته فقط.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">الشركة</p>
                                <p class="font-medium text-gray-900">
                                    {{ $promotion->user->name ?? 'غير محدد' }}
                                </p>

                                @if($promotion->user)
                                    <p class="text-sm text-gray-500 mt-1">
                                        {{ $promotion->user->email }}
                                    </p>
                                @endif
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">العنوان</p>
                                <p class="font-medium text-gray-900">
                                    {{ $promotion->title }}
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 md:col-span-2">
                                <p class="text-sm text-gray-500 mb-1">الوصف</p>
                                <p class="font-medium text-gray-900 whitespace-pre-wrap">
                                    {{ $promotion->description ?? 'لا يوجد وصف' }}
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">النوع</p>

                                @php
                                    $typeLabels = [
                                        'discount' => 'خصم',
                                        'cashback' => 'استرداد نقدي',
                                        'bundle_offer' => 'عرض باقة',
                                    ];
                                @endphp

                                <p class="font-medium text-gray-900">
                                    {{ $typeLabels[$promotion->type] ?? $promotion->type }}
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">القيمة</p>
                                <p class="font-medium text-gray-900">
                                    @if($promotion->value !== null)
                                        {{ number_format($promotion->value, 2) }}
                                    @else
                                        غير محدد
                                    @endif
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">تاريخ البداية</p>
                                <p class="font-medium text-gray-900">
                                    {{ $promotion->start_date ? $promotion->start_date->format('Y-m-d H:i') : 'غير محدد' }}
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">تاريخ النهاية</p>
                                <p class="font-medium text-gray-900">
                                    {{ $promotion->end_date ? $promotion->end_date->format('Y-m-d H:i') : 'غير محدد' }}
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">الكمية</p>
                                <p class="font-medium text-gray-900">
                                    {{ $promotion->quantity ?? 'غير محدد' }}
                                </p>
                            </div>

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <p class="text-sm text-gray-500 mb-1">الحالة الحالية</p>

                                @if ($promotion->status === 'active')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        فعال
                                    </span>
                                @elseif ($promotion->status === 'pending')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        بانتظار المراجعة
                                    </span>
                                @elseif ($promotion->status === 'rejected')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        مرفوض
                                    </span>
                                @elseif ($promotion->status === 'expired')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        منتهي
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                        {{ $promotion->status }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-6">
                            <div class="mb-4">
                                <x-input-label for="status" :value="__('تحديث حالة العرض')" />

                                @php
                                    $statusLabels = [
                                        'pending' => 'بانتظار المراجعة',
                                        'active' => 'فعال',
                                        'rejected' => 'مرفوض',
                                        'expired' => 'منتهي',
                                    ];
                                @endphp

                                <select id="status"
                                        name="status"
                                        class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                        required>
                                    @foreach($statuses as $statusOption)
                                        <option value="{{ $statusOption }}" {{ old('status', $promotion->status) === $statusOption ? 'selected' : '' }}>
                                            {{ $statusLabels[$statusOption] ?? $statusOption }}
                                        </option>
                                    @endforeach
                                </select>

                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>

                            <div class="mb-4">
                                <x-input-label for="rejection_reason" :value="__('سبب الرفض')" />
                                <textarea
                                    id="rejection_reason"
                                    name="rejection_reason"
                                    rows="3"
                                    class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                    placeholder="إلزامي فقط عند اختيار الحالة مرفوض"
                                >{{ old('rejection_reason', $promotion->rejection_reason) }}</textarea>
                                <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                            </div>

                            <div class="flex items-center justify-end gap-2 mt-6">
                                <a href="{{ route('admin.promotions.index') }}"
                                   class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600">
                                    رجوع
                                </a>

                                <x-primary-button>
                                    تحديث حالة العرض
                                </x-primary-button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
