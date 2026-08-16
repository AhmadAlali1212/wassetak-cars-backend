<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Transaction') }}: (ID: {{ $transaction->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.transactions.update', $transaction) }}">
                        @csrf
                        @method('PUT')

                        <!-- User Information (Display Only) -->
                        <div class="mb-6 border-b pb-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('User Information') }}</h3>
                            <p class="text-gray-700"><strong>{{ __('Name') }}:</strong> {{ $transaction->user->name ?? 'N/A' }}</p>
                            <p class="text-gray-700"><strong>{{ __('Email') }}:</strong> {{ $transaction->user->email ?? 'N/A' }}</p>
                        </div>

                        <!-- User ID -->
                        <div>
                            <x-input-label for="user_id" :value="__('User ID')" />
                            <x-text-input id="user_id" class="block mt-1 w-full" type="number" name="user_id" :value="old('user_id', $transaction->user_id)" required />
                            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                        </div>

                        <!-- Amount -->
                        <div class="mt-4">
                            <x-input-label for="amount" :value="__('Amount')" />
                            <x-text-input id="amount" class="block mt-1 w-full" type="number" step="0.01" name="amount" :value="old('amount', $transaction->amount)" required />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <!-- Type -->
                        <div class="mt-4">
                            <x-input-label for="type" :value="__('Type')" />
                            <select id="type" name="type" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                <option value="deposit" {{ old('type', $transaction->type) == 'deposit' ? 'selected' : '' }}>{{ __('Deposit') }}</option>
                                <option value="withdrawal" {{ old('type', $transaction->type) == 'withdrawal' ? 'selected' : '' }}>{{ __('Withdrawal') }}</option>
                                <option value="payment" {{ old('type', $transaction->type) == 'payment' ? 'selected' : '' }}>{{ __('Payment') }}</option>
                                <option value="refund" {{ old('type', $transaction->type) == 'refund' ? 'selected' : '' }}>{{ __('Refund') }}</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <!-- Status -->
                        <div class="mt-4">
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                @foreach($statuses as $statusOption)
                                    <option value="{{ $statusOption }}" {{ old('status', $transaction->status) == $statusOption ? 'selected' : '' }}>
                                        {{ ucfirst($statusOption) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>

                        <!-- Payment Method -->
                        <div class="mt-4">
                            <x-input-label for="payment_method" :value="__('Payment Method')" />
                            <x-text-input id="payment_method" class="block mt-1 w-full" type="text" name="payment_method" :value="old('payment_method', $transaction->payment_method)" />
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        </div>

                        <!-- Transaction Date -->
                        <div class="mt-4">
                            <x-input-label for="transaction_date" :value="__('Transaction Date')" />
                            <x-text-input id="transaction_date" class="block mt-1 w-full" type="datetime-local" name="transaction_date" :value="old('transaction_date', $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d\TH:i') : '')" required />
                            <x-input-error :messages="$errors->get('transaction_date')" class="mt-2" />
                        </div>

                        <!-- Description -->
                        <div class="mt-4">
                            <x-input-label for="description" :value="__('Description')" />
                            <textarea id="description" name="description" rows="3" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('description', $transaction->description) }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Update Transaction') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
