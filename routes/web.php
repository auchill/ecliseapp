<?php

use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Admin\MobileSentrixController as AdminMobileSentrixController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PartBrandController as AdminPartBrandController;
use App\Http\Controllers\Admin\PartCategoryController as AdminPartCategoryController;
use App\Http\Controllers\Admin\PartController as AdminPartController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\ProductBrandController as AdminProductBrandController;
use App\Http\Controllers\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\QuoteController as AdminQuoteController;
use App\Http\Controllers\Admin\ReferenceController as AdminReferenceController;
use App\Http\Controllers\Admin\RepairController as AdminRepairController;
use App\Http\Controllers\Admin\ShippingDiscountRuleController as AdminShippingDiscountRuleController;
use App\Http\Controllers\Admin\ShippingMethodController as AdminShippingMethodController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CertifiedPreOwnedDeviceController;
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

Route::get('/repairs/confirmation/{repairBooking}', [RepairBookingController::class, 'confirmation'])->name('repairs.confirmation');
Route::get('/repairs/track', [RepairBookingController::class, 'trackForm'])->name('repairs.track');
Route::post('/repairs/track', [RepairBookingController::class, 'track'])->name('repairs.track.submit');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/certified-pre-owned-devices', [CertifiedPreOwnedDeviceController::class, 'index'])->name('shop.certified-pre-owned-devices.index');
Route::get('/shop/certified-pre-owned-devices/export', [CertifiedPreOwnedDeviceController::class, 'export'])->name('shop.certified-pre-owned-devices.export');
Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');
Route::get('/orders/track', [OrderTrackingController::class, 'form'])->name('orders.track');
Route::post('/orders/track/result', [OrderTrackingController::class, 'result'])->name('orders.track.result');
Route::get('/parts', [PartController::class, 'index'])->name('parts.index');
Route::get('/parts/search', [PartController::class, 'search'])->name('parts.search');
Route::get('/parts/suggestions', [PartController::class, 'suggestions'])->name('parts.suggestions');
Route::get('/parts/{part}', [PartController::class, 'show'])->whereNumber('part')->name('parts.show');
Route::get('/contact', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [PaymentWebhookController::class, 'paypal'])->name('webhooks.paypal');

