<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Save settings</x-filament::button>
    </form>
    <div class="flex flex-wrap gap-3">
        <x-filament::button color="gray" wire:click="testConnection" wire:target="testConnection" wire:loading.attr="disabled">Test Connection</x-filament::button>
        <x-filament::button color="gray" wire:click="sendTestMessage" wire:target="sendTestMessage" wire:loading.attr="disabled">Send Test Message</x-filament::button>
    </div>
</x-filament-panels::page>
