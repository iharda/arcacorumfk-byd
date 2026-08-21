<x-filament-panels::page>
    <form wire:submit.prevent="mountAction('kaydet')">
        {{ $this->form }}

        <div style="margin-top:1.25rem; display:flex; justify-content:flex-end;">
            {{ $this->kaydetAction }}
        </div>
    </form>
</x-filament-panels::page>
