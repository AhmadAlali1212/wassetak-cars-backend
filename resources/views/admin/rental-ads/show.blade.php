<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rental Ad Details') }}: {{ $rentalAd->car->make ?? '' }} {{ $rentalAd->car->model ?? '' }} (ID: {{ $rentalAd->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <a href="{{ route('admin.rental-ads.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; {{ __('Back to Rental Ads List') }}</a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Ad ID') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $rentalAd->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Car') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($rentalAd->car)
                                    {{ $rentalAd->car->make }} {{ $rentalAd->car->model }} ({{ $rentalAd->car->year }})
                                @else
                                    {{ __('N/A') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Renter') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($rentalAd->user)
                                    <a href="{{ route('admin.users.show', $rentalAd->user) }}" class="text-blue-600 hover:underline">{{ $rentalAd->user->name }}</a> ({{ $rentalAd->user->email }})
                                @else
                                    {{ __('N/A') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Daily Price') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ number_format($rentalAd->daily_price, 2) ?? __('N/A') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Monthly Price') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ number_format($rentalAd->monthly_price, 2) ?? __('N/A') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Rental Conditions') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $rentalAd->rental_conditions ?? __('N/A') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Location') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $rentalAd->location }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Status') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full
                                    @if($rentalAd->status == 'active') bg-green-100 text-green-800
                                    @elseif($rentalAd->status == 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($rentalAd->status == 'rented') bg-blue-100 text-blue-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($rentalAd->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Created At') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $rentalAd->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Updated At') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $rentalAd->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex">
                        <a href="{{ route('admin.rental-ads.edit', $rentalAd) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Edit Rental Ad') }}
                        </a>
                        <form action="{{ route('admin.rental-ads.destroy', $rentalAd) }}" method="POST" class="inline ms-3" onsubmit="return confirm('{{ __('Are you sure you want to delete this rental ad? This action cannot be undone.') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Delete Rental Ad') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
