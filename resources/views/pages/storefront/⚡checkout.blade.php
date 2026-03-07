<?php

use App\Models\Country;
use App\Models\Department;
use App\Models\District;
use App\Models\Province;
use App\Models\User;
use App\Services\Storefront\CartService;
use App\Services\Storefront\CheckoutService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $firstName = '';

    public string $lastName = '';

    public string $customerEmail = '';

    public string $customerPhone = '';

    public string $documentType = 'DNI';

    public string $documentNumber = '';

    public string $shippingCountryId = '';

    public string $shippingDepartmentId = '';

    public string $shippingProvinceId = '';

    public string $shippingDistrictId = '';

    public string $shippingAddressLine = '';

    public string $shippingReference = '';

    public string $shippingOption = 'scheduled';

    public bool $existingEmailDetected = false;

    public ?string $feedbackMessage = null;

    public bool $feedbackSuccess = true;

    public bool $isPaymentProcessing = false;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user) {
            $fullName = preg_split('/\s+/', trim((string) $user->name)) ?: [];
            $this->firstName = (string) ($fullName[0] ?? '');
            $this->lastName = (string) (count($fullName) > 1 ? implode(' ', array_slice($fullName, 1)) : '');
            $this->customerEmail = (string) $user->email;
        }

        $countryId = Country::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');
        $this->shippingCountryId = $countryId === null ? '' : (string) $countryId;

        $this->detectExistingEmail();
    }

    public function rendering(View $view): void
    {
        $view->layout('layouts.storefront-checkout', [
            'title' => __('Checkout').' | '.config('app.name'),
            'cancelUrl' => route('storefront.cart.show'),
        ]);
    }

    #[Computed]
    public function cartItems(): Collection
    {
        return app(CartService::class)->detailedItems();
    }

    #[Computed]
    public function cartSummary(): array
    {
        return app(CartService::class)->summary();
    }

    #[Computed]
    public function countries(): Collection
    {
        return Country::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function departments(): Collection
    {
        if ($this->shippingCountryId === '') {
            return collect();
        }

        return Department::query()
            ->whereNull('deleted_at')
            ->where('country_id', (int) $this->shippingCountryId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function provinces(): Collection
    {
        if ($this->shippingDepartmentId === '') {
            return collect();
        }

        return Province::query()
            ->whereNull('deleted_at')
            ->where('department_id', (int) $this->shippingDepartmentId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function districts(): Collection
    {
        if ($this->shippingProvinceId === '') {
            return collect();
        }

        return District::query()
            ->whereNull('deleted_at')
            ->where('province_id', (int) $this->shippingProvinceId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'shipping_price']);
    }

    #[Computed]
    public function shippingBaseMinor(): int
    {
        if ($this->shippingDistrictId === '') {
            return 0;
        }

        return app(CheckoutService::class)->resolveBaseShippingTotal((int) $this->shippingDistrictId);
    }

    #[Computed]
    public function expressAvailable(): bool
    {
        return app(CheckoutService::class)->isExpressAvailable(
            $this->shippingDistrictId === '' ? null : (int) $this->shippingDistrictId,
            $this->shippingProvinceId === '' ? null : (int) $this->shippingProvinceId,
        );
    }

    #[Computed]
    public function shippingTotalMinor(): int
    {
        if ($this->shippingDistrictId === '') {
            return 0;
        }

        return app(CheckoutService::class)->resolveShippingTotal(
            (int) $this->shippingDistrictId,
            (int) $this->cartSummary['subtotal_minor'],
            $this->shippingOption,
        );
    }

    #[Computed]
    public function grandTotalMinor(): int
    {
        return (int) $this->cartSummary['subtotal_minor'] + (int) $this->shippingTotalMinor;
    }

    public function updatedCustomerEmail(): void
    {
        $this->detectExistingEmail();
    }

    public function updatedCustomerPhone(string $value): void
    {
        $this->customerPhone = $this->formatPeruvianPhone($value);
    }

    public function updatedDocumentType(): void
    {
        $this->documentNumber = '';
    }

    public function updatedShippingCountryId(): void
    {
        $this->shippingDepartmentId = '';
        $this->shippingProvinceId = '';
        $this->shippingDistrictId = '';
        $this->shippingOption = 'scheduled';
    }

    public function updatedShippingDepartmentId(): void
    {
        $this->shippingProvinceId = '';
        $this->shippingDistrictId = '';
        $this->shippingOption = 'scheduled';
    }

    public function updatedShippingProvinceId(): void
    {
        $this->shippingDistrictId = '';

        $this->shippingOption = $this->expressAvailable ? 'express' : 'scheduled';
    }

    public function updatedShippingDistrictId(): void
    {
        $this->shippingOption = $this->expressAvailable ? 'express' : 'scheduled';
    }

    public function updatedShippingOption(): void
    {
        if ($this->shippingOption === 'express' && ! $this->expressAvailable) {
            $this->shippingOption = 'scheduled';
        }
    }

    public function increase(int $variantId): void
    {
        $this->applyResult(app(CartService::class)->increment($variantId));
    }

    public function decrease(int $variantId): void
    {
        $this->applyResult(app(CartService::class)->decrement($variantId));
    }

    public function removeItem(int $variantId): void
    {
        $this->applyResult(app(CartService::class)->remove($variantId));
    }

    public function startPayment(): void
    {
        if ((int) $this->cartSummary['items_count'] === 0) {
            $this->feedbackSuccess = false;
            $this->feedbackMessage = __('Your cart is empty.');

            return;
        }

        if (trim((string) config('services.culqi.public_key')) === '') {
            $this->feedbackSuccess = false;
            $this->feedbackMessage = __('Culqi public key is not configured.');

            return;
        }

        if (trim((string) config('services.culqi.secret_key')) === '') {
            $this->feedbackSuccess = false;
            $this->feedbackMessage = __('Culqi secret key is not configured.');

            return;
        }

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['required', 'string', 'max:120'],
            'customerEmail' => ['required', 'string', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'regex:/^9\d{2}\s?\d{3}\s?\d{3}$/'],
            'documentType' => ['required', Rule::in(['DNI', 'CE', 'PASSPORT'])],
            'documentNumber' => ['required', 'string', ...$this->documentNumberRules()],
            'shippingOption' => ['required', Rule::in(['scheduled', 'express'])],
            'shippingCountryId' => ['required', 'integer', 'exists:countries,id'],
            'shippingDepartmentId' => ['required', 'integer', 'exists:departments,id'],
            'shippingProvinceId' => ['required', 'integer', 'exists:provinces,id'],
            'shippingDistrictId' => ['required', 'integer', 'exists:districts,id'],
            'shippingAddressLine' => ['required', 'string', 'max:255'],
            'shippingReference' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['shippingOption'] === 'express' && ! $this->expressAvailable) {
            $this->addError('shippingOption', __('Express shipping is not available for this destination.'));

            return;
        }

        $result = app(CheckoutService::class)->preparePendingOrder([
            'customer_name' => trim($validated['firstName'].' '.$validated['lastName']),
            'customer_email' => $validated['customerEmail'],
            'customer_phone' => preg_replace('/\D+/', '', $validated['customerPhone']) ?? $validated['customerPhone'],
            'customer_document_type' => $validated['documentType'],
            'customer_document_number' => strtoupper(trim($validated['documentNumber'])),
            'shipping_country_id' => (int) $validated['shippingCountryId'],
            'shipping_department_id' => (int) $validated['shippingDepartmentId'],
            'shipping_province_id' => (int) $validated['shippingProvinceId'],
            'shipping_district_id' => (int) $validated['shippingDistrictId'],
            'shipping_address_line' => $validated['shippingAddressLine'],
            'shipping_reference' => $validated['shippingReference'] ?? null,
            'shipping_option' => $validated['shippingOption'],
        ], Auth::user());

        if (! $result['ok']) {
            $this->feedbackSuccess = false;
            $this->feedbackMessage = $result['message'];

            return;
        }

        $this->feedbackSuccess = true;
        $this->feedbackMessage = __('Opening secure payment...');

        $this->dispatch('checkout-open-culqi',
            orderToken: (string) $result['order_token'],
            amount: (int) $result['amount_minor'],
            currency: (string) $result['currency'],
            publicKey: (string) config('services.culqi.public_key'),
            title: config('app.name'),
            email: $this->customerEmail,
            customerName: trim($this->firstName.' '.$this->lastName),
        );
    }

    public function confirmPayment(string $orderToken, string $culqiTokenId): void
    {
        $this->isPaymentProcessing = true;
        $result = app(CheckoutService::class)->confirmCharge($orderToken, $culqiTokenId);

        $this->feedbackSuccess = (bool) $result['ok'];
        $this->feedbackMessage = (string) $result['message'];

        if ($result['ok'] && isset($result['thank_you_url'])) {
            $this->redirect((string) $result['thank_you_url'], navigate: true);

            return;
        }

        $this->isPaymentProcessing = false;
    }

    public function markPaymentFailed(string $orderToken, string $message): void
    {
        app(CheckoutService::class)->markOrderFailed($orderToken, $message, 'culqi_checkout_failed');

        $this->isPaymentProcessing = false;
        $this->feedbackSuccess = false;
        $this->feedbackMessage = $message;
    }

    /**
     * @param  array{ok: bool, message: string, code: string}  $result
     */
    protected function applyResult(array $result): void
    {
        $this->feedbackSuccess = (bool) $result['ok'];
        $this->feedbackMessage = (string) $result['message'];

        unset($this->cartItems, $this->cartSummary, $this->shippingBaseMinor, $this->expressAvailable, $this->shippingTotalMinor, $this->grandTotalMinor);

        $this->dispatch('cart-updated');
    }

    protected function detectExistingEmail(): void
    {
        $email = trim($this->customerEmail);

        if ($email === '') {
            $this->existingEmailDetected = false;

            return;
        }

        $query = User::query()->where('email', $email);

        if (Auth::check()) {
            $query->where('id', '!=', (int) Auth::id());
        }

        $this->existingEmailDetected = $query->exists();
    }

    protected function formatPeruvianPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '51') && strlen($digits) > 9) {
            $digits = substr($digits, 2);
        }

        $digits = substr($digits, 0, 9);

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 3) {
            return $digits;
        }

        if (strlen($digits) <= 6) {
            return substr($digits, 0, 3).' '.substr($digits, 3);
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6);
    }

    /**
     * @return array<int, string>
     */
    protected function documentNumberRules(): array
    {
        return match ($this->documentType) {
            'DNI' => ['regex:/^\d{8}$/'],
            'CE' => ['regex:/^\d{9}$/'],
            'PASSPORT' => ['regex:/^[A-Za-z0-9]{6,12}$/'],
            default => ['regex:/^$/'],
        };
    }

    #[Computed]
    public function isCustomerSectionComplete(): bool
    {
        if (! filter_var($this->customerEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $phoneDigits = preg_replace('/\D+/', '', $this->customerPhone) ?? '';

        if (! preg_match('/^9\d{8}$/', $phoneDigits)) {
            return false;
        }

        if (trim($this->firstName) === '' || trim($this->lastName) === '' || trim($this->documentNumber) === '') {
            return false;
        }

        $documentNumber = strtoupper(trim($this->documentNumber));

        return match ($this->documentType) {
            'DNI' => (bool) preg_match('/^\d{8}$/', $documentNumber),
            'CE' => (bool) preg_match('/^\d{9}$/', $documentNumber),
            'PASSPORT' => (bool) preg_match('/^[A-Z0-9]{6,12}$/', $documentNumber),
            default => false,
        };
    }

    #[Computed]
    public function isShippingSectionComplete(): bool
    {
        return $this->shippingCountryId !== ''
            && $this->shippingDepartmentId !== ''
            && $this->shippingProvinceId !== ''
            && $this->shippingDistrictId !== ''
            && trim($this->shippingAddressLine) !== '';
    }
}; ?>

<section
    x-data="checkoutCulqi(@this)"
    x-on:checkout-open-culqi.window="open($event.detail)"
    class="space-y-6"
>
    @if ($isPaymentProcessing)
        <div class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/70 px-4">
            <div class="w-full max-w-md space-y-3 rounded-sm border border-slate-200 bg-white p-6 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <flux:icon.arrow-path class="mx-auto size-8 animate-spin text-amber-500" />
                <p class="text-base font-semibold text-slate-900 dark:text-zinc-100">{{ __('Processing your payment...') }}</p>
                <p class="text-sm text-slate-600 dark:text-zinc-300">{{ __('Please do not close this page while we confirm the transaction.') }}</p>
            </div>
        </div>
    @endif

    <header class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight">{{ __('Checkout') }}</h1>
        <p class="text-sm text-slate-600 dark:text-zinc-300">{{ __('Complete your information and continue with secure payment.') }}</p>
    </header>

    @if ($feedbackMessage)
        <div class="rounded-sm border px-4 py-3 text-sm {{ $feedbackSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
            {{ $feedbackMessage }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start">
        <div class="space-y-5">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="space-y-1">
                        <flux:heading size="sm" class="inline-flex items-center gap-2">
                            <flux:icon.user-circle class="size-4 text-slate-600 dark:text-zinc-300" />
                            <span>{{ __('Contact information') }}</span>
                        </flux:heading>
                        <flux:text>{{ __('We will use this information for shipping updates and payment.') }}</flux:text>
                    </div>
                    @if ($this->isCustomerSectionComplete)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                            <flux:icon.check-circle class="size-4" />
                            {{ __('Complete') }}
                        </span>
                    @endif
                </div>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model.blur="customerEmail" type="email" autocomplete="email" />
                    <flux:error name="customerEmail" />
                </flux:field>

                <div class="grid gap-3 md:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('First name') }}</flux:label>
                        <flux:input wire:model.blur="firstName" type="text" autocomplete="given-name" />
                        <flux:error name="firstName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Last name') }}</flux:label>
                        <flux:input wire:model.blur="lastName" type="text" autocomplete="family-name" />
                        <flux:error name="lastName" />
                    </flux:field>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Phone (Peru)') }}</flux:label>
                        <flux:input
                            wire:model.live="customerPhone"
                            type="text"
                            autocomplete="tel"
                            placeholder="999 999 999"
                            mask="999 999 999"
                        />
                        <flux:error name="customerPhone" />
                    </flux:field>

                    <div class="space-y-2">
                        <flux:label>{{ __('Document') }}</flux:label>
                        <div class="grid grid-cols-[130px_minmax(0,1fr)] gap-2">
                            <flux:select wire:model.live="documentType">
                                <option value="DNI">{{ __('DNI') }}</option>
                                <option value="CE">{{ __('CE') }}</option>
                                <option value="PASSPORT">{{ __('Passport') }}</option>
                            </flux:select>
                            <flux:input wire:model.blur="documentNumber" type="text" autocomplete="off" />
                        </div>
                        <flux:error name="documentType" />
                        <flux:error name="documentNumber" />
                    </div>
                </div>

                @if ($existingEmailDetected && ! auth()->check())
                    <flux:callout icon="exclamation-triangle" variant="warning" heading="{{ __('This email already has an account') }}">
                        <div class="space-y-2 text-sm">
                            <p>{{ __('You can log in to auto-fill your checkout data, or continue as guest.') }}</p>
                            <a
                                href="{{ route('login') }}"
                                wire:navigate
                                class="inline-flex min-h-10 items-center rounded-sm border border-amber-300 bg-amber-100 px-3 text-xs font-semibold uppercase tracking-wide text-amber-900 transition hover:bg-amber-200"
                            >
                                {{ __('Log in') }}
                            </a>
                        </div>
                    </flux:callout>
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="space-y-1">
                        <flux:heading size="sm" class="inline-flex items-center gap-2">
                            <flux:icon.map-pin class="size-4 text-slate-600 dark:text-zinc-300" />
                            <span>{{ __('Shipping address') }}</span>
                        </flux:heading>
                        <flux:text>{{ __('Select your destination and delivery details.') }}</flux:text>
                    </div>
                    @if ($this->isShippingSectionComplete)
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                            <flux:icon.check-circle class="size-4" />
                            {{ __('Complete') }}
                        </span>
                    @endif
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Country') }}</flux:label>
                        <flux:select wire:model.live="shippingCountryId">
                            <option value="">{{ __('Select country') }}</option>
                            @foreach ($this->countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="shippingCountryId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Department') }}</flux:label>
                        <flux:select wire:model.live="shippingDepartmentId" :disabled="$this->departments->isEmpty()">
                            <option value="">{{ __('Select department') }}</option>
                            @foreach ($this->departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="shippingDepartmentId" />
                    </flux:field>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Province') }}</flux:label>
                        <flux:select wire:model.live="shippingProvinceId" :disabled="$this->provinces->isEmpty()">
                            <option value="">{{ __('Select province') }}</option>
                            @foreach ($this->provinces as $province)
                                <option value="{{ $province->id }}">{{ $province->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="shippingProvinceId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('District') }}</flux:label>
                        <flux:select wire:model.live="shippingDistrictId" :disabled="$this->districts->isEmpty()">
                            <option value="">{{ __('Select district') }}</option>
                            @foreach ($this->districts as $district)
                                <option value="{{ $district->id }}">
                                    {{ $district->name }} ({{ money($district->shipping_price, $this->cartSummary['currency'])->format() }})
                                </option>
                            @endforeach
                        </flux:select>
                        <flux:error name="shippingDistrictId" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Street and number') }}</flux:label>
                    <flux:input wire:model.blur="shippingAddressLine" type="text" autocomplete="street-address" />
                    <flux:error name="shippingAddressLine" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Reference (optional)') }}</flux:label>
                    <flux:input wire:model.blur="shippingReference" type="text" />
                    <flux:error name="shippingReference" />
                </flux:field>

                <div class="space-y-2">
                    <flux:label>{{ __('Shipping option') }}</flux:label>
                    <div class="grid gap-2 md:grid-cols-2">
                        <button
                            type="button"
                            wire:click="$set('shippingOption', 'scheduled')"
                            class="flex w-full items-center justify-between rounded-sm border p-3 text-left transition {{ $shippingOption === 'scheduled' ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/20' : 'border-slate-300 bg-white hover:border-slate-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500' }}"
                        >
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="size-4 text-slate-700 dark:text-zinc-200" />
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ __('Scheduled delivery') }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Free for orders over S/ 100') }}</p>
                                </div>
                            </div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ $this->cartSummary['subtotal_minor'] >= 10000 ? __('Free') : money($this->shippingBaseMinor, $this->cartSummary['currency'])->format() }}
                            </p>
                        </button>

                        <button
                            type="button"
                            wire:click="$set('shippingOption', 'express')"
                            class="flex w-full items-center justify-between rounded-sm border p-3 text-left transition disabled:cursor-not-allowed disabled:opacity-50 {{ $shippingOption === 'express' ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/20' : 'border-slate-300 bg-white hover:border-slate-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500' }}"
                            @disabled(! $this->expressAvailable)
                        >
                            <div class="flex items-center gap-2">
                                <flux:icon.bolt class="size-4 text-slate-700 dark:text-zinc-200" />
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ __('Express delivery') }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Available only in enabled zones') }}</p>
                                </div>
                            </div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                {{ money($this->shippingBaseMinor * 2, $this->cartSummary['currency'])->format() }}
                            </p>
                        </button>
                    </div>
                    <flux:error name="shippingOption" />

                    <div class="rounded-sm border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200">
                        <p class="font-semibold">{{ __('Delivery times') }}</p>
                        <p>{{ __('Scheduled: Lima 1-2 days, Provinces 2-4 days.') }}</p>
                        <p>{{ __('Express: same-day delivery when paid before 1:00 PM.') }}</p>
                    </div>

                    @if (! $this->expressAvailable)
                        <p class="text-xs text-amber-700 dark:text-amber-400">{{ __('Express delivery is not available for the selected province/district.') }}</p>
                    @endif
                </div>
            </flux:card>

            <flux:card class="space-y-3">
                <div class="space-y-1">
                    <flux:heading size="sm" class="inline-flex items-center gap-2">
                        <flux:icon.credit-card class="size-4 text-slate-600 dark:text-zinc-300" />
                        <span>{{ __('Payment') }}</span>
                    </flux:heading>
                    <flux:text>{{ __('Card data is requested securely by Culqi Checkout.') }}</flux:text>
                </div>

                <flux:button
                    variant="primary"
                    wire:click="startPayment"
                    wire:loading.attr="disabled"
                    wire:target="startPayment"
                    class="w-full"
                    :disabled="$this->cartSummary['items_count'] === 0"
                >
                    <span wire:loading.remove wire:target="startPayment">{{ __('Pay Now') }}</span>
                    <span wire:loading wire:target="startPayment">{{ __('Preparing secure checkout...') }}</span>
                </flux:button>
            </flux:card>
        </div>

        <aside class="space-y-4 rounded-sm border border-slate-200 bg-white p-5 lg:sticky lg:top-28 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">{{ __('Order summary') }}</h2>

            <div class="max-h-[40vh] space-y-3 overflow-auto pr-1">
                @forelse ($this->cartItems as $item)
                    @php($variant = $item['variant'])
                    @php($imageUrl = $variant->primary_image_url ?: $variant->product?->featured_image)
                    <article wire:key="checkout-item-{{ $item['variant_id'] }}" class="space-y-2 rounded-sm border border-slate-200 p-3 dark:border-zinc-700">
                        <div class="flex gap-3">
                            <div class="size-16 shrink-0 overflow-hidden rounded-sm border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $variant->product?->name ?? __('Product') }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ __('No image') }}</div>
                                @endif
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ $variant->product?->name ?? __('Product') }}</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">
                                    {{ __('Size') }}: {{ $variant->size ?: __('One Size') }}
                                    <span>·</span>
                                    {{ __('Color') }}: {{ $variant->color ?: __('Default') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2">
                            <div class="inline-flex items-center rounded-sm border border-slate-300 dark:border-zinc-700">
                                <button type="button" wire:click="decrease({{ $item['variant_id'] }})" class="inline-flex min-h-9 min-w-9 items-center justify-center text-slate-700 transition hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                    <flux:icon.minus class="size-4" />
                                </button>
                                <span class="inline-flex min-h-9 min-w-9 items-center justify-center border-x border-slate-300 px-2 text-sm font-semibold dark:border-zinc-700">{{ $item['quantity'] }}</span>
                                <button
                                    type="button"
                                    wire:click="increase({{ $item['variant_id'] }})"
                                    class="inline-flex min-h-9 min-w-9 items-center justify-center text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-zinc-200 dark:hover:bg-zinc-800"
                                    @disabled($item['quantity'] >= $item['stock_available'])
                                >
                                    <flux:icon.plus class="size-4" />
                                </button>
                            </div>

                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ money($item['line_total'], $this->cartSummary['currency'])->format() }}</p>
                                <button
                                    type="button"
                                    wire:click="removeItem({{ $item['variant_id'] }})"
                                    class="inline-flex size-9 items-center justify-center rounded-sm border border-rose-300 text-rose-700 transition hover:bg-rose-50 dark:border-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/20"
                                    aria-label="{{ __('Remove item') }}"
                                >
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-sm border border-dashed border-slate-300 p-5 text-center text-sm text-slate-500 dark:border-zinc-700 dark:text-zinc-300">
                        {{ __('Your cart is empty.') }}
                    </div>
                @endforelse
            </div>

            <div class="space-y-2 border-t border-slate-200 pt-4 text-sm dark:border-zinc-700">
                <div class="flex items-center justify-between text-slate-600 dark:text-zinc-300">
                    <span>{{ __('Subtotal') }}</span>
                    <span>{{ money($this->cartSummary['subtotal_minor'], $this->cartSummary['currency'])->format() }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-600 dark:text-zinc-300">
                    <span>
                        {{ __('Shipping') }}
                        <span class="text-xs">({{ $shippingOption === 'express' ? __('Express') : __('Scheduled') }})</span>
                    </span>
                    <span>{{ money($this->shippingTotalMinor, $this->cartSummary['currency'])->format() }}</span>
                </div>
                <div class="flex items-center justify-between text-base font-semibold text-slate-900 dark:text-zinc-100">
                    <span>{{ __('Total') }}</span>
                    <span>{{ money($this->grandTotalMinor, $this->cartSummary['currency'])->format() }}</span>
                </div>
            </div>
        </aside>
    </div>

    @script
        <script>
            window.checkoutCulqi = (wire) => ({
                async loadScript() {
                    if (!window.Culqi3DS) {
                        window.Culqi3DS = function() {};
                    }

                    if (window.CulqiCheckout) {
                        return true;
                    }

                    const existingScript = document.querySelector('script[data-culqi-checkout]');
                    if (existingScript) {
                        await new Promise((resolve) => {
                            if (window.CulqiCheckout) {
                                resolve(true);

                                return;
                            }

                            existingScript.addEventListener('load', () => resolve(true), { once: true });
                            existingScript.addEventListener('error', () => resolve(false), { once: true });
                        });

                        return Boolean(window.CulqiCheckout);
                    }

                    const script = document.createElement('script');
                    script.src = 'https://js.culqi.com/checkout-js';
                    script.async = true;
                    script.defer = true;
                    script.dataset.culqiCheckout = 'true';

                    const loaded = await new Promise((resolve) => {
                        script.addEventListener('load', () => resolve(true), { once: true });
                        script.addEventListener('error', () => resolve(false), { once: true });
                        document.body.appendChild(script);
                    });

                    return Boolean(loaded) && Boolean(window.CulqiCheckout);
                },

                async open(payload) {
                    const sdkReady = await this.loadScript();

                    if (!sdkReady) {
                        wire.markPaymentFailed(
                            payload.orderToken,
                            'Culqi checkout script is not available. Please disable blockers and retry.'
                        );

                        return;
                    }

                    const culqiConfig = {
                        settings: {
                            title: payload.title,
                            currency: payload.currency,
                            amount: payload.amount,
                        },
                        client: {
                            email: payload.email,
                        },
                        options: {
                            lang: 'es',
                            installments: false,
                            modal: true,
                            paymentMethods: {
                                tarjeta: true,
                                yape: true,
                            },
                        },
                        appearance: {
                            theme: 'default',
                            hiddenCulqiLogo: false,
                            hiddenBannerContent: false,
                            hiddenBanner: false,
                            hiddenToolBarAmount: false,
                            menuType: 'sidebar',
                            buttonCardPayText: 'Pagar monto',
                            logo: null,
                            defaultStyle: {},
                        },
                    };

                    const culqi = new window.CulqiCheckout(payload.publicKey, culqiConfig);
                    window.Culqi = culqi;

                    culqi.culqi = () => {
                        const tokenId = culqi?.token?.id ?? null;
                        const errorMessage = culqi?.error?.user_message ?? null;
                        const merchantMessage = culqi?.error?.merchant_message ?? culqi?.error?.type ?? '';

                        if (tokenId) {
                            culqi.close();
                            wire.confirmPayment(payload.orderToken, tokenId);

                            return;
                        }

                        if (errorMessage) {
                            const normalizedError = `${merchantMessage} ${errorMessage}`.toLowerCase();
                            let fallbackMessage = errorMessage;

                            if (
                                normalizedError.includes('iins')
                                || normalizedError.includes('inss')
                                || normalizedError.includes('3ds')
                                || normalizedError.includes('no pudimos validar tu tarjeta')
                            ) {
                                fallbackMessage = 'Esta tarjeta no es compatible con este checkout. Intenta con otra tarjeta o paga con Yape.';
                            }

                            culqi.close();
                            wire.markPaymentFailed(payload.orderToken, fallbackMessage);
                        }
                    };

                    culqi.open();
                },
            });
        </script>
    @endscript
</section>
