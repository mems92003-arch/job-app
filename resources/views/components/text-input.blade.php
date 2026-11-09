@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg block mt-1 w-full bg-gray-600 text-gray border-white/10 fucas:border-indigo-500 rounded-lg']) }}>