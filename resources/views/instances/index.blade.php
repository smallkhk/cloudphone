<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My Cloud Phones') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-4 rounded-md bg-green-50 text-green-800">{{ session('status') }}</div>
            @endif

            @if ($instances->isEmpty())
                <div class="bg-white shadow-sm rounded-lg p-6 text-gray-600">
                    You don't have any cloud phones yet. <a href="{{ route('plans.index') }}" class="text-indigo-600 hover:underline">Browse plans</a>.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($instances as $instance)
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                            @if ($instance->screenshot_url)
                                <img src="{{ $instance->screenshot_url }}" alt="Screenshot" class="w-full h-40 object-cover bg-gray-100">
                            @else
                                <div class="w-full h-40 bg-gray-100 flex items-center justify-center text-gray-400 text-sm">No screenshot yet</div>
                            @endif

                            <div class="p-4">
                                <p class="font-semibold text-gray-900">{{ $instance->nickname ?? $instance->sku?->name ?? 'Cloud Phone' }}</p>
                                <p class="text-sm text-gray-500 font-mono">{{ $instance->pad_code ?? 'Provisioning…' }}</p>

                                <p class="mt-2 text-sm">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full {{ $instance->online ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                        {{ $instance->online ? 'Online' : 'Offline' }}
                                    </span>
                                </p>

                                @if ($instance->expires_at)
                                    <p class="text-xs text-gray-400 mt-1">Expires {{ $instance->expires_at->diffForHumans() }}</p>
                                @endif

                                @if ($instance->pad_code)
                                    <div class="mt-4 flex gap-2">
                                        <form method="POST" action="{{ route('instances.screenshot', $instance) }}">
                                            @csrf
                                            <x-secondary-button type="submit">Screenshot</x-secondary-button>
                                        </form>
                                        <form method="POST" action="{{ route('instances.restart', $instance) }}">
                                            @csrf
                                            <x-secondary-button type="submit">Restart</x-secondary-button>
                                        </form>
                                        <form method="POST" action="{{ route('instances.reset', $instance) }}"
                                              onsubmit="return confirm('Reset clears ALL data on this device. Continue?');">
                                            @csrf
                                            <x-danger-button type="submit">Reset</x-danger-button>
                                        </form>
                                    </div>
                                @else
                                    <p class="mt-4 text-xs text-gray-400">Actions become available once provisioning finishes.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
