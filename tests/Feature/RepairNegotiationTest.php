<?php

use App\Models\Customer;
use App\Models\EcliseMarkup;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Payment;
use App\Models\Repair;
use App\Models\RepairConversation;
use App\Models\RepairPartOption;
use App\Models\User;
use App\Services\MobileSentrixMarkupService;
use App\Services\PaymentFinalizer;
use App\Services\RepairNegotiationService;
use App\Support\CatalogImage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;

function negotiationUser(string $email, string $role = 'customer'): User
{
    return User::query()->create([
        'name' => ucfirst($role).' Negotiation',
        'email' => $email,
        'password' => 'password',
        'role' => $role,
        'status' => 'active',
    ]);
}

function negotiationRepair(User $user, array $overrides = []): Repair
{
    $customer = Customer::forUser($user);

    return Repair::query()->create(array_merge([
        'customer_id' => $customer->id,
        'repair_number' => 'REP-NEG-'.random_int(1000, 9999),
        'device_type' => 'Phone',
        'device_brand' => 'Apple',
        'device_model' => 'iPhone 14',
        'issue_category' => 'Screen',
        'issue_description' => 'Broken screen',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
        'amount_paid' => 0,
        'balance_due' => 0,
        'status' => 'diagnosis_in_progress',
        'repair_status' => 'diagnosis_in_progress',
        'payment_status' => 'unpaid',
        'fulfillment_method' => 'pickup',
        'pickup_or_shipping_option' => 'pickup',
        'repair_total' => 0,
    ], $overrides));
}

function negotiationPart(array $overrides = []): Part
{
    MobileSentrixMarkupService::flushRuleCache();

    PartCategory::query()->firstOrCreate(['id' => 165], [
        'name' => 'Replacement Parts',
        'slug' => 'replacement-parts',
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
    ]);
    PartCategory::query()->firstOrCreate(['id' => 756], [
        'name' => 'Apple',
        'slug' => 'apple',
        'parent_id' => 165,
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
    ]);
    $category = PartCategory::query()->firstOrCreate(['id' => $overrides['category_id'] ?? 7001], [
        'name' => $overrides['category_name'] ?? 'Screens',
        'slug' => str($overrides['category_name'] ?? 'screens')->slug(),
        'parent_id' => 756,
        'is_part' => true,
        'is_active' => true,
        'status' => 'active',
    ]);
    unset($overrides['category_id'], $overrides['category_name']);

    $id = $overrides['id'] ?? random_int(300000, 399999);
    $part = Part::query()->create(array_merge([
        'id' => $id,
        'category_ids' => [(string) $category->id],
        'name' => 'Negotiation Display Assembly',
        'slug' => 'negotiation-display-assembly-'.$id,
        'sku' => 'NEG-SKU-'.$id,
        'new_sku' => 'NEG-NEW-'.$id,
        'brand' => 'Apple',
        'manufacturer_text' => 'Apple',
        'model' => 'A2894',
        'model_compatibility' => 'iPhone 14',
        'device_model_text' => 'iPhone 14',
        'model_text' => ['iPhone 14'],
        'part_category' => $category->name,
        'description' => 'Proposal part.',
        'price' => 120,
        'api_price' => 120,
        'cost_price' => 120,
        'quantity' => 4,
        'in_stock_qty' => 4,
        'is_in_stock' => true,
        'stock_status' => 'In stock',
        'supplier' => 'MobileSentrix',
        'is_api_item' => true,
        'is_active' => true,
        'status' => 'active',
        'api_status' => 'active',
    ], $overrides));

    $part->partCategories()->syncWithoutDetaching([$category->id]);

    return $part;
}

