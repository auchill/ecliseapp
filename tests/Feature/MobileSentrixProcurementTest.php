<?php

use App\Models\Customer;
use App\Models\MobileSentrixBuffer;
use App\Models\MobileSentrixDevice;
use App\Models\MobileSentrixOrder;
use App\Models\MobileSentrixOrderItem;
use App\Models\Order;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Repair;
use App\Models\RepairConversation;
use App\Models\RepairPartGroup;
use App\Models\RepairPartOption;
use App\Models\RepairPartSelection;
use App\Models\User;
use App\Services\MobileSentrix\MobileSentrixProcurementService;
use App\Services\MobileSentrixMarkupService;
use App\Services\PaymentFinalizer;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    MobileSentrixMarkupService::flushRuleCache();
});

function procurementUser(string $role = 'customer'): User
{
    return User::query()->create([
        'name' => 'Procurement '.ucfirst($role),
        'email' => 'procurement-'.$role.'-'.str()->random(10).'@example.com',
        'password' => 'password',
        'role' => $role,
        'status' => 'active',
    ]);
}

function procurementDevice(array $overrides = []): MobileSentrixDevice
{
    $entityId = $overrides['entity_id'] ?? random_int(500000, 599999);

    return MobileSentrixDevice::query()->create(array_merge([
        'entity_id' => $entityId,
        'sku' => 'MS-DEV-'.$entityId,
        'name' => 'Galaxy S25 Pre-Owned',
        'manufacturer_text' => 'Samsung',
        'available_qty' => 10,
        'qty' => 10,
        // The MobileSentrix cost. Eclise markup is applied on top of this for the customer.
        'price' => 400.00,
        'status' => 'active',
    ], $overrides));
}

function procurementPart(array $overrides = []): Part
{
    $category = PartCategory::query()->firstOrCreate(['id' => 8100], [
        'name' => 'Procurement Screens',
        'slug' => 'procurement-screens',
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
    ]);

    $id = $overrides['id'] ?? random_int(600000, 699999);

    return Part::query()->create(array_merge([
        'id' => $id,
        'category_ids' => [(string) $category->id],
        'name' => 'iPhone 17 Pro Max OLED Screen',
        'slug' => 'procurement-part-'.$id,
        'sku' => 'MS-PART-'.$id,
        'brand' => 'Apple',
        'manufacturer_text' => 'Apple',
        // api_price is the synced MobileSentrix price and the base Eclise markup applies to.
        'price' => 150.00,
        'api_price' => 150.00,
        'cost_price' => 150.00,
        'selling_price' => 220.00,
        'quantity' => 5,
        'supplier' => 'MobileSentrix',
        'external_api_source' => 'MobileSentrix',
        'external_api_id' => (string) $id,
        'is_api_item' => true,
        'is_active' => true,
        'status' => 'active',
    ], $overrides));
}

function procurementOrder(Customer $customer, array $items): Order
{
    $order = Order::query()->create([
        'customer_id' => $customer->id,
        'order_number' => 'ECL-ORD-2026-'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
        'subtotal' => 100,
        'tax' => 13,
        'total' => 113,
        'status' => 'Pending',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'currency' => 'cad',
    ]);

    foreach ($items as $item) {
        $order->items()->create($item);
    }

    return $order->fresh('items');
}

function procurementPayment(Order|Repair $payable, Customer $customer, float $amount = 113): Payment
{
    return $payable->payments()->create([
        'customer_id' => $customer->id,
        'source' => $payable instanceof Order ? 'shop' : 'repair',
        'gateway' => 'stripe',
        'method' => 'stripe',
        'provider' => 'stripe',
        'purpose' => $payable instanceof Order ? 'shop_order' : 'balance',
        'amount' => $amount,
        'currency' => 'cad',
        'status' => 'pending',
    ]);
}

/**
 * Builds a repair whose accepted proposal contains one selected option.
 */
