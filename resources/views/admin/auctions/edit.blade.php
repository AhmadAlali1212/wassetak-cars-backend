<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Auction') }}: {{ $auction->car->make ?? '' }} {{ $auction->car->model ?? '' }} (ID: {{ $auction->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.auctions.update', $auction) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-6 border-b pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Car Information') }}</h3>
                            <p class="text-gray-700"><strong>{{ __('Make') }}:</strong> {{ $auction->car->make ?? __('N/A') }}</p>
                            <p class="text-gray-700"><strong>{{ __('Model') }}:</strong> {{ $auction->car->model ?? __('N/A') }}</p>
                            <p class="text-gray-700"><strong>{{ __('Year') }}:</strong> {{ $auction->car->year ?? __('N/A') }}</p>
                            <p class="text-gray-700"><strong>{{ __('Seller') }}:</strong> {{ $auction->user->name ?? __('N/A') }} ({{ $auction->user->email ?? __('N/A') }})</p>
                        </div>

                        <div>
                            <x-input-label for="starting_price" :value="__('Starting Price')" />
                            <x-text-input id="starting_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="starting_price" :value="old('starting_price', $auction->starting_price)" required />
                            <x-input-error :messages="$errors->get('starting_price')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="current_price" :value="__('Current Price')" />
                            <x-text-input id="current_price" class="block mt-1 w-full" type="number" step="0.01" min="0" name="current_price" :value="old('current_price', $auction->current_price)" />
                            <x-input-error :messages="$errors->get('current_price')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                @foreach($statuses as $statusOption)
                                <option value="{{ $statusOption }}" {{ old('status', $auction->status) == $statusOption ? 'selected' : '' }}>
                                    {{ ucfirst($statusOption) }}
                                </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="start_time" :value="__('Start Time')" />
                            <x-text-input id="start_time" class="block mt-1 w-full" type="datetime-local" name="start_time" :value="old('start_time', $auction->start_time ? $auction->start_time->format('Y-m-d\TH:i') : '')" required />
                            <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="end_time" :value="__('End Time')" />
                            <x-text-input id="end_time" class="block mt-1 w-full" type="datetime-local" name="end_time" :value="old('end_time', $auction->end_time ? $auction->end_time->format('Y-m-d\TH:i') : '')" required />
                            <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                        </div>



                        <div class="flex items-center justify-between mt-6">
                            <a href="{{ route('admin.auctions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                                {{ __('Back to Auctions List') }}
                            </a>

                            <x-primary-button>
                                {{ __('Update Auction') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>