test('admin internal repair messages are hidden from the customer conversation', function () {
    $admin = negotiationUser('negotiation-admin@example.com', 'admin');
    $customerUser = negotiationUser('negotiation-customer@example.com');
    $repair = negotiationRepair($customerUser);

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.messages.store', $repair), [
            'message' => 'Customer visible update',
        ])
        ->assertRedirect(route('admin.repairs.show', $repair));

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.messages.store', $repair), [
            'message' => 'Internal diagnostic note',
            'is_internal' => 1,
        ])
        ->assertRedirect(route('admin.repairs.show', $repair));

    $this->actingAs($customerUser)
        ->get(route('repair-conversations.show-for-repair', $repair))
        ->assertOk()
        ->assertSee('Customer visible update')
        ->assertDontSee('Internal diagnostic note');
});

test('customer must choose required part options before accepting proposal', function () {
    $admin = negotiationUser('proposal-admin@example.com', 'admin');
    $customerUser = negotiationUser('proposal-customer@example.com');
    $customer = Customer::forUser($customerUser);
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Display assembly'], $admin);
    $part = negotiationPart(['price' => 120, 'api_price' => 120, 'cost_price' => 120]);
    $option = $service->addPartOption($group, [
        'part_id' => $part->id,
    ], $admin);
    $service->updateCharges($conversation->fresh(), [
        'labour_amount' => 80,
        'diagnostic_fee' => 0,
        'service_fee' => 0,
        'discount_amount' => 0,
        'tax_amount' => 26,
    ], $admin);
    $conversation = $service->sendProposal($conversation->fresh(), $admin);

    $this->actingAs($customerUser)
        ->post(route('repair-conversations.accept', $conversation), ['agreement_accepted' => 1])
        ->assertSessionHasErrors('agreement');

    $service->selectOption($conversation->fresh(), $customer, $option->id);

    $this->actingAs($customerUser)
        ->post(route('repair-conversations.accept', $conversation), ['agreement_accepted' => 1])
        ->assertRedirect(route('repair-conversations.show', $conversation));

    $conversation->refresh();
    $repair->refresh();

    expect($conversation->status)->toBe(RepairConversation::STATUS_PAYMENT_PENDING)
        ->and((float) $conversation->final_total)->toBe(226.0)
        ->and((float) $repair->repair_total)->toBe(226.0)
        ->and($repair->repair_status)->toBe('awaiting_customer_payment');
});