function procurementRepairWithSelection(callable $optionBuilder, float $total = 226.0): array
{
    $user = procurementUser();
    $customer = Customer::forUser($user);
    $repair = Repair::query()->create([
        'customer_id' => $customer->id,
        'repair_number' => 'REP-PROC-'.str()->random(6),
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 17',
        'issue_category' => 'Screen',
        'issue_description' => 'Cracked screen',
        'subtotal' => $total,
        'tax_amount' => 0,
        'total_amount' => $total,
        'amount_paid' => 0,
        'balance_due' => $total,
        'status' => 'awaiting_customer_payment',
        'repair_status' => 'awaiting_customer_payment',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'pickup_or_shipping_option' => 'pickup',
        'repair_total' => $total,
        'currency' => 'cad',
    ]);

    $conversation = RepairConversation::query()->create([
        'repair_id' => $repair->id,
        'customer_id' => $customer->id,
        'status' => 'accepted',
    ]);

    $group = RepairPartGroup::query()->create([
        'repair_conversation_id' => $conversation->id,
        'title' => 'Display',
        'is_required' => true,
        'sort_order' => 1,
        'proposal_version' => 1,
        'is_active' => true,
    ]);

    $option = $optionBuilder($group);

    RepairPartSelection::query()->create([
        'repair_part_group_id' => $group->id,
        'repair_part_option_id' => $option->id,
        'customer_id' => $customer->id,
        'selected_at' => now(),
    ]);

    return [$repair->fresh(), $customer, $group, $option];
}

// ---------------------------------------------------------------------------
// Shop
// ---------------------------------------------------------------------------

test('a paid shop order queues its mobilesentrix device and skips the eclise product', function () {
    $user = procurementUser();
    $customer = Customer::forUser($user);
    $device = procurementDevice();
    $product = Product::query()->create([
        'name' => 'Eclise iPhone Case',
        'slug' => 'eclise-iphone-case-'.str()->random(6),
        'sku' => 'ECL-CASE-'.str()->random(6),
        'price' => 25,
        'quantity' => 10,
        'status' => 'active',
    ]);

    $order = procurementOrder($customer, [
        ['source_id' => $product->id, 'source_sku' => $product->sku, 'source' => 'Eclise', 'quantity' => 1, 'unit_price' => 25, 'line_total' => 25],
        ['source_id' => $device->entity_id, 'source_sku' => $device->sku, 'source' => 'Mobilesentrix', 'quantity' => 1, 'unit_price' => 480, 'line_total' => 480],
    ]);

    app(PaymentFinalizer::class)->markPaid(procurementPayment($order, $customer));

    $buffers = MobileSentrixBuffer::query()->get();

    expect($buffers)->toHaveCount(1);
    expect($buffers->first())
        ->source_sku->toBe($device->sku)
        ->source_id->toBe((int) $device->entity_id)
        ->customer_id->toBe($customer->id)
        ->order_number->toBe($order->order_number)
        ->repair_number->toBeNull()
        ->is_device->toBeTrue()
        ->is_part->toBeFalse()
        ->quantity->toBe(1)
        ->status->toBe(MobileSentrixBuffer::STATUS_PENDING);
});

test('quantity and customer are preserved for multiple mobilesentrix lines', function () {
    $user = procurementUser();
    $customer = Customer::forUser($user);
    $first = procurementDevice();
    $second = procurementDevice();

    $order = procurementOrder($customer, [
        ['source_id' => $first->entity_id, 'source_sku' => $first->sku, 'source' => 'Mobilesentrix', 'quantity' => 1, 'unit_price' => 480, 'line_total' => 480],
        ['source_id' => $second->entity_id, 'source_sku' => $second->sku, 'source' => 'Mobilesentrix', 'quantity' => 2, 'unit_price' => 480, 'line_total' => 960],
    ]);

    app(PaymentFinalizer::class)->markPaid(procurementPayment($order, $customer));

    expect(MobileSentrixBuffer::query()->count())->toBe(2)
        ->and(MobileSentrixBuffer::query()->where('source_sku', $second->sku)->value('quantity'))->toBe(2)
        ->and(MobileSentrixBuffer::query()->pluck('customer_id')->unique()->all())->toBe([$customer->id]);
});

