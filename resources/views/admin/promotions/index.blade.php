<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            إدارة العروض الترويجية
        </h2>
    </x-slot>

    <div class="py-12" dir="rtl">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        قائمة العروض الترويجية
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        رقم العرض
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        العنوان
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الشركة
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        النوع
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        القيمة
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الكمية
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        تاريخ البداية
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        تاريخ النهاية
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الحالة
                                    </th>

                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($promotions as $promotion)
                                    @php
                                        $typeLabels = [
                                            'discount' => 'خصم',
                                            'cashback' => 'استرداد نقدي',
                                            'bundle_offer' => 'عرض باقة',
                                        ];
                                    @endphp

                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $promotion->id }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $promotion->title }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($promotion->user)
                                                <div class="font-medium text-gray-900">
                                                    {{ $promotion->user->name }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $promotion->user->email }}
                                                </div>
                                            @else
                                                <span class="text-gray-400">غير محدد</span>
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $typeLabels[$promotion->type] ?? $promotion->type }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($promotion->value !== null)
                                                {{ number_format($promotion->value, 2) }}
                                            @else
                                                غير محدد
                                            @endif
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $promotion->quantity ?? 'غير محدود' }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $promotion->start_date ? \Carbon\Carbon::parse($promotion->start_date)->format('Y-m-d H:i') : 'غير محدد' }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            {{ $promotion->end_date ? \Carbon\Carbon::parse($promotion->end_date)->format('Y-m-d H:i') : 'غير محدد' }}
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
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
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('admin.promotions.show', $promotion) }}"
                                               class="text-indigo-600 hover:text-indigo-900 me-3">
                                                عرض
                                            </a>

                                            <a href="{{ route('admin.promotions.edit', $promotion) }}"
                                               class="text-green-600 hover:text-green-900 me-3">
                                                مراجعة
                                            </a>

                                            <form action="{{ route('admin.promotions.destroy', $promotion) }}"
                                                  method="POST"
                                                  class="inline"
                                                  onsubmit="return confirm('هل أنت متأكد من حذف هذا العرض؟');">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    حذف
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                            لا توجد عروض ترويجية حالياً.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $promotions->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>