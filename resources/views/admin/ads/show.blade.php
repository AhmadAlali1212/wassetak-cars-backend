<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تفاصيل الإعلان') }}: {{ $ad->car->make ?? '' }} {{ $ad->car->model ?? '' }} (ID: {{ $ad->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <a href="{{ route('admin.ads.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; {{ __('العودة لقائمة إعلانات البيع') }}</a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">{{ __('معرف الإعلان') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $ad->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('السيارة') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($ad->car)
                                    {{ $ad->car->make }} {{ $ad->car->model }} ({{ $ad->car->year }})
                                @else
                                    {{ __('غير متوفر') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('البائع') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($ad->user)
                                    <a href="{{ route('admin.users.show', $ad->user) }}" class="text-blue-600 hover:underline">{{ $ad->user->name }}</a> ({{ $ad->user->email }})
                                @else
                                    {{ __('غير متوفر') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('السعر') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ number_format($ad->price, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('الحالة') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full
                                    @if($ad->status == 'active') bg-green-100 text-green-800
                                    @elseif($ad->status == 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($ad->status == 'sold') bg-blue-100 text-blue-800
                                    @elseif($ad->status == 'expired') bg-gray-100 text-gray-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ['draft' => 'مسودة', 'pending' => 'قيد المراجعة', 'active' => 'نشط', 'expired' => 'منتهي', 'sold' => 'مباع', 'rejected' => 'مرفوض'][$ad->status] ?? ucfirst($ad->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ النشر') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $ad->published_at ? $ad->published_at->format('Y-m-d H:i') : __('غير متوفر') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ الانتهاء') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $ad->expires_at ? $ad->expires_at->format('Y-m-d H:i') : __('غير متوفر') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ الإنشاء') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $ad->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ التحديث') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $ad->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex">
                        <a href="{{ route('admin.ads.edit', $ad) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('تعديل الإعلان') }}
                        </a>
                        <form action="{{ route('admin.ads.destroy', $ad) }}" method="POST" class="inline ms-3" onsubmit="return confirm('{{ __('هل أنت متأكد من حذف هذا الإعلان؟ لا يمكن التراجع عن هذا الإجراء.') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('حذف الإعلان') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
