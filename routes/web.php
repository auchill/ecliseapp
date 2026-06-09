<?php

use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PartBrandController as AdminPartBrandController;
use App\Http\Controllers\Admin\PartCategoryController as AdminPartCategoryController;
use App\Http\Controllers\Admin\PartController as AdminPartController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductBrandController as AdminProductBrandController;
use App\Http\Controllers\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Admin\ReferenceController as AdminReferenceController;
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
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RepairBookingController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/about', [PublicPageController::class, 'about'])->name('about');
Route::get('/services', [PublicPageController::class, 'services'])->name('services');
Route::get('/repairs/quote', [QuoteController::class, 'create'])->name('quotes.create');
Route::post('/repairs/quote', [QuoteController::class, 'store'])->name('quotes.store');

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
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/products/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/products/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/products/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [PaymentWebhookController::class, 'paypal'])->name('webhooks.paypal');
Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
Route::get('/payments/{payment}/stripe/success', [PaymentController::class, 'stripeSuccess'])->name('payments.stripe.success');
Route::get('/payments/{payment}/paypal/return', [PaymentController::class, 'paypalReturn'])->name('payments.paypal.return');
Route::get('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');

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
    Route::get('/repairs/book/{trackingNumber}', [RepairBookingController::class, 'complete'])->name('repairs.complete');
    Route::post('/repairs/book/{trackingNumber}', [RepairBookingController::class, 'completeStore'])->name('repairs.complete.store');

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
    Route::resource('product-brands', AdminProductBrandController::class)->except(['show']);
    Route::resource('product-categories', AdminProductCategoryController::class)->except(['show']);
    foreach (['product-models', 'part-models', 'device-types', 'device-brands', 'device-models', 'issue-categories'] as $reference) {
        Route::get($reference, [AdminReferenceController::class, 'index'])->defaults('reference', $reference)->name($reference.'.index');
        Route::get($reference.'/create', [AdminReferenceController::class, 'create'])->defaults('reference', $reference)->name($reference.'.create');
        Route::post($reference, [AdminReferenceController::class, 'store'])->defaults('reference', $reference)->name($reference.'.store');
        Route::get($reference.'/{id}/edit', [AdminReferenceController::class, 'edit'])->defaults('reference', $reference)->name($reference.'.edit');
        Route::match(['put', 'patch'], $reference.'/{id}', [AdminReferenceController::class, 'update'])->defaults('reference', $reference)->name($reference.'.update');
        Route::delete($reference.'/{id}', [AdminReferenceController::class, 'destroy'])->defaults('reference', $reference)->name($reference.'.destroy');
    }
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');

    Route::post('/parts/sync', [AdminPartController::class, 'sync'])->name('parts.sync');
    Route::resource('parts', AdminPartController::class)->except(['show']);
    Route::resource('part-brands', AdminPartBrandController::class)->except(['show']);
    Route::resource('part-categories', AdminPartCategoryController::class)->except(['show']);
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::get('/quotes', [AdminQuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/{quote}', [AdminQuoteController::class, 'show'])->name('quotes.show');
    Route::patch('/quotes/{quote}', [AdminQuoteController::class, 'update'])->name('quotes.update');
    Route::get('/quotes/{quote}/convert', [AdminQuoteController::class, 'createBooking'])->name('quotes.convert.create');
    Route::post('/quotes/{quote}/convert', [AdminQuoteController::class, 'storeBooking'])->name('quotes.convert.store');
    Route::resource('shipping-methods', AdminShippingMethodController::class)->except(['show']);
    Route::resource('shipping-discounts', AdminShippingDiscountRuleController::class)->except(['show']);

    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::delete('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');
});
