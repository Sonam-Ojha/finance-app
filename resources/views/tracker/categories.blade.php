<x-app-layout>
    <x-slot name="title">Categories</x-slot>

    <div class="space-y-4" x-data="{ q: '' }">
        <div class="flex items-center gap-2">
            <div class="flex-1 relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input type="search" x-model="q" placeholder="Search categories" class="input !pl-9 !rounded-full" aria-label="Search categories">
            </div>
            <a href="{{ route('categories.create') }}" class="btn-primary !rounded-full"><x-icon name="plus" class="w-4 h-4" /> Add</a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            @foreach($categories as $category)
                <a href="{{ route('categories.edit', $category) }}" x-show="@js(mb_strtolower($category->name)).includes(q.trim().toLowerCase())"
                   class="card !p-4 flex flex-col items-center text-center gap-2 hover:shadow-md transition-shadow">
                    <x-tracker.badge :icon="$category->icon" :color="$category->color" size="w-12 h-12 text-2xl" />
                    <span class="text-sm font-medium strong line-clamp-2">{{ $category->name }}</span>
                    @if($category->budget_minor)
                        <span class="text-xs muted">Budget {{ $money->compact($category->budget_minor) }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="text-center py-8" x-show="q.trim() !== '' && ! @js($categories->map(fn ($c) => mb_strtolower($c->name))->values()).some(n => n.includes(q.trim().toLowerCase()))" x-cloak>
            <p class="font-semibold strong">No matches</p>
            <p class="text-sm muted mt-1">No category has that in its name. Clear the search to see them all.</p>
        </div>

        <p class="text-xs muted text-center">Deleting a category refiles its expenses under another one rather than removing them.</p>
    </div>
</x-app-layout>