Route::middleware('no_admin_cart')->group(function (): void {
    Route::get('/repairs/book', [RepairBookingController::class, 'create'])->name('repairs.create');
    Route::post('/repairs/book', [RepairBookingController::class, 'store'])->name('repairs.store');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/products/{product}', [CartController::class, 'store'])->name('cart.store');
    Route::post('/cart/mobilesentrix-devices', [CartController::class, 'storeDevices'])->name('cart.devices.bulk');
    Route::post('/cart/mobilesentrix-devices/{device}', [CartController::class, 'storeDevice'])->name('cart.devices.store');
    Route::patch('/cart/products/{product}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/products/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::patch('/cart/items', [CartController::class, 'updateItem'])->name('cart.items.update');
    Route::delete('/cart/items', [CartController::class, 'destroyItem'])->name('cart.items.destroy');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/payments/{payment}/stripe/success', [PaymentController::class, 'stripeSuccess'])->name('payments.stripe.success');
    Route::get('/payments/{payment}/paypal/return', [PaymentController::class, 'paypalReturn'])->name('payments.paypal.return');
    Route::get('/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'customer'])->group(function (): void {
    Route::get('/dashboard', [CustomerDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/my-repairs', [CustomerDashboardController::class, 'repairs'])->name('customer.repairs');
    Route::get('/my-orders', [CustomerDashboardController::class, 'orders'])->name('customer.orders');
});

Route::middleware(['auth', 'customer', 'no_admin_cart'])->group(function (): void {
    Route::get('/repairs/book/{trackingNumber}', [RepairBookingController::class, 'complete'])->name('repairs.complete');
    Route::post('/repairs/book/{trackingNumber}', [RepairBookingController::class, 'completeStore'])->name('repairs.complete.store');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');

});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showAdminLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'adminLogin'])->name('login.store');
    Route::post('/logout', [AuthController::class, 'adminLogout'])->name('logout');
});

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('home');
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

    Route::get('/repairs', [AdminRepairController::class, 'index'])->name('repairs.index');
    Route::get('/repairs/{repair}', [AdminRepairController::class, 'show'])->whereNumber('repair')->name('repairs.show');
    Route::patch('/repairs/{repair}', [AdminRepairController::class, 'update'])->whereNumber('repair')->name('repairs.update');

    foreach ([
        'repairs/device-types' => 'device-types',
        'repairs/device-brands' => 'device-brands',
        'repairs/device-models' => 'device-models',
        'repairs/issues' => 'issue-categories',
        'shop/product-models' => 'product-models',
        'parts/part-models' => 'part-models',
        'mobilesentrix/device-manufacturers' => 'device-manufacturers',
        'mobilesentrix/device-colors' => 'device-colors',
        'mobilesentrix/device-conditions' => 'device-conditions',
        'mobilesentrix/device-carriers' => 'device-carriers',
        'mobilesentrix/device-sizes' => 'device-sizes',
        'mobilesentrix/device-grades' => 'device-grades',
    ] as $path => $reference) {
        Route::get($path, [AdminReferenceController::class, 'index'])->defaults('reference', $reference)->name($reference.'.index');
        Route::get($path.'/create', [AdminReferenceController::class, 'create'])->defaults('reference', $reference)->name($reference.'.create');
        Route::post($path, [AdminReferenceController::class, 'store'])->defaults('reference', $reference)->name($reference.'.store');
        Route::get($path.'/{id}/edit', [AdminReferenceController::class, 'edit'])->defaults('reference', $reference)->name($reference.'.edit');
        Route::match(['put', 'patch'], $path.'/{id}', [AdminReferenceController::class, 'update'])->defaults('reference', $reference)->name($reference.'.update');
        Route::delete($path.'/{id}', [AdminReferenceController::class, 'destroy'])->defaults('reference', $reference)->name($reference.'.destroy');
    }

    Route::get('/repairs/quotes', [AdminQuoteController::class, 'index'])->name('quotes.index');
    Route::get('/repairs/quotes/{quote}', [AdminQuoteController::class, 'show'])->name('quotes.show');
    Route::patch('/repairs/quotes/{quote}', [AdminQuoteController::class, 'update'])->name('quotes.update');
    Route::get('/repairs/quotes/{quote}/convert', [AdminQuoteController::class, 'createBooking'])->name('quotes.convert.create');
    Route::post('/repairs/quotes/{quote}/convert', [AdminQuoteController::class, 'storeBooking'])->name('quotes.convert.store');
    Route::get('/repairs/payments', [AdminPaymentController::class, 'index'])->defaults('source', 'repair')->name('repair-payments.index');

    Route::resource('shop/products', AdminProductController::class)->names('products')->except(['show']);
    Route::resource('shop/product-brands', AdminProductBrandController::class)->names('product-brands')->except(['show']);
    Route::resource('shop/product-categories', AdminProductCategoryController::class)->names('product-categories')->except(['show']);
    Route::get('/shop/orders', [AdminOrderController::class, 'index'])->defaults('source', 'shop')->name('orders.index');
    Route::get('/shop/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/shop/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::get('/shop/payments', [AdminPaymentController::class, 'index'])->defaults('source', 'shop')->name('shop-payments.index');

    Route::get('/parts/mobilesentrix', [AdminMobileSentrixController::class, 'index'])->name('parts.mobilesentrix.index');
    Route::post('/parts/mobilesentrix/authenticate-server', [AdminMobileSentrixController::class, 'authenticateServer'])->name('parts.mobilesentrix.authenticate-server');
    Route::post('/parts/mobilesentrix/authorize', [AdminMobileSentrixController::class, 'startAuthorization'])->name('parts.mobilesentrix.authorize');
    Route::get('/parts/mobilesentrix/callback', [AdminMobileSentrixController::class, 'callback'])->name('parts.mobilesentrix.callback');
    Route::post('/parts/mobilesentrix/test', [AdminMobileSentrixController::class, 'test'])->name('parts.mobilesentrix.test');
    Route::post('/parts/mobilesentrix/sync-categories', [AdminMobileSentrixController::class, 'syncCategories'])->name('parts.mobilesentrix.sync-categories');
    Route::post('/parts/mobilesentrix/sync-parts', [AdminMobileSentrixController::class, 'syncParts'])->name('parts.mobilesentrix.sync-parts');
    Route::post('/parts/mobilesentrix/refresh', [AdminMobileSentrixController::class, 'refreshPart'])->name('parts.mobilesentrix.refresh');
    Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/export', [AdminDeviceController::class, 'export'])->name('devices.export');
    Route::post('/devices/sync', [AdminDeviceController::class, 'sync'])->name('devices.sync');
    Route::post('/parts/sync', [AdminPartController::class, 'sync'])->name('parts.sync');
    Route::get('/parts/search', [AdminPartController::class, 'search'])->name('parts.search');
    Route::get('/parts/suggestions', [AdminPartController::class, 'suggestions'])->name('parts.suggestions');
    Route::resource('parts', AdminPartController::class)->except(['show']);
    Route::resource('parts/part-brands', AdminPartBrandController::class)->names('part-brands')->except(['show']);
    Route::resource('parts/part-categories', AdminPartCategoryController::class)->names('part-categories')->except(['show']);
    Route::get('/payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
    Route::resource('shipping/methods', AdminShippingMethodController::class)->parameters(['methods' => 'shippingMethod'])->names('shipping-methods')->except(['show']);
    Route::resource('shipping/discounts', AdminShippingDiscountRuleController::class)->parameters(['discounts' => 'shippingDiscount'])->names('shipping-discounts')->except(['show']);

    Route::resource('users/permissions', AdminPermissionController::class)->names('permissions')->except(['show']);
    Route::resource('users', AdminUserController::class)->except(['show', 'destroy']);

    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');

    Route::get('/messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::delete('/messages/{contactMessage}', [AdminContactMessageController::class, 'destroy'])->name('contact-messages.destroy');

    Route::get('/orders', fn () => redirect()->route('admin.orders.index'))->name('legacy.orders');
    Route::get('/quotes', fn () => redirect()->route('admin.quotes.index'))->name('legacy.quotes');
    Route::get('/contact-messages', fn () => redirect()->route('admin.contact-messages.index'))->name('legacy.contact-messages');
});
