@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <h1 class="auth-heading">{{ $title }}</h1>
    <p class="auth-description">{{ $description }}</p>
</div>