test('an unpaid order queues nothing', function () {
    $user = procurementUser();
    $customer = Customer::forUser($user);
    $device = procurementDevice();

    $order = procurementOrder($customer, [
        ['source_id' => $device->entity_id, 'source_sku' => $device->sku, 'source' => 'Mobilesentrix', 'quantity' => 1, 'unit_price' => 480, 'line_total' => 480],
    ]);

    $payment = procurementPayment($order, $customer);
    app(PaymentFinalizer::class)->markFailed($payment, 'failed');

    expect(MobileSentrixBuffer::query()->count())->toBe(0);
});

test('repeated payment confirmation never duplicates a requirement', function () {
    $user = procurementUser();
    $customer = Customer::forUser($user);
    $device = procurementDevice();

    $order = procurementOrder($customer, [
        ['source_id' => $device->entity_id, 'source_sku' => $device->sku, 'source' => 'Mobilesentrix', 'quantity' => 3, 'unit_price' => 480, 'line_total' => 1440],
    ]);

    $payment = procurementPayment($order, $customer);
    $finalizer = app(PaymentFinalizer::class);

    // Stripe delivering the same event three times.
    $finalizer->markPaid($payment);
    $finalizer->markPaid($payment->fresh());
    $finalizer->markPaid($payment->fresh());

    expect(MobileSentrixBuffer::query()->count())->toBe(1)
        ->and(MobileSentrixBuffer::query()->first()->quantity)->toBe(3);
});

test('the same sku bought in a different order is queued separately', function () {
    $device = procurementDevice();

    foreach ([procurementUser(), procurementUser()] as $user) {
        $customer = Customer::forUser($user);
        $order = procurementOrder($customer, [
            ['source_id' => $device->entity_id, 'source_sku' => $device->sku, 'source' => 'Mobilesentrix', 'quantity' => 1, 'unit_price' => 480, 'line_total' => 480],
        ]);
        app(PaymentFinalizer::class)->markPaid(procurementPayment($order, $customer));
    }

    expect(MobileSentrixBuffer::query()->where('source_sku', $device->sku)->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Repair
// ---------------------------------------------------------------------------

test('a paid repair queues the selected mobilesentrix part', function () {
    $part = procurementPart();

    [$repair, $customer, , $option] = procurementRepairWithSelection(fn (RepairPartGroup $group) => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_PART,
        'is_system_option' => false,
        'source_type' => Part::class,
        'source_id' => $part->id,
        'sku_snapshot' => $part->sku,
        'name_snapshot' => $part->name,
        'price_snapshot' => 220.00,
        'is_primary' => true,
        'proposal_version' => 1,
        'is_active' => true,
    ]));

    app(PaymentFinalizer::class)->markPaid(procurementPayment($repair, $customer, 226));

    $buffer = MobileSentrixBuffer::query()->firstOrFail();

    expect($buffer)
        ->source_sku->toBe($part->sku)
        ->source_id->toBe((int) $part->id)
        ->is_part->toBeTrue()
        ->is_device->toBeFalse()
        ->repair_number->toBe($repair->repair_number)
        ->order_number->toBeNull()
        ->quantity->toBe(1);

    expect($option->price_snapshot)->toEqual(220.00);
});

test('an unpaid repair queues nothing', function () {
    $part = procurementPart();

    [$repair, $customer] = procurementRepairWithSelection(fn (RepairPartGroup $group) => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_PART,
        'is_system_option' => false,
        'source_type' => Part::class,
        'source_id' => $part->id,
        'sku_snapshot' => $part->sku,
        'name_snapshot' => $part->name,
        'price_snapshot' => 220.00,
        'proposal_version' => 1,
        'is_active' => true,
    ]));

    app(PaymentFinalizer::class)->markFailed(procurementPayment($repair, $customer, 226), 'failed');

    expect(MobileSentrixBuffer::query()->count())->toBe(0);
});