test('required part groups include a protected customer supplied option first', function () {
    $admin = negotiationUser('system-option-admin@example.com', 'admin');
    $customerUser = negotiationUser('system-option-customer@example.com');
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Screen', 'is_required' => false], $admin);
    $part = negotiationPart(['sku' => 'SYSTEM-NORMAL-1']);
    $normalOption = $service->addPartOption($group, ['part_id' => $part->id, 'is_primary' => true], $admin);
    $options = $group->fresh()->activeOptions()->get();
    $systemOption = $options->first();

    expect($group->fresh()->is_required)->toBeTrue()
        ->and($systemOption->isCustomerSuppliedOption())->toBeTrue()
        ->and($systemOption->name_snapshot)->toBe(RepairPartOption::CUSTOMER_SUPPLIED_LABEL)
        ->and((float) $systemOption->price_snapshot)->toBe(0.0)
        ->and($systemOption->source_type)->toBeNull()
        ->and($systemOption->source_id)->toBeNull()
        ->and($systemOption->sku_snapshot)->toBeNull()
        ->and($systemOption->is_primary)->toBeFalse()
        ->and($options->pluck('id')->all())->toBe([$systemOption->id, $normalOption->id]);

    $this->actingAs($admin)
        ->patch(route('admin.repairs.conversation.part-options.primary', [$repair, $systemOption]))
        ->assertSessionHasErrors('part_id');

    $this->actingAs($admin)
        ->delete(route('admin.repairs.conversation.part-options.destroy', [$repair, $systemOption]))
        ->assertSessionHasErrors('part_id');

    expect($systemOption->fresh()->is_active)->toBeTrue()
        ->and($group->fresh()->activeOptions()->where('system_option_key', RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED)->count())->toBe(1);

    expect(fn () => RepairPartOption::query()->create([
        'repair_part_group_id' => $group->id,
        'option_type' => RepairPartOption::TYPE_CUSTOMER_SUPPLIED,
        'is_system_option' => true,
        'system_option_key' => RepairPartOption::SYSTEM_KEY_CUSTOMER_SUPPLIED,
        'name_snapshot' => RepairPartOption::CUSTOMER_SUPPLIED_LABEL,
        'price_snapshot' => 0,
        'is_primary' => false,
        'sort_order' => 0,
        'proposal_version' => 1,
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

test('customer supplied option is selectable and contributes zero to selected parts total', function () {
    $admin = negotiationUser('customer-supplied-admin@example.com', 'admin');
    $customerUser = negotiationUser('customer-supplied-customer@example.com');
    $customer = Customer::forUser($customerUser);
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Battery'], $admin);
    $systemOption = $group->fresh()->activeOptions()->firstOrFail();

    $service->updateCharges($conversation->fresh(), [
        'labour_amount' => 75,
        'diagnostic_fee' => 5,
        'service_fee' => 0,
        'discount_amount' => 0,
        'tax_amount' => 10,
    ], $admin);
    $conversation = $service->sendProposal($conversation->fresh(), $admin);

    $this->actingAs($customerUser)
        ->post(route('repair-conversations.part-selections.store', $conversation), [
            'option_id' => $systemOption->id,
        ])
        ->assertRedirect(route('repair-conversations.show', $conversation));

    $this->actingAs($customerUser)
        ->post(route('repair-conversations.accept', $conversation), ['agreement_accepted' => 1])
        ->assertRedirect(route('repair-conversations.show', $conversation));

    $conversation->refresh();

    expect($systemOption->fresh()->isCustomerSuppliedOption())->toBeTrue()
        ->and($group->selections()->where('customer_id', $customer->id)->where('repair_part_option_id', $systemOption->id)->exists())->toBeTrue()
        ->and((float) $conversation->selected_parts_subtotal)->toBe(0.0)
        ->and((float) $conversation->final_total)->toBe(90.0)
        ->and($conversation->status)->toBe(RepairConversation::STATUS_PAYMENT_PENDING);
});

test('repair proposal payment uses the server agreed total and marks conversation paid', function () {
    Mail::fake();

    $admin = negotiationUser('payment-admin@example.com', 'admin');
    $customerUser = negotiationUser('payment-customer@example.com');
    $customer = Customer::forUser($customerUser);
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Battery'], $admin);
    $part = negotiationPart([
        'name' => 'Premium battery',
        'sku' => 'BAT-1',
        'price' => 90,
        'api_price' => 90,
        'cost_price' => 90,
    ]);
    $option = $service->addPartOption($group, [
        'part_id' => $part->id,
    ], $admin);
    $service->updateCharges($conversation->fresh(), [
        'labour_amount' => 60,
        'diagnostic_fee' => 10,
        'service_fee' => 0,
        'discount_amount' => 5,
        'tax_amount' => 20,
    ], $admin);
    $conversation = $service->sendProposal($conversation->fresh(), $admin);
    $service->selectOption($conversation->fresh(), $customer, $option->id);
    $conversation = $service->acceptProposal($conversation->fresh(), $customer);

    $payment = $service->createPayment($conversation, $customer, 'stripe');

    expect($payment)->toBeInstanceOf(Payment::class)
        ->and((float) $payment->amount)->toBe(175.0)
        ->and($payment->source)->toBe('repair')
        ->and((int) data_get($payment->checkout_data, 'conversation_id'))->toBe($conversation->id);

    app(PaymentFinalizer::class)->markPaid($payment);

    expect($conversation->fresh()->status)->toBe(RepairConversation::STATUS_PAID)
        ->and($repair->fresh()->payment_status)->toBe('paid')
        ->and((float) $repair->fresh()->balance_due)->toBe(0.0);
});

test('customers cannot access another customers repair conversation', function () {
    $owner = negotiationUser('owner-conversation@example.com');
    $other = negotiationUser('other-conversation@example.com');
    $repair = negotiationRepair($owner);

    $this->actingAs($other)
        ->get(route('repair-conversations.show-for-repair', $repair))
        ->assertForbidden();
});

test('admin repair proposal part search uses eligible parts scope and customer facing price', function () {
    $admin = negotiationUser('search-admin@example.com', 'admin');
    $customerUser = negotiationUser('search-customer@example.com');
    $repair = negotiationRepair($customerUser);
    $part = negotiationPart([
        'sku' => 'SEARCH-SKU-100',
        'new_sku' => 'SEARCH-NEW-100',
        'name' => 'Searchable OLED Assembly',
        'model' => 'MODEL-NUMBER-100',
        'model_compatibility' => 'iPhone Search Model',
        'image_url' => CatalogImage::MOBILESENTRIX_PLACEHOLDER,
        'price' => 100,
        'api_price' => 100,
        'cost_price' => 100,
    ]);
    negotiationPart([
        'id' => 499001,
        'sku' => 'INACTIVE-SKU',
        'name' => 'Inactive Searchable Part',
        'is_active' => false,
        'status' => 'inactive',
    ]);
    negotiationPart([
        'id' => 499002,
        'sku' => 'EXCLUDED-SKU',
        'name' => 'Excluded Searchable Part',
        'category_id' => 8363,
        'category_name' => 'Excluded Accessories',
    ]);
    EcliseMarkup::query()->create([
        'item_type' => EcliseMarkup::ITEM_TYPE_PARTS,
        'scope_type' => EcliseMarkup::SCOPE_ALL,
        'markup_type' => EcliseMarkup::MARKUP_PERCENTAGE,
        'markup_value' => 10,
        'priority' => 0,
        'is_active' => true,
    ]);

    foreach (['SEARCH-SKU-100', 'SEARCH-SKU', 'Searchable OLED', 'iPhone Search Model', 'MODEL-NUMBER-100'] as $term) {
        $response = $this->actingAs($admin)
            ->getJson(route('admin.repairs.conversation.part-search', [$repair, 'q' => $term]));

        $response->assertOk()
            ->assertJsonPath('results.0.id', $part->id)
            ->assertJsonPath('results.0.price', '110.00');
    }

    $response = $this->actingAs($admin)
        ->getJson(route('admin.repairs.conversation.part-search', [$repair, 'q' => 'SEARCH']));

    $skus = collect($response->json('results'))->pluck('sku');

    expect($skus)->toContain('SEARCH-SKU-100')
        ->not->toContain('INACTIVE-SKU')
        ->not->toContain('EXCLUDED-SKU')
        ->and($response->json('results.0.image_url'))->toContain('eclise-thumb-grey.png');

    $this->actingAs($customerUser)
        ->getJson(route('admin.repairs.conversation.part-search', [$repair, 'q' => 'SEARCH-SKU-100']))
        ->assertForbidden();
});

test('admin adds searched part as proposal option using server generated snapshots', function () {
    $admin = negotiationUser('searched-option-admin@example.com', 'admin');
    $customerUser = negotiationUser('searched-option-customer@example.com');
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Display'], $admin);
    $part = negotiationPart([
        'sku' => 'SERVER-SKU',
        'name' => 'Server Snapshot Part',
        'model_compatibility' => 'Server Model',
        'device_model_text' => 'Server Model',
        'model_text' => ['Server Model'],
        'price' => 140,
        'api_price' => 140,
        'cost_price' => 140,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.part-options.store', [$repair, $group]), [
            'part_id' => $part->id,
            'sku_snapshot' => 'FAKE-SKU',
            'name_snapshot' => 'Fake Browser Name',
            'price_snapshot' => 1,
            'quality_label' => 'Fake quality',
            'is_primary' => 1,
        ])
        ->assertRedirect(route('admin.repairs.show', $repair));

    $systemOption = $group->options()->firstOrFail();
    $option = $group->options()->where('source_id', $part->id)->firstOrFail();

    expect($systemOption->isCustomerSuppliedOption())->toBeTrue()
        ->and((float) $systemOption->price_snapshot)->toBe(0.0)
        ->and($option->source_id)->toBe($part->id)
        ->and($option->sku_snapshot)->toBe('SERVER-SKU')
        ->and($option->name_snapshot)->toBe('Server Snapshot Part')
        ->and($option->model_snapshot)->toBe('Server Model')
        ->and((float) $option->price_snapshot)->toBe(140.0)
        ->and($option->quality_label)->toBeNull()
        ->and($option->is_primary)->toBeTrue();

    $part->update(['price' => 10, 'api_price' => 10, 'cost_price' => 10, 'name' => 'Changed Later']);

    expect((float) $option->fresh()->price_snapshot)->toBe(140.0)
        ->and($option->fresh()->name_snapshot)->toBe('Server Snapshot Part');
});

test('duplicate parts and manual proposal option payloads are rejected', function () {
    $admin = negotiationUser('duplicate-option-admin@example.com', 'admin');
    $customerUser = negotiationUser('duplicate-option-customer@example.com');
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Battery'], $admin);
    $part = negotiationPart(['sku' => 'DUPLICATE-SKU']);

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.part-options.store', [$repair, $group]), [
            'name_snapshot' => 'Manual Name',
            'sku_snapshot' => 'MANUAL-SKU',
            'price_snapshot' => 45,
        ])
        ->assertSessionHasErrors('part_id');

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.part-options.store', [$repair, $group]), [
            'part_id' => $part->id,
        ])
        ->assertRedirect(route('admin.repairs.show', $repair));

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.part-options.store', [$repair, $group]), [
            'part_id' => $part->id,
        ])
        ->assertSessionHasErrors('part_id');
});

