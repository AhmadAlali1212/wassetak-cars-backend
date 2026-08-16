<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('إدارة إعلانات البيع') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('قائمة إعلانات البيع') }}</h3>
                    </div>

                    @if(session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('معرف الإعلان') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('السيارة') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('البائع') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('السعر') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('الحالة') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('تاريخ النشر') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('الإجراءات') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($ads as $ad)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $ad->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($ad->car)
                                                {{ $ad->car->make }} {{ $ad->car->model }} ({{ $ad->car->year }})
                                            @else
                                                {{ __('غير متوفر') }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($ad->user)
                                                {{ $ad->user->name }}
                                            @else
                                                {{ __('غير متوفر') }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ number_format($ad->price, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @if($ad->status == 'active') bg-green-100 text-green-800
                                                @elseif($ad->status == 'pending') bg-yellow-100 text-yellow-800
                                                @elseif($ad->status == 'sold') bg-blue-100 text-blue-800
                                                @elseif($ad->status == 'expired') bg-gray-100 text-gray-800
                                                @else bg-red-100 text-red-800 @endif">
                                                {{ ['draft' => 'مسودة', 'pending' => 'قيد المراجعة', 'active' => 'نشط', 'expired' => 'منتهي', 'sold' => 'مباع', 'rejected' => 'مرفوض'][$ad->status] ?? ucfirst($ad->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $ad->published_at ? $ad->published_at->format('Y-m-d H:i') : '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('admin.ads.show', $ad) }}" class="text-indigo-600 hover:text-indigo-900 me-3">{{ __('عرض') }}</a>
                                            <a href="{{ route('admin.ads.edit', $ad) }}" class="text-green-600 hover:text-green-900 me-3">{{ __('تعديل') }}</a>
                                            <form action="{{ route('admin.ads.destroy', $ad) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('هل أنت متأكد من حذف هذا الإعلان؟') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('حذف') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $ads->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
