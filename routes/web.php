<?php

use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PartController as AdminPartController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\RepairController as AdminRepairController;
use App\Http\Controllers\Admin\ShippingDiscountRuleController as AdminShippingDiscountRuleController;
use App\Http\Controllers\Admin\ShippingMethodController as AdminShippingMethodController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PartController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\RepairBookingController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/about', [PublicPageController::class, 'about'])->name('about');
Route::get('/services', [PublicPageController::class, 'services'])->name('services');

Route::get('/repairs/book', [RepairBookingController::class, 'create'])->name('repairs.create');
Route::post('/repairs/book', [RepairBookingController::class, 'store'])->name('repairs.store');
Route::get('/repairs/confirmation/{repairBooking}', [RepairBookingController::class, 'confirmation'])->name('repairs.confirmation');
Route::get('/repairs/track', [RepairBookingController::class, 'trackForm'])->name('repairs.track');
Route::post('/repairs/track', [RepairBookingController::class, 'track'])->name('repairs.track.submit');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');
Route::get('/orders/track', [OrderTrackingController::class, 'form'])->name('orders.track');
Route::post('/orders/track/result', [OrderTrackingController::class, 'result'])->name('orders.track.result');
Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [CustomerDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/my-repairs', [CustomerDashboardController::class, 'repairs'])->name('customer.repairs');
    Route::get('/my-orders', [CustomerDashboardController::class, 'orders'])->name('customer.orders');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/products/{product}', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/products/{product}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/products/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/repairs', [AdminRepairController::class, 'index'])->name('repairs.index');
    Route::get('/repairs/{repair}', [AdminRepairController::class, 'show'])->name('repairs.show');
    Route::patch('/repairs/{repair}', [AdminRepairController::class, 'update'])->name('repairs.update');

    Route::resource('products', AdminProductController::class)->except(['show']);
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');

    Route::post('/parts/sync', [AdminPartController::class, 'sync'])->name('parts.sync');
    Route::resource('parts', AdminPartController::class)->except(['show']);
    Route::resource('shipping-methods', AdminShippingMethodController::class)->except(['show']);
    Route::resource('shipping-discounts', AdminShippingDiscountRuleController::class)->except(['show']);

    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::delete('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
});
