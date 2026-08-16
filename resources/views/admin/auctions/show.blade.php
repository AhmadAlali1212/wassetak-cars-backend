<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Auction Details') }}: {{ $auction->car->make ?? '' }} {{ $auction->car->model ?? '' }} (ID: {{ $auction->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <a href="{{ route('admin.auctions.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; {{ __('Back to Auctions List') }}</a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Auction ID') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $auction->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Car') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($auction->car)
                                    {{ $auction->car->make }} {{ $auction->car->model }} ({{ $auction->car->year }})
                                @else
                                    {{ __('N/A') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Seller') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($auction->user)
                                    <a href="{{ route('admin.users.show', $auction->user) }}" class="text-blue-600 hover:underline">{{ $auction->user->name }}</a> ({{ $auction->user->email }})
                                @else
                                    {{ __('N/A') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Start Price') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ number_format($auction->starting_price, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Current Price') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ number_format($auction->current_price, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Status') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                <span class="px-2 inline-flex text-sm leading-5 font-semibold rounded-full
                                    @if($auction->status == 'active') bg-green-100 text-green-800
                                    @elseif($auction->status == 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($auction->status == 'closed') bg-blue-100 text-blue-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($auction->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Start Time') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $auction->start_time->format('Y-m-d H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('End Time') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $auction->end_time->format('Y-m-d H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Winner') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($auction->winner)
                                    <a href="{{ route('admin.users.show', $auction->winner) }}" class="text-blue-600 hover:underline">{{ $auction->winner->name }}</a>
                                @else
                                    {{ __('N/A') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Created At') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $auction->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Updated At') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $auction->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h4 class="text-lg font-medium text-gray-900 mb-4">{{ __('Bids for this Auction') }}</h4>
                        @if($auction->bids->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Bidder') }}</th>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Bid Amount') }}</th>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Bid Time') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($auction->bids->sortByDesc('amount') as $bid)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($bid->user)
                                                        <a href="{{ route('admin.users.show', $bid->user) }}" class="text-blue-600 hover:underline">{{ $bid->user->name }}</a>
                                                    @else
                                                        {{ __('N/A') }}
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($bid->amount, 2) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">{{ $bid->created_at->format('Y-m-d H:i:s') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-gray-600">{{ __('No bids placed for this auction yet.') }}</p>
                        @endif
                    </div>

                    <div class="mt-6 flex">
                        <a href="{{ route('admin.auctions.edit', $auction) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Edit Auction') }}
                        </a>
                        <form action="{{ route('admin.auctions.destroy', $auction) }}" method="POST" class="inline ms-3" onsubmit="return confirm('{{ __('Are you sure you want to delete this auction? This action cannot be undone and will also delete associated bids.') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Delete Auction') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
