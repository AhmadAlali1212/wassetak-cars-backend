<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تعديل الإعلان') }}: {{ $ad->car->make ?? '' }} {{ $ad->car->model ?? '' }} (ID: {{ $ad->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.ads.update', $ad) }}">
                        @csrf
                        @method('PUT')

                        <!-- معلومات السيارة (للعرض فقط) -->
                        <div class="mb-6 border-b pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('معلومات السيارة') }}</h3>
                            <p class="text-gray-700"><strong>{{ __('الشركة المصنعة') }}:</strong> {{ $ad->car->make ?? 'غير متوفر' }}</p>
                            <p class="text-gray-700"><strong>{{ __('الموديل') }}:</strong> {{ $ad->car->model ?? 'غير متوفر' }}</p>
                            <p class="text-gray-700"><strong>{{ __('السنة') }}:</strong> {{ $ad->car->year ?? 'غير متوفر' }}</p>
                            <p class="text-gray-700"><strong>{{ __('البائع') }}:</strong> {{ $ad->user->name ?? 'غير متوفر' }} ({{ $ad->user->email ?? 'غير متوفر' }})</p>
                        </div>

                        <!-- السعر -->
                        <div>
                            <x-input-label for="price" :value="__('السعر')" />
                            <x-text-input id="price" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="price" :value="old('price', $ad->price)" required />
                            <x-input-error :messages="$errors->get('price')" class="mt-2" />
                        </div>

                        <!-- الحالة -->
                        <div class="mt-4">
                            <x-input-label for="status" :value="__('الحالة')" />
                            <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                @foreach($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" {{ old('status', $ad->status) == $statusOption ? 'selected' : '' }}>
                                        {{ [
                                            'draft' => 'مسودة',
                                            'pending' => 'قيد المراجعة',
                                            'active' => 'نشط',
                                            'expired' => 'منتهي',
                                            'sold' => 'مباع',
                                            'rejected' => 'مرفوض',
                                        ][$statusOption] ?? ucfirst($statusOption) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="rejection_reason" :value="__('سبب الرفض')" />
                            <textarea
                                id="rejection_reason"
                                name="rejection_reason"
                                rows="3"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full"
                                placeholder="إلزامي فقط عند اختيار الحالة rejected"
                            >{{ old('rejection_reason', $ad->rejection_reason) }}</textarea>
                            <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                        </div>

                        <!-- تاريخ النشر -->
                        <div class="mt-4">
                            <x-input-label for="published_at" :value="__('تاريخ النشر')" />
                            <x-text-input id="published_at" class="block mt-1 w-full" type="datetime-local" name="published_at" :value="old('published_at', $ad->published_at ? $ad->published_at->format('Y-m-d\TH:i') : '')" />
                            <x-input-error :messages="$errors->get('published_at')" class="mt-2" />
                        </div>

                        <!-- تاريخ الانتهاء -->
                        <div class="mt-4">
                            <x-input-label for="expires_at" :value="__('تاريخ الانتهاء')" />
                            <x-text-input id="expires_at" class="block mt-1 w-full" type="datetime-local" name="expires_at" :value="old('expires_at', $ad->expires_at ? $ad->expires_at->format('Y-m-d\TH:i') : '')" />
                            <x-input-error :messages="$errors->get('expires_at')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('تحديث الإعلان') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
