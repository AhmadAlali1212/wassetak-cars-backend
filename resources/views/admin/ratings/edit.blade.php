<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            تعديل التقييم
        </h2>
    </x-slot>

    <div class="py-12" dir="rtl">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <form method="POST" action="{{ route('admin.ratings.update', $rating) }}">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">

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
                        </div>

                        <div>
                            <x-input-label for="rating" :value="__('التقييم من 1 إلى 5')" />

                            <x-text-input id="rating"
                                          class="block mt-1 w-full"
                                          type="number"
                                          name="rating"
                                          :value="old('rating', $rating->rating)"
                                          min="1"
                                          max="5"
                                          required />

                            <x-input-error :messages="$errors->get('rating')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="comment" :value="__('التعليق')" />

                            <textarea id="comment"
                                      name="comment"
                                      rows="4"
                                      class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block mt-1 w-full">{{ old('comment', $rating->comment) }}</textarea>

                            <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end gap-2 mt-6">
                            <a href="{{ route('admin.ratings.index') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600">
                                رجوع
                            </a>

                            <x-primary-button>
                                تحديث التقييم
                            </x-primary-button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>