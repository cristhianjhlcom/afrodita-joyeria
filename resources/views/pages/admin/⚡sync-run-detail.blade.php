<?php

use App\Models\SyncRun;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public SyncRun $syncRun;

    public function rendering(View $view): void
    {
        $view->layout('layouts.admin', [
            'title' => __('Sync Run').' #'.$this->syncRun->id,
        ]);
    }

    #[Computed]
    public function details(): SyncRun
    {
        return SyncRun::query()->findOrFail($this->syncRun->id);
    }

    #[Computed]
    public function errorMessages(): array
    {
        $meta = $this->details->meta;
        if (! is_array($meta)) {
            return [];
        }

        $messages = [];

        if (isset($meta['error']) && is_string($meta['error'])) {
            $messages[] = $meta['error'];
        }

        if (isset($meta['errors']) && is_array($meta['errors'])) {
            foreach ($meta['errors'] as $errorMessage) {
                if (is_string($errorMessage) && $errorMessage !== '') {
                    $messages[] = $errorMessage;
                }
            }
        }

        return array_values(array_unique($messages));
    }
}; ?>

<section class="w-full">
    <x-pages::admin.layout
        :heading="__('Sync Run #:id', ['id' => $this->details->id])"
        :subheading="__('Resource: :resource', ['resource' => str($this->details->resource)->headline()])"
    >
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <flux:subheading>
                    {{ __('Status: :status', ['status' => str($this->details->status)->headline()]) }}
                </flux:subheading>
                <flux:button :href="route('admin.dashboard')" variant="ghost" wire:navigate>
                    {{ __('Back to Dashboard') }}
                </flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-5">
                <flux:card>
                    <flux:heading size="sm">{{ __('Started At') }}</flux:heading>
                    <flux:text class="mt-1 text-sm font-semibold">
                        {{ optional($this->details->started_at)?->format('Y-m-d H:i:s') ?? '-' }}
                    </flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Finished At') }}</flux:heading>
                    <flux:text class="mt-1 text-sm font-semibold">
                        {{ optional($this->details->finished_at)?->format('Y-m-d H:i:s') ?? '-' }}
                    </flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Processed') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->details->records_processed) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Errors') }}</flux:heading>
                    <flux:text class="mt-1 text-xl font-semibold">{{ number_format($this->details->errors_count) }}</flux:text>
                </flux:card>
                <flux:card>
                    <flux:heading size="sm">{{ __('Checkpoint') }}</flux:heading>
                    <flux:text class="mt-1 text-sm font-semibold">
                        {{ optional($this->details->checkpoint_updated_since)?->format('Y-m-d H:i:s') ?? '-' }}
                    </flux:text>
                </flux:card>
            </div>

            <div class="space-y-2">
                <flux:subheading>{{ __('Error Inspection') }}</flux:subheading>

                @if ($this->errorMessages !== [])
                    <div class="space-y-2">
                        @foreach ($this->errorMessages as $errorMessage)
                            <flux:callout icon="x-circle" variant="danger" :heading="$errorMessage" />
                        @endforeach
                    </div>
                @else
                    <flux:callout icon="information-circle" variant="secondary" heading="{{ __('No explicit error messages recorded for this run.') }}" />
                @endif
            </div>

            <div class="space-y-2">
                <flux:subheading>{{ __('Raw Meta Payload') }}</flux:subheading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Key') }}</flux:table.column>
                        <flux:table.column>{{ __('Value') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse (($this->details->meta ?? []) as $metaKey => $metaValue)
                            <flux:table.row :key="$metaKey">
                                <flux:table.cell variant="strong">{{ $metaKey }}</flux:table.cell>
                                <flux:table.cell>{{ is_scalar($metaValue) || $metaValue === null ? (string) ($metaValue ?? 'null') : json_encode($metaValue) }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2">{{ __('No metadata stored.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </x-pages::admin.layout>
</section>
