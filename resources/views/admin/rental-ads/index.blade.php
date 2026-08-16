<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manage Rental Ads') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('List of Rental Ads') }}</h3>
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
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Ad ID') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Car') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Renter') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Daily Price') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Monthly Price') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Location') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($rentalAds as $rentalAd)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rentalAd->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($rentalAd->car)
                                                {{ $rentalAd->car->make }} {{ $rentalAd->car->model }} ({{ $rentalAd->car->year }})
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($rentalAd->user)
                                                {{ $rentalAd->user->name }}
                                            @else
                                                {{ __('N/A') }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ number_format($rentalAd->daily_price, 2) ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ number_format($rentalAd->monthly_price, 2) ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $rentalAd->location }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @if($rentalAd->status == 'active') bg-green-100 text-green-800
                                                @elseif($rentalAd->status == 'pending') bg-yellow-100 text-yellow-800
                                                @elseif($rentalAd->status == 'rented') bg-blue-100 text-blue-800
                                                @else bg-red-100 text-red-800 @endif">
                                                {{ ucfirst($rentalAd->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('admin.rental-ads.show', $rentalAd) }}" class="text-indigo-600 hover:text-indigo-900 me-3">{{ __('View') }}</a>
                                            <a href="{{ route('admin.rental-ads.edit', $rentalAd) }}" class="text-green-600 hover:text-green-900 me-3">{{ __('Edit') }}</a>
                                            <form action="{{ route('admin.rental-ads.destroy', $rentalAd) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this rental ad?') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900">{{ __('Delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $rentalAds->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