test('the customer supplied option queues nothing', function () {
    [$repair, $customer] = procurementRepairWithSelection(fn (RepairPartGroup $group) => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_CUSTOMER_SUPPLIED,
        'is_system_option' => true,
        'system_option_key' => RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED,
        'name_snapshot' => RepairPartOption::CUSTOMER_SUPPLIED_LABEL,
        'price_snapshot' => 0,
        'proposal_version' => 1,
        'is_active' => true,
    ]), 0.0);

    app(PaymentFinalizer::class)->markPaid(procurementPayment($repair, $customer, 100));

    expect(MobileSentrixBuffer::query()->count())->toBe(0);
});

test('unselected and deactivated repair options queue nothing', function () {
    $selected = procurementPart();
    $alternative = procurementPart();

    [$repair, $customer, $group] = procurementRepairWithSelection(fn (RepairPartGroup $group) => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_PART,
        'is_system_option' => false,
        'source_type' => Part::class,
        'source_id' => $selected->id,
        'sku_snapshot' => $selected->sku,
        'name_snapshot' => $selected->name,
        'price_snapshot' => 220.00,
        'proposal_version' => 1,
        'is_active' => true,
    ]));

    // An alternative the customer did not choose, and a removed option.
    RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_PART,
        'is_system_option' => false,
        'source_type' => Part::class,
        'source_id' => $alternative->id,
        'sku_snapshot' => $alternative->sku,
        'name_snapshot' => $alternative->name,
        'price_snapshot' => 180.00,
        'proposal_version' => 1,
        'is_active' => true,
    ]);

    app(PaymentFinalizer::class)->markPaid(procurementPayment($repair, $customer, 226));

    expect(MobileSentrixBuffer::query()->count())->toBe(1)
        ->and(MobileSentrixBuffer::query()->first()->source_sku)->toBe($selected->sku);
});

test('a locally sourced repair part queues nothing', function () {
    $localPart = procurementPart(['is_api_item' => false, 'supplier' => 'Eclise', 'external_api_source' => null]);

    [$repair, $customer] = procurementRepairWithSelection(fn (RepairPartGroup $group) => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_PART,
        'is_system_option' => false,
        'source_type' => Part::class,
        'source_id' => $localPart->id,
        'sku_snapshot' => $localPart->sku,
        'name_snapshot' => $localPart->name,
        'price_snapshot' => 220.00,
        'proposal_version' => 1,
        'is_active' => true,
    ]));

    app(PaymentFinalizer::class)->markPaid(procurementPayment($repair, $customer, 226));

    expect(MobileSentrixBuffer::query()->count())->toBe(0);
});

