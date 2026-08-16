<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تفاصيل المعاملة') }}: (ID: {{ $transaction->id }})
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <a href="{{ route('admin.transactions.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; {{ __('العودة إلى قائمة المعاملات') }}</a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">{{ __('معرف المعاملة') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $transaction->id }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('المستخدم') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($transaction->user)
                                    <a href="{{ route('admin.users.show', $transaction->user) }}" class="text-blue-600 hover:underline">{{ $transaction->user->name }}</a> ({{ $transaction->user->email }})
                                @else
                                    {{ __('غير متوفر') }}
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('المبلغ') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ number_format($transaction->amount, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('النوع') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ ucfirst($transaction->type) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('الحالة') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @if($transaction->status == 'completed') bg-green-100 text-green-800
                                    @elseif($transaction->status == 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($transaction->status == 'failed') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ ucfirst($transaction->status) }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('طريقة الدفع') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $transaction->payment_method ?? __('غير متوفر') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ المعاملة') }}:</p>
                            <p class="text-lg font-medium text-gray-900">
                                {{ optional($transaction->processed_at ?? $transaction->created_at)->format('Y-m-d H:i') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('الوصف') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $transaction->description ?? __('غير متوفر') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('المرجع') }}:</p>
                            <p class="text-lg font-medium text-gray-900 break-all">{{ $transaction->reference ?? __('غير متوفر') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ الإنشاء') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $transaction->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('تاريخ التحديث') }}:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $transaction->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
