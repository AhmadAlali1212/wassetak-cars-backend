<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Rental Ad') }}: {{ $rentalAd->car->make ?? '' }} {{ $rentalAd->car->model ?? '' }} (ID: {{ $rentalAd->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.rental-ads.update', $rentalAd) }}">
                        @csrf
                        @method('PUT')

                        <!-- Car Information (Display Only) -->
                        <div class="mb-6 border-b pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Car Information') }}</h3>
                            <p class="text-gray-700"><strong>{{ __('Make') }}:</strong> {{ $rentalAd->car->make ?? 'N/A' }}</p>
                            <p class="text-gray-700"><strong>{{ __('Model') }}:</strong> {{ $rentalAd->car->model ?? 'N/A' }}</p>
                            <p class="text-gray-700"><strong>{{ __('Year') }}:</strong> {{ $rentalAd->car->year ?? 'N/A' }}</p>
                            <p class="text-gray-700"><strong>{{ __('Renter') }}:</strong> {{ $rentalAd->user->name ?? 'N/A' }} ({{ $rentalAd->user->email ?? 'N/A' }})</p>
                        </div>

                        <!-- Daily Price -->
                        <div>
                            <x-input-label for="daily_price" :value="__('Daily Price')" />
                            <x-text-input id="daily_price" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="daily_price" :value="old('daily_price', $rentalAd->daily_price)" />
                            <x-input-error :messages="$errors->get('daily_price')" class="mt-2" />
                        </div>

                        <!-- Monthly Price -->
                        <div class="mt-4">
                            <x-input-label for="monthly_price" :value="__('Monthly Price')" />
                            <x-text-input id="monthly_price" class="block mt-1 w-full" type="number" step="0.01" min="0.01" name="monthly_price" :value="old('monthly_price', $rentalAd->monthly_price)" />
                            <x-input-error :messages="$errors->get('monthly_price')" class="mt-2" />
                        </div>

                        <!-- Rental Conditions -->
                        <div class="mt-4">
                            <x-input-label for="rental_conditions" :value="__('Rental Conditions')" />
                            <textarea id="rental_conditions" name="rental_conditions" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('rental_conditions', $rentalAd->rental_conditions) }}</textarea>
                            <x-input-error :messages="$errors->get('rental_conditions')" class="mt-2" />
                        </div>

                        <!-- Location -->
                        <div class="mt-4">
                            <x-input-label for="location" :value="__('Location')" />
                            <x-text-input id="location" class="block mt-1 w-full" type="text" name="location" :value="old('location', $rentalAd->location)" required />
                            <x-input-error :messages="$errors->get('location')" class="mt-2" />
                        </div>

                        <!-- Status -->
                        <div class="mt-4">
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                @foreach($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" {{ old('status', $rentalAd->status) == $statusOption ? 'selected' : '' }}>
                                        {{ ucfirst($statusOption) }}
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
                            >{{ old('rejection_reason', $rentalAd->rejection_reason) }}</textarea>
                            <x-input-error :messages="$errors->get('rejection_reason')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Update Rental Ad') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
