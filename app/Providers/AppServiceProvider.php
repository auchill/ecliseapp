<?php

namespace App\Providers;

use App\Models\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.app', function ($view): void {
            $count = 0;

            if (auth()->check()) {
                $cart = Cart::query()
                    ->where('user_id', auth()->id())
                    ->where('status', 'active')
                    ->with('items')
                    ->first();

                $count = $cart?->items->sum('quantity') ?? 0;
            } elseif (request()->hasSession()) {
                $count = collect(request()->session()->get('cart.items', []))->sum();
            }

            $view->with('cartItemCount', (int) $count);
        });
    }
}
