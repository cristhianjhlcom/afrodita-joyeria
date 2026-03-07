<?php

namespace App\Livewire\Storefront;

use App\Services\Storefront\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CartSheet extends Component
{
    public bool $show = false;

    public ?string $feedbackMessage = null;

    public bool $feedbackSuccess = true;

    public function open(): void
    {
        $this->show = true;
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

    public function clearCart(): void
    {
        app(CartService::class)->clear();
        $this->feedbackSuccess = true;
        $this->feedbackMessage = __('Cart cleared.');
        $this->dispatch('cart-updated');
    }

    public function processPurchase(): void
    {
        $this->applyResult(app(CartService::class)->processPurchase());
    }

    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function render(): View
    {
        $cartService = app(CartService::class);

        return view('livewire.storefront.cart-sheet', [
            'cartSummary' => $cartService->summary(),
            'cartItems' => $cartService->detailedItems(),
        ]);
    }

    /**
     * @param  array{ok: bool, message: string, code: string}  $result
     */
    protected function applyResult(array $result): void
    {
        $this->feedbackSuccess = (bool) $result['ok'];
        $this->feedbackMessage = (string) $result['message'];

        if ($result['ok']) {
            $this->dispatch('cart-updated');
        }
    }
}