test('repeated repair payment confirmation never duplicates a requirement', function () {
    $part = procurementPart();

    [$repair, $customer] = procurementRepairWithSelection(fn (RepairPartGroup $group) => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_PART,
        'is_system_option' => false,
        'source_type' => Part::class,
        'source_id' => $part->id,
        'sku_snapshot' => $part->sku,
        'name_snapshot' => $part->name,
        'price_snapshot' => 220.00,
        'proposal_version' => 1,
        'is_active' => true,
    ]));

    $finalizer = app(PaymentFinalizer::class);
    $payment = procurementPayment($repair, $customer, 226);

    $finalizer->markPaid($payment);
    $finalizer->markPaid($payment->fresh());

    expect(MobileSentrixBuffer::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Procurement orders
// ---------------------------------------------------------------------------

function pendingBuffer(int $quantity = 5, ?Part $part = null): MobileSentrixBuffer
{
    $part ??= procurementPart();
    $customer = Customer::forUser(procurementUser());

    return MobileSentrixBuffer::query()->create([
        'customer_id' => $customer->id,
        'order_number' => null,
        'repair_number' => 'REP-BUF-'.str()->random(6),
        'source_reference_type' => MobileSentrixBuffer::SOURCE_REPAIR_PART_SELECTION,
        'source_reference_id' => random_int(1, 999999),
        'is_device' => false,
        'is_part' => true,
        'source_id' => $part->id,
        'source_sku' => $part->sku,
        'quantity' => $quantity,
        'processed_quantity' => 0,
        'status' => MobileSentrixBuffer::STATUS_PENDING,
    ]);
}

test('an admin creates a procurement order that stores the mobilesentrix cost not the selling price', function () {
    $admin = procurementUser('admin');
    $part = procurementPart(['api_price' => 150.00, 'selling_price' => 220.00]);
    $buffer = pendingBuffer(2, $part);

    $order = app(MobileSentrixProcurementService::class)
        ->createProcurementOrder([$buffer->id => 2], $admin);

    $item = $order->items->firstOrFail();

    expect($order->order_number)->toStartWith('MS-ORD-')
        ->and($order->order_status)->toBe(MobileSentrixOrder::STATUS_ORDERED)
        ->and($order->created_by)->toBe($admin->id)
        ->and($item->mobilesentrix_order_id)->toBe($order->id)
        // The MobileSentrix cost, never the customer's marked-up selling price.
        ->and((float) $item->mobilesentrix_price)->toBe(150.00)
        ->and((float) $item->mobilesentrix_tax)->toBe(0.00)
        ->and($item->quantity)->toBe(2)
        ->and($item->repair_number)->toBe($buffer->repair_number)
        ->and((float) $order->subtotal)->toBe(300.00)
        ->and((float) $order->total)->toBe(300.00);
});

test('a partially ordered requirement stays pending with the remainder available', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(5);

    app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 3], $admin);

    $buffer->refresh();

    expect($buffer->status)->toBe(MobileSentrixBuffer::STATUS_PENDING)
        ->and($buffer->processed_quantity)->toBe(3)
        ->and($buffer->remainingQuantity())->toBe(2)
        ->and(MobileSentrixOrderItem::query()->sum('quantity'))->toEqual(3);

    app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 2], $admin);

    $buffer->refresh();

    expect($buffer->status)->toBe(MobileSentrixBuffer::STATUS_PROCESSED)
        ->and($buffer->remainingQuantity())->toBe(0);
});

test('a requirement cannot be over-processed', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(2);

    app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 2], $admin);

    // A second admin acting on a stale screen must be rejected against the locked row.
    expect(fn () => app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 1], $admin))
        ->toThrow(InvalidArgumentException::class);

    expect($buffer->fresh()->processed_quantity)->toBe(2)
        ->and(MobileSentrixOrder::query()->count())->toBe(1);
});

test('ordering more than the remaining quantity is rejected and creates no order', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(2);

    expect(fn () => app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 5], $admin))
        ->toThrow(InvalidArgumentException::class);

    expect(MobileSentrixOrder::query()->count())->toBe(0)
        ->and(MobileSentrixOrderItem::query()->count())->toBe(0)
        ->and($buffer->fresh()->processed_quantity)->toBe(0);
});

test('the whole procurement order rolls back when one line is invalid', function () {
    $admin = procurementUser('admin');
    $good = pendingBuffer(1);
    $bad = pendingBuffer(1);
    $bad->update(['processed_quantity' => 1, 'status' => MobileSentrixBuffer::STATUS_PROCESSED]);

    expect(fn () => app(MobileSentrixProcurementService::class)->createProcurementOrder([
        $good->id => 1,
        $bad->id => 1,
    ], $admin))->toThrow(InvalidArgumentException::class);

    expect(MobileSentrixOrder::query()->count())->toBe(0)
        ->and($good->fresh()->processed_quantity)->toBe(0);
});

test('the price snapshot survives a later catalogue price change', function () {
    $admin = procurementUser('admin');
    $part = procurementPart(['api_price' => 150.00]);
    $buffer = pendingBuffer(1, $part);

    $order = app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 1], $admin);

    $part->update(['api_price' => 999.00, 'price' => 999.00]);

    expect((float) $order->items->first()->fresh()->mobilesentrix_price)->toBe(150.00);
});