test('accepted proposal cannot receive new part options', function () {
    $admin = negotiationUser('locked-option-admin@example.com', 'admin');
    $customerUser = negotiationUser('locked-option-customer@example.com');
    $customer = Customer::forUser($customerUser);
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Display'], $admin);
    $part = negotiationPart(['sku' => 'LOCKED-1']);
    $secondPart = negotiationPart(['id' => 399998, 'sku' => 'LOCKED-2']);
    $option = $service->addPartOption($group, ['part_id' => $part->id], $admin);
    $conversation = $service->sendProposal($conversation->fresh(), $admin);
    $service->selectOption($conversation->fresh(), $customer, $option->id);
    $service->acceptProposal($conversation->fresh(), $customer);

    $this->actingAs($admin)
        ->post(route('admin.repairs.conversation.part-options.store', [$repair, $group]), [
            'part_id' => $secondPart->id,
        ])
        ->assertSessionHasErrors('part_id');
});

test('removing a normal part option hides it from active proposal views and clears selection', function () {
    $admin = negotiationUser('remove-option-admin@example.com', 'admin');
    $customerUser = negotiationUser('remove-option-customer@example.com');
    $customer = Customer::forUser($customerUser);
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Display'], $admin);
    $part = negotiationPart([
        'sku' => 'REMOVE-SKU',
        'name' => 'Remove Me Proposal Part',
    ]);
    $option = $service->addPartOption($group, ['part_id' => $part->id], $admin);
    $conversation = $service->sendProposal($conversation->fresh(), $admin);
    $service->selectOption($conversation->fresh(), $customer, $option->id);

    $this->actingAs($admin)
        ->delete(route('admin.repairs.conversation.part-options.destroy', [$repair, $option]))
        ->assertRedirect(route('admin.repairs.show', $repair));

    expect($option->fresh()->is_active)->toBeFalse()
        ->and($group->selections()->where('customer_id', $customer->id)->exists())->toBeFalse()
        ->and((float) $conversation->fresh()->selected_parts_subtotal)->toBe(0.0);

    $this->actingAs($admin)
        ->get(route('admin.repairs.show', $repair))
        ->assertOk()
        ->assertDontSee('Remove Me Proposal Part')
        ->assertDontSee('REMOVE-SKU')
        ->assertDontSee('Removed');

    $this->actingAs($customerUser)
        ->get(route('repair-conversations.show', $conversation))
        ->assertOk()
        ->assertSee(RepairPartOption::CUSTOMER_SUPPLIED_LABEL)
        ->assertDontSee('Remove Me Proposal Part')
        ->assertDontSee('REMOVE-SKU')
        ->assertDontSee('Removed');
});

