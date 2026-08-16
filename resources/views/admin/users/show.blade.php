<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تفاصيل المستخدم') }}: {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <a href="{{ route('admin.users.index') }}" class="text-indigo-600 hover:text-indigo-900">&larr; العودة لقائمة المستخدمين</a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">الاسم:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $user->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">البريد الإلكتروني:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $user->email }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">رقم الهاتف:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $user->phone_number ?? 'لا يوجد' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">نوع الحساب:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $user->accountType->name ?? 'غير محدد' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">موثق:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($user->is_verified)
                                    <span class="text-green-600">نعم</span>
                                @else
                                    <span class="text-red-600">لا</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">مشترك:</p>
                            <p class="text-lg font-medium text-gray-900">
                                @if($user->is_subscribed)
                                    <span class="text-green-600">نعم</span>
                                @else
                                    <span class="text-red-600">لا</span>
                                @endif
                            </p>
                        </div>
                        @if($user->is_subscribed)
                        <div>
                            <p class="text-sm text-gray-600">تاريخ انتهاء الاشتراك:</p>
                            <p class="text-lg font-medium text-gray-900">{{ $user->subscription_ends_at ? $user->subscription_ends_at->format('Y-m-d H:i') : 'غير محدد' }}</p>
                        </div>
                        @endif
                        @if($user->verification_documents)
                            <div>
                                <p class="text-sm text-gray-600">وثائق التوثيق:</p>
                                <ul class="list-disc list-inside">
                                    @foreach($user->verification_documents as $doc)
                                        <li><a href="{{ asset('storage/' . $doc) }}" target="_blank" class="text-blue-500 hover:underline">{{ basename($doc) }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex">
                        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('تعديل المستخدم') }}
                        </a>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline ms-3" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟ سيتم حذف جميع البيانات المرتبطة به.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('حذف المستخدم') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
