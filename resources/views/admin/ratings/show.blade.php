<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            تفاصيل التقييم
        </h2>
    </x-slot>

    <div class="py-12" dir="rtl">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-4">
                <a href="{{ route('admin.ratings.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    رجوع إلى قائمة التقييمات
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">رقم التقييم</p>
                            <p class="font-medium text-gray-900">{{ $rating->id }}</p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">التقييم</p>
                            <p class="font-medium text-gray-900">{{ $rating->rating }} / 5</p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">المقيّم</p>
                            @if($rating->rater)
                                <p class="font-medium text-gray-900">{{ $rating->rater->name }}</p>
                                <p class="text-sm text-gray-500">{{ $rating->rater->email }}</p>
                            @else
                                <p class="text-gray-400">غير محدد</p>
                            @endif
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">المستخدم المقيّم</p>
                            @if($rating->ratedUser)
                                <p class="font-medium text-gray-900">{{ $rating->ratedUser->name }}</p>
                                <p class="text-sm text-gray-500">{{ $rating->ratedUser->email }}</p>
                            @else
                                <p class="text-gray-400">غير محدد</p>
                            @endif
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 md:col-span-2">
                            <p class="text-sm text-gray-500 mb-1">التعليق</p>
                            <p class="font-medium text-gray-900 whitespace-pre-wrap">
                                {{ $rating->comment ?? 'لا يوجد تعليق' }}
                            </p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">تاريخ الإنشاء</p>
                            <p class="font-medium text-gray-900">
                                {{ $rating->created_at ? $rating->created_at->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-500 mb-1">آخر تعديل</p>
                            <p class="font-medium text-gray-900">
                                {{ $rating->updated_at ? $rating->updated_at->format('Y-m-d H:i') : 'غير محدد' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <a href="{{ route('admin.ratings.edit', $rating) }}"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            تعديل
                        </a>

                        <form action="{{ route('admin.ratings.destroy', $rating) }}"
                              method="POST"
                              onsubmit="return confirm('هل أنت متأكد من حذف هذا التقييم؟');">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700">
                                حذف
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>