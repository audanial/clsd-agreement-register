@props([
    'name',
    'placeholder',
    'options' => [],
    'value' => null,
    'isOpen' => false,
])

@php
$selectedOption = collect($options)->first(fn ($option) => (string) $option['value'] === (string) $value);
@endphp

<div class="relative" wire:keydown.escape="closeDropdown('{{ $name }}')">
    <button
        type="button"
        wire:click.stop="toggleDropdown('{{ $name }}')"
        class="mt-1 flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
    >
        <span>
            @if ($selectedOption)
                {!! $selectedOption['label'] !!}
            @else
                {{ $placeholder }}
            @endif
        </span>
        <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    @if ($isOpen)
        <div class="fixed inset-0 z-10" wire:click="closeDropdown('{{ $name }}')"></div>

        <div class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-black ring-opacity-5">
            @foreach ($options as $option)
                <button
                    type="button"
                    wire:click.stop="selectDropdown('{{ $name }}', @js($option['value']))"
                    class="block w-full px-4 py-2 text-left hover:bg-gray-100 {{ (string) $option['value'] === (string) $value ? 'bg-gray-100' : '' }}"
                >
                    {!! $option['label'] !!}
                </button>
            @endforeach
        </div>
    @endif
</div>