test('proposal option names use truncation classes and full title attributes', function () {
    $admin = negotiationUser('layout-option-admin@example.com', 'admin');
    $customerUser = negotiationUser('layout-option-customer@example.com');
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Panel'], $admin);
    $longName = '14 Inch LCD Panel 1920x1080 30 Pin IPS FHD For Lenovo HP Dell Acer ASUS Toast POS Extended Proposal Name';
    $part = negotiationPart([
        'sku' => 'LONG-NAME-SKU',
        'name' => $longName,
    ]);
    $service->addPartOption($group, ['part_id' => $part->id], $admin);
    $service->sendProposal($conversation->fresh(), $admin);

    $this->actingAs($admin)
        ->get(route('admin.repairs.show', $repair))
        ->assertOk()
        ->assertSee('repair-part-option-name', false)
        ->assertSee('repair-part-option-meta', false)
        ->assertSee('repair-part-option-actions', false)
        ->assertSee('title="'.e($longName).'"', false);

    $this->actingAs($customerUser)
        ->get(route('repair-conversations.show', $conversation))
        ->assertOk()
        ->assertSee('repair-part-option-name', false)
        ->assertSee('repair-part-option-meta', false)
        ->assertSee('title="'.e($longName).'"', false);
});

test('manual option controls are removed and customer only sees proposal choices', function () {
    $admin = negotiationUser('manual-fields-admin@example.com', 'admin');
    $customerUser = negotiationUser('manual-fields-customer@example.com');
    $repair = negotiationRepair($customerUser);
    $service = app(RepairNegotiationService::class);
    $conversation = $service->conversationForRepair($repair);
    $group = $service->addPartGroup($conversation, ['title' => 'Display'], $admin);
    $part = negotiationPart([
        'sku' => 'VISIBLE-SKU',
        'name' => 'Visible Proposal Part',
    ]);
    $service->addPartOption($group, ['part_id' => $part->id], $admin);
    $service->sendProposal($conversation->fresh(), $admin);

    $this->actingAs($admin)
        ->get(route('admin.repairs.show', $repair))
        ->assertOk()
        ->assertSee('Search by SKU, part name, model, or model number')
        ->assertDontSee('Manual option')
        ->assertDontSee('Manual SKU')
        ->assertDontSee('Quality label')
        ->assertDontSee('Manual price')
        ->assertDontSee('Customer must choose one option in this group')
        ->assertSee(RepairPartOption::CUSTOMER_SUPPLIED_LABEL);

    $this->actingAs($customerUser)
        ->get(route('repair-conversations.show', $conversation))
        ->assertOk()
        ->assertSee('type="radio"', false)
        ->assertSee(RepairPartOption::CUSTOMER_SUPPLIED_LABEL)
        ->assertSee('Visible Proposal Part')
        ->assertSee('VISIBLE-SKU')
        ->assertDontSee('Search by SKU, part name, model, or model number')
        ->assertDontSee('Manual option')
        ->assertDontSee('Quality label');
});
