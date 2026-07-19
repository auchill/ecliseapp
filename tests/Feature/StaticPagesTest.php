<?php

use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DefaultPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(DefaultPermissionsSeeder::class);
});

function staticPageCustomer(string $email = 'static-json-customer@example.com'): User
{
    return User::query()->create([
        'name' => 'Static JSON Customer',
        'email' => $email,
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => Permission::query()->where('name', 'customer')->value('id'),
        'status' => 'active',
    ]);
}

function staticPageProduct(string $sku = 'STATIC-AJAX-1', int $quantity = 5): Product
{
    return Product::query()->create([
        'name' => 'Static AJAX Product With A Longer Name',
        'slug' => strtolower($sku),
        'sku' => $sku,
        'regular_price' => 50,
        'quantity' => $quantity,
        'source' => 'manual',
        'is_active' => true,
    ]);
}

test('public static pages load with updated professional content and metadata', function (string $routeName, string $heading) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertSee('meta name="description"', false)
        ->assertSee('Eclise Technology Inc.')
        ->assertSee($heading);
})->with([
    ['home', 'Professional Phone, Tablet and Computer Repair'],
    ['about', 'Technology repair, parts and product services with a practical customer workflow.'],
    ['services', 'Repair, diagnostics, parts and product services for everyday devices.'],
    ['contact.create', 'Reach Eclise Technology Inc.'],
]);

test('static pages render route backed customer actions without requiring auth for public links', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('repairs.create').'"', false)
        ->assertSee('href="'.route('shop.index').'"', false)
        ->assertSee('href="'.route('parts.index').'"', false)
        ->assertSee('href="'.route('contact.create').'"', false)
        ->assertSee('data-intended-url="'.route('quotes.create').'"', false)
        ->assertDontSee('data-intended-url="'.route('repairs.create').'"', false)
        ->assertDontSee('data-intended-url="'.route('shop.index').'"', false)
        ->assertDontSee('data-intended-url="'.route('parts.index').'"', false);
});

test('home page renders an accessible branded carousel with valid slide assets and routes', function () {
    $response = $this->get(route('home'));
    $html = $response->getContent();

    $response->assertOk()
        ->assertSee('data-eclise-home-carousel', false)
        ->assertSee('Previous home slide')
        ->assertSee('Next home slide')
        ->assertSee('Professional Phone, Tablet and Computer Repair')
        ->assertSee('Shop Phones, Computers and Accessories')
        ->assertSee('Find Replacement Parts for Your Device')
        ->assertSee('Stay Updated on Your Repair')
        ->assertSee('Need Help Choosing the Right Service?')
        ->assertSee('href="'.route('quotes.create').'"', false)
        ->assertSee('href="'.route('repairs.create').'"', false)
        ->assertSee('href="'.route('shop.index').'"', false)
        ->assertSee('href="'.route('parts.index').'"', false)
        ->assertSee('href="'.route('contact.create').'"', false)
        ->assertDontSee('href="#"', false);

    preg_match_all('/class="carousel-item/', $html, $slideMatches);
    expect(count($slideMatches[0]))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5);

    preg_match_all('/data-slide-image="([^"]+)"/', $html, $imageMatches);
    expect($imageMatches[1])->toHaveCount(count($slideMatches[0]));

    foreach ($imageMatches[1] as $imagePath) {
        expect(file_exists(public_path($imagePath)))->toBeTrue("Missing slide image: {$imagePath}");
    }
});

test('authenticated customers can view static pages without customer flow regressions', function () {
    $customer = User::query()->create([
        'name' => 'Static Page Customer',
        'email' => 'static-customer@example.com',
        'password' => 'password',
        'role' => 'customer',
        'permission_id' => Permission::query()->where('name', 'customer')->value('id'),
        'status' => 'active',
    ]);

    foreach (['home', 'about', 'services', 'contact.create'] as $routeName) {
        $this->actingAs($customer)
            ->get(route($routeName))
            ->assertOk();
    }
});

test('contact page renders csrf protected enquiry form and verified contact fallback', function () {
    $this->get(route('contact.create'))
        ->assertOk()
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="enquiry_type"', false)
        ->assertSee('value="parts"', false)
        ->assertSee('Verified phone, email, address and hours have not been configured yet.')
        ->assertSee('href="'.route('repairs.track').'"', false)
        ->assertSee('href="'.route('orders.track').'"', false);
});

test('contact form validates required fields and allowed enquiry types', function () {
    $this->from(route('contact.create'))
        ->post(route('contact.store'), [
            'email' => 'not-an-email',
            'enquiry_type' => 'unsupported',
            'message' => 'short',
        ])
        ->assertRedirect(route('contact.create'))
        ->assertSessionHasErrors(['name', 'email', 'enquiry_type', 'subject', 'message']);
});

test('contact form stores a sanitized enquiry label in the message subject', function () {
    $this->post(route('contact.store'), [
        'name' => 'Jordan Lee',
        'email' => 'jordan@example.com',
        'phone' => '416-555-0188',
        'enquiry_type' => 'parts',
        'subject' => 'Battery availability',
        'message' => 'I would like to confirm replacement battery availability.',
    ])
        ->assertRedirect(route('contact.create'))
        ->assertSessionHas('status');

    $message = ContactMessage::query()->firstOrFail();

    expect($message->name)->toBe('Jordan Lee')
        ->and($message->email)->toBe('jordan@example.com')
        ->and($message->phone)->toBe('416-555-0188')
        ->and($message->subject)->toBe('[Parts] Battery availability')
        ->and($message->message)->toBe('I would like to confirm replacement battery availability.');
});

test('contact form returns json for asynchronous success and validation failure', function () {
    $this->postJson(route('contact.store'), [
        'name' => 'Avery Quinn',
        'email' => 'avery@example.com',
        'enquiry_type' => 'repair',
        'subject' => 'Screen service',
        'message' => 'I need help checking repair options for my screen.',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Message sent. The Eclise team will respond soon.');

    expect(ContactMessage::query()->where('email', 'avery@example.com')->first()?->subject)->toBe('[Repair] Screen service');

    $this->postJson(route('contact.store'), [
        'email' => 'not-an-email',
        'enquiry_type' => 'invalid',
        'message' => 'short',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'enquiry_type', 'subject', 'message']);
});

test('cart endpoints return authoritative json totals for asynchronous add update and remove', function () {
    $customer = staticPageCustomer();
    $product = staticPageProduct();

    $addResponse = $this->actingAs($customer)
        ->postJson(route('cart.store', $product), ['quantity' => 2])
        ->assertOk()
        ->assertJsonPath('message', 'Product added to cart.')
        ->assertJsonPath('cart.count', 2)
        ->assertJsonPath('cart.subtotal_display', '$100.00');

    $itemKey = $addResponse->json('cart.items.0.cart_key');

    expect($itemKey)->toBe('Eclise:'.$product->id.':'.rawurlencode($product->sku))
        ->and(Customer::forUser($customer)->activeCart()->first()?->items()->first()?->quantity)->toBe(2);

    $this->actingAs($customer)
        ->patchJson(route('cart.items.update'), [
            'item_key' => $itemKey,
            'quantity' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Cart updated.')
        ->assertJsonPath('cart.count', 1)
        ->assertJsonPath('cart.subtotal_display', '$50.00');

    $this->actingAs($customer)
        ->deleteJson(route('cart.items.destroy'), [
            'item_key' => $itemKey,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Item removed from cart.')
        ->assertJsonPath('cart.count', 0)
        ->assertJsonPath('cart.subtotal_display', '$0.00');

    expect(CartItem::query()->count())->toBe(0);
});
