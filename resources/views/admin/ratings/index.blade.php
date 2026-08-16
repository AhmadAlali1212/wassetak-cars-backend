<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            إدارة التقييمات
        </h2>
    </x-slot>

    <div class="py-12" dir="rtl">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        قائمة التقييمات
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الرقم
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        المقيّم
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        المستخدم المقيّم
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        التقييم
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        التعليق
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        التاريخ
                                    </th>
                                    <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        الإجراءات
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($ratings as $rating)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $rating->id }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($rating->rater)
                                        <div class="font-medium text-gray-900">
                                            {{ $rating->rater->name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $rating->rater->email }}
                                        </div>
                                        @else
                                        <span class="text-gray-400">غير محدد</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($rating->ratedUser)
                                        <div class="font-medium text-gray-900">
                                            {{ $rating->ratedUser->name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $rating->ratedUser->email }}
                                        </div>
                                        @else
                                        <span class="text-gray-400">غير محدد</span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $rating->rating }} / 5
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ \Illuminate\Support\Str::limit($rating->comment ?? 'لا يوجد تعليق', 50) }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $rating->created_at ? $rating->created_at->format('Y-m-d H:i') : 'غير محدد' }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="{{ route('admin.ratings.show', $rating) }}"
                                            class="text-indigo-600 hover:text-indigo-900 me-3">
                                            عرض
                                        </a>

                                        <a href="{{ route('admin.ratings.edit', $rating) }}"
                                            class="text-green-600 hover:text-green-900 me-3">
                                            تعديل
                                        </a>

                                        <form action="{{ route('admin.ratings.destroy', $rating) }}"
                                            method="POST"
                                            class="inline"
                                            onsubmit="return confirm('هل أنت متأكد من حذف هذا التقييم؟');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                حذف
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        لا توجد تقييمات حالياً.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $ratings->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>