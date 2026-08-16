<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تعديل المستخدم') }}: {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" :value="__('الاسم')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="email" :value="__('البريد الإلكتروني')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $user->email)" required autocomplete="username" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="phone_number" :value="__('رقم الهاتف')" />
                            <x-text-input id="phone_number" class="block mt-1 w-full" type="text" name="phone_number" :value="old('phone_number', $user->phone_number)" autocomplete="tel" />
                            <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="account_type_id" :value="__('نوع الحساب')" />
                            <select id="account_type_id" name="account_type_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">
                                @foreach($accountTypes as $type)
                                    <option value="{{ $type->id }}" {{ old('account_type_id', $user->account_type_id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('account_type_id')" class="mt-2" />
                        </div>

                        <div class="mt-4 flex items-center">
                            <input type="checkbox" id="is_verified" name="is_verified" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_verified', $user->is_verified) ? 'checked' : '' }}>
                            <x-input-label for="is_verified" class="ms-2" :value="__('موثق')" />
                            <x-input-error :messages="$errors->get('is_verified')" class="mt-2" />
                        </div>

                        <div class="mt-4 flex items-center">
                            <input type="checkbox" id="is_subscribed" name="is_subscribed" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('is_subscribed', $user->is_subscribed) ? 'checked' : '' }}>
                            <x-input-label for="is_subscribed" class="ms-2" :value="__('مشترك')" />
                            <x-input-error :messages="$errors->get('is_subscribed')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="subscription_ends_at" :value="__('تاريخ انتهاء الاشتراك')" />
                            <x-text-input id="subscription_ends_at" class="block mt-1 w-full" type="datetime-local" name="subscription_ends_at" :value="old('subscription_ends_at', $user->subscription_ends_at ? $user->subscription_ends_at->format('Y-m-d\TH:i') : '')" />
                            <x-input-error :messages="$errors->get('subscription_ends_at')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('تحديث المستخدم') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>