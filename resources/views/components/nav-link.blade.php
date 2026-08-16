<!-- @props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a> -->
@props(['active'])

@php
$baseClasses = 'inline-flex items-center justify-center min-w-[135px] px-5 py-3 rounded-2xl text-[15px] font-semibold leading-5 whitespace-nowrap text-center transition-all duration-200 ease-in-out';

$classes = ($active ?? false)
    ? $baseClasses . ' text-white bg-indigo-600 shadow-md shadow-indigo-100'
    : $baseClasses . ' text-gray-600 hover:text-indigo-700 hover:bg-indigo-50 hover:shadow-sm';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>