test('order totals combine tax shipping and discount without floating point drift', function () {
    $admin = procurementUser('admin');
    $part = procurementPart(['api_price' => 10.10]);
    $buffer = pendingBuffer(3, $part);

    $order = app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 3], $admin);

    expect((float) $order->subtotal)->toBe(30.30);

    $updated = app(MobileSentrixProcurementService::class)->updateOrder($order, [
        'tax' => 3.94,
        'shipping_cost' => 15.00,
        'shipping_discount_amount' => 5.00,
    ], $admin);

    expect((float) $updated->total)->toBe(44.24);
});

test('an admin can move an order from ordered to received and then returned', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(1);
    $order = app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 1], $admin);
    $service = app(MobileSentrixProcurementService::class);

    expect($service->transitionStatus($order, MobileSentrixOrder::STATUS_RECEIVED, $admin)->order_status)
        ->toBe(MobileSentrixOrder::STATUS_RECEIVED);

    expect($service->transitionStatus($order->fresh(), MobileSentrixOrder::STATUS_RETURNED, $admin)->order_status)
        ->toBe(MobileSentrixOrder::STATUS_RETURNED);

    // Returned is terminal.
    expect(fn () => $service->transitionStatus($order->fresh(), MobileSentrixOrder::STATUS_RECEIVED, $admin))
        ->toThrow(InvalidArgumentException::class);
});

test('procurement history is preserved after processing', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(1);

    app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 1], $admin);

    expect(MobileSentrixBuffer::query()->whereKey($buffer->id)->exists())->toBeTrue()
        ->and($buffer->fresh()->status)->toBe(MobileSentrixBuffer::STATUS_PROCESSED)
        ->and(MobileSentrixOrderItem::query()->where('mobilesentrix_buffer_id', $buffer->id)->exists())->toBeTrue();
});

test('procurement actions are written to the existing audit log', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(1);
    $order = app(MobileSentrixProcurementService::class)->createProcurementOrder([$buffer->id => 1], $admin);
    app(MobileSentrixProcurementService::class)->transitionStatus($order, MobileSentrixOrder::STATUS_RECEIVED, $admin);

    $events = PaymentAuditLog::query()->pluck('event')->all();

    expect($events)->toContain('mobilesentrix.order.created')
        ->and($events)->toContain('mobilesentrix.order.received');
});

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

test('procurement routes are admin only', function () {
    $customer = procurementUser();
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(1);

    $this->get(route('admin.mobilesentrix-procurement.index'))->assertRedirect(route('admin.login'));
    $this->actingAs($customer)->get(route('admin.mobilesentrix-procurement.index'))->assertForbidden();
    $this->actingAs($customer)->get(route('admin.mobilesentrix-orders.index'))->assertForbidden();
    $this->actingAs($customer)
        ->post(route('admin.mobilesentrix-procurement.store'), ['quantities' => [$buffer->id => 1]])
        ->assertForbidden();

    expect(MobileSentrixOrder::query()->count())->toBe(0);

    $this->actingAs($admin)->get(route('admin.mobilesentrix-procurement.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.mobilesentrix-orders.index'))->assertOk();
});

test('an admin creates a procurement order through the cart screen', function () {
    $admin = procurementUser('admin');
    $buffer = pendingBuffer(4);

    $this->actingAs($admin)
        ->post(route('admin.mobilesentrix-procurement.store'), [
            'quantities' => [$buffer->id => 3],
        ])
        ->assertRedirect();

    $order = MobileSentrixOrder::query()->firstOrFail();

    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(3)
        ->and($buffer->fresh()->remainingQuantity())->toBe(1);

    $this->actingAs($admin)->get(route('admin.mobilesentrix-orders.show', $order))->assertOk();
});

test('the procurement permission names are seeded', function () {
    expect(Permission::query()->whereIn('name', [
        'mobilesentrix.buffer.view',
        'mobilesentrix.orders.view',
        'mobilesentrix.orders.create',
        'mobilesentrix.orders.update',
        'mobilesentrix.orders.receive',
        'mobilesentrix.orders.return',
    ])->count())->toBe(6);
});
