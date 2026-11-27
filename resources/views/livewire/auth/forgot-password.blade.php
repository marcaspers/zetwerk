<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('Als dit account bestaat, staat een herstellink in de lokale mailpreview.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Wachtwoord vergeten" description="Vraag een herstellink aan. In deze demo blijft de mail lokaal." />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- E-mailadres -->
        <div class="grid gap-2">
            <flux:input wire:model="email" label="{{ __('E-mailadres') }}" type="email" name="email" required autofocus placeholder="email@example.com" />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Herstellink aanvragen') }}</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Of ga terug naar
        <x-text-link href="{{ route('login') }}">inloggen</x-text-link>
    </div>
</div>
