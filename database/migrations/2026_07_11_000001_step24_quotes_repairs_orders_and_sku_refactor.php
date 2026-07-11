<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->addProductCategoryCodes();
        $this->createSequenceTables();
        $this->renameRepairTable();
        $this->refactorRepairStatusUpdates();
        $this->refactorQuotes();
        $this->refactorRepairs();
        $this->createShippingSnapshots();
        $this->refactorOrders();
        $this->refactorPayments();
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_status_updates') && Schema::hasColumn('repair_status_updates', 'repair_id')) {
            $this->dropForeignIfSupported('repair_status_updates', 'repair_status_updates_repair_id_foreign');
            Schema::table('repair_status_updates', function (Blueprint $table): void {
                $table->renameColumn('repair_id', 'repair_booking_id');
            });
        }

        if (Schema::hasTable('repairs') && ! Schema::hasTable('repair_bookings')) {
            Schema::rename('repairs', 'repair_bookings');
        }

        Schema::dropIfExists('repair_shipping');
        Schema::dropIfExists('order_shipping');
        Schema::dropIfExists('repair_number_sequences');
        Schema::dropIfExists('product_sku_sequences');
    }

    private function addProductCategoryCodes(): void
    {
        if (! Schema::hasTable('product_categories')) {
            return;
        }

        if (! Schema::hasColumn('product_categories', 'code')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                $table->string('code', 3)->nullable()->after('slug')->unique();
            });
        }

        DB::table('product_categories')
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->each(function ($category): void {
                $code = $this->categoryCodeForName($category->name);

                if (! $code || filled($category->code)) {
                    return;
                }

                if (DB::table('product_categories')->where('code', $code)->where('id', '!=', $category->id)->exists()) {
                    return;
                }

                DB::table('product_categories')->where('id', $category->id)->update([
                    'code' => $code,
                    'updated_at' => now(),
                ]);
            });
    }

    private function createSequenceTables(): void
    {
        if (! Schema::hasTable('product_sku_sequences')) {
            Schema::create('product_sku_sequences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }

        if (! DB::table('product_sku_sequences')->where('id', 1)->exists()) {
            DB::table('product_sku_sequences')->insert([
                'id' => 1,
                'last_sequence' => $this->maxExistingProductSkuSequence(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('repair_number_sequences')) {
            Schema::create('repair_number_sequences', function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->unsignedBigInteger('last_sequence')->default(0);
                $table->timestamps();
            });
        }
    }

    private function renameRepairTable(): void
    {
        if (Schema::hasTable('repair_bookings') && ! Schema::hasTable('repairs')) {
            Schema::rename('repair_bookings', 'repairs');
        }
    }

    private function refactorRepairStatusUpdates(): void
    {
        if (! Schema::hasTable('repair_status_updates') || ! Schema::hasColumn('repair_status_updates', 'repair_booking_id')) {
            return;
        }

        $this->dropForeignIfSupported('repair_status_updates', 'repair_status_updates_repair_booking_id_foreign');

        Schema::table('repair_status_updates', function (Blueprint $table): void {
            $table->renameColumn('repair_booking_id', 'repair_id');
        });

        $this->addForeignIfSupported('repair_status_updates', 'repair_id', 'repairs');
    }

    private function refactorQuotes(): void
    {
        if (! Schema::hasTable('quotes')) {
            return;
        }

        Schema::table('quotes', function (Blueprint $table): void {
            if (! Schema::hasColumn('quotes', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->restrictOnDelete();
            }

            if (! Schema::hasColumn('quotes', 'converted_to_repair')) {
                $table->boolean('converted_to_repair')->default(false)->after('converted_to_booking');
            }
        });

        DB::table('quotes')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get()
            ->each(function ($quote): void {
                $customerId = $this->customerIdForLegacyIdentity($quote->email ?? null, $quote->customer_name ?? null, $quote->phone_number ?? null);

                if ($customerId) {
                    DB::table('quotes')->where('id', $quote->id)->update([
                        'customer_id' => $customerId,
                        'converted_to_repair' => (bool) ($quote->converted_to_booking ?? false),
                    ]);
                }
            });

        DB::table('quotes')
            ->where('status', 'converted_to_booking')
            ->update([
                'status' => 'converted_to_repair',
                'converted_to_repair' => true,
            ]);
    }

    private function refactorRepairs(): void
    {
        if (! Schema::hasTable('repairs')) {
            return;
        }

        Schema::table('repairs', function (Blueprint $table): void {
            if (! Schema::hasColumn('repairs', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->restrictOnDelete();
            }
        });

        if (! Schema::hasColumn('repairs', 'repair_number')) {
            Schema::table('repairs', function (Blueprint $table): void {
                $table->string('repair_number')->nullable()->after('customer_id');
            });
        }

        DB::table('repairs')
            ->orderBy('id')
            ->get()
            ->each(function ($repair): void {
                $customerId = $repair->customer_id
                    ?? $this->customerIdForUserId($repair->user_id ?? null)
                    ?? $this->customerIdForLegacyIdentity($repair->email ?? null, $repair->customer_name ?? null, $repair->phone ?? null);
                $repairNumber = filled($repair->repair_number ?? null)
                    ? $this->normalizeRepairNumber((string) $repair->repair_number, $repair->created_at)
                    : $this->nextBackfillRepairNumber($repair->created_at);

                DB::table('repairs')->where('id', $repair->id)->update([
                    'customer_id' => $customerId,
                    'repair_number' => $repairNumber,
                ]);
            });

        if (! $this->indexExists('repairs', 'repairs_repair_number_unique')) {
            Schema::table('repairs', function (Blueprint $table): void {
                $table->unique('repair_number');
            });
        }
    }

    private function createShippingSnapshots(): void
    {
        if (! Schema::hasTable('repair_shipping')) {
            Schema::create('repair_shipping', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('repair_id')->unique()->constrained('repairs')->cascadeOnDelete();
                $table->text('shipping_address');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_shipping')) {
            Schema::create('order_shipping', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
                $table->text('shipping_address');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('repairs')) {
            DB::table('repairs')
                ->where('fulfillment_method', 'shipping')
                ->orderBy('id')
                ->get()
                ->each(function ($repair): void {
                    $address = $this->formatAddress([
                        'full_name' => $repair->shipping_full_name ?? $repair->customer_name ?? null,
                        'address_line1' => $repair->shipping_address_line1 ?? null,
                        'address_line2' => $repair->shipping_address_line2 ?? null,
                        'city' => $repair->shipping_city ?? null,
                        'province' => $repair->shipping_province ?? null,
                        'postal_code' => $repair->shipping_postal_code ?? null,
                        'country' => $repair->shipping_country ?? null,
                        'email' => $repair->shipping_email ?? $repair->email ?? null,
                        'phone' => $repair->shipping_phone ?? $repair->phone ?? null,
                    ]);

                    if ($address !== '') {
                        DB::table('repair_shipping')->updateOrInsert(
                            ['repair_id' => $repair->id],
                            ['shipping_address' => $address, 'created_at' => now(), 'updated_at' => now()],
                        );
                    }
                });
        }

        DB::table('orders')
            ->where('fulfillment_method', 'shipping')
            ->orderBy('id')
            ->get()
            ->each(function ($order): void {
                $address = $this->formatAddress([
                    'full_name' => $order->shipping_full_name ?? $order->customer_name ?? null,
                    'address_line1' => $order->shipping_address_line1 ?? null,
                    'address_line2' => $order->shipping_address_line2 ?? null,
                    'city' => $order->shipping_city ?? null,
                    'province' => $order->shipping_province ?? null,
                    'postal_code' => $order->shipping_postal_code ?? null,
                    'country' => $order->shipping_country ?? null,
                    'email' => $order->shipping_email ?? $order->email ?? null,
                    'phone' => $order->shipping_phone ?? $order->phone ?? null,
                ]);

                if ($address !== '') {
                    DB::table('order_shipping')->updateOrInsert(
                        ['order_id' => $order->id],
                        ['shipping_address' => $address, 'created_at' => now(), 'updated_at' => now()],
                    );
                }
            });
    }

    private function refactorOrders(): void
    {
        if (! Schema::hasColumn('orders', 'customer_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->restrictOnDelete();
            });
        }

        DB::table('orders')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get()
            ->each(function ($order): void {
                $customerId = $this->customerIdForLegacyIdentity($order->email ?? null, $order->customer_name ?? null, $order->phone ?? null);

                if ($customerId) {
                    DB::table('orders')->where('id', $order->id)->update(['customer_id' => $customerId]);
                }
            });
    }

    private function refactorPayments(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! Schema::hasColumn('payments', 'repair_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('repair_id')->nullable()->after('order_id')->constrained('repairs')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('payments', 'repair_order_id')) {
            DB::table('payments')
                ->whereNull('repair_id')
                ->whereNotNull('repair_order_id')
                ->update(['repair_id' => DB::raw('repair_order_id')]);
        }

        DB::table('payments')
            ->where('payable_type', 'App\\Models\\RepairBooking')
            ->update(['payable_type' => 'App\\Models\\Repair']);
    }

    private function customerIdForUserId(mixed $userId): ?int
    {
        return $userId ? DB::table('customers')->where('user_id', $userId)->value('id') : null;
    }

    private function customerIdForLegacyIdentity(?string $email, ?string $name, ?string $phone): ?int
    {
        $email = Str::lower(trim((string) $email));

        if ($email !== '') {
            $matches = DB::table('customers')->whereRaw('LOWER(email) = ?', [$email])->pluck('id');

            if ($matches->count() === 1) {
                return (int) $matches->first();
            }

            $user = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user) {
                return $this->customerIdForUserId($user->id) ?: DB::table('customers')->insertGetId([
                    'user_id' => $user->id,
                    'full_name' => $name ?: $user->name,
                    'email' => $email ?: $user->email,
                    'phone' => $phone,
                    'customer_since' => $user->created_at ?: now(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return null;
    }

    private function normalizeRepairNumber(string $value, mixed $createdAt): string
    {
        if (preg_match('/^ECL-REP-\d{4}-\d{7}$/', $value)) {
            $this->seedRepairSequenceFromNumber($value);

            return $value;
        }

        return $this->nextBackfillRepairNumber($createdAt);
    }

    private function nextBackfillRepairNumber(mixed $createdAt): string
    {
        $year = (int) date('Y', strtotime((string) ($createdAt ?: now())));
        $row = DB::table('repair_number_sequences')->where('year', $year)->first();

        if (! $row) {
            DB::table('repair_number_sequences')->insert([
                'year' => $year,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $last = 0;
        } else {
            $last = (int) $row->last_sequence;
        }

        $next = $last + 1;
        DB::table('repair_number_sequences')->where('year', $year)->update([
            'last_sequence' => $next,
            'updated_at' => now(),
        ]);

        return sprintf('ECL-REP-%d-%07d', $year, $next);
    }

    private function seedRepairSequenceFromNumber(string $repairNumber): void
    {
        if (! preg_match('/^ECL-REP-(\d{4})-(\d{7})$/', $repairNumber, $matches)) {
            return;
        }

        $year = (int) $matches[1];
        $sequence = (int) $matches[2];
        $existing = DB::table('repair_number_sequences')->where('year', $year)->first();

        if ($existing) {
            if ((int) $existing->last_sequence < $sequence) {
                DB::table('repair_number_sequences')->where('year', $year)->update([
                    'last_sequence' => $sequence,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        DB::table('repair_number_sequences')->insert([
            'year' => $year,
            'last_sequence' => $sequence,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function maxExistingProductSkuSequence(): int
    {
        return DB::table('products')
            ->where('sku', 'like', 'ECL-SHP-%')
            ->pluck('sku')
            ->map(function (string $sku): int {
                return preg_match('/^ECL-SHP-[A-Z]{3}-(\d{7})$/', $sku, $matches) ? (int) $matches[1] : 0;
            })
            ->max() ?: 0;
    }

    private function categoryCodeForName(?string $name): ?string
    {
        return [
            'phone' => 'PHN',
            'phones' => 'PHN',
            'tablet' => 'TAB',
            'tablets' => 'TAB',
            'laptop' => 'LAP',
            'laptops' => 'LAP',
            'desktop' => 'DES',
            'desktops' => 'DES',
            'watch' => 'WAT',
            'smart watch' => 'WAT',
            'game console' => 'GAM',
            'game consoles' => 'GAM',
            'accessory' => 'ACC',
            'accessories' => 'ACC',
            'other' => 'OTH',
            'parts' => 'OTH',
        ][Str::lower(trim((string) $name))] ?? null;
    }

    private function formatAddress(array $data): string
    {
        return collect([
            $data['full_name'] ?? null,
            $data['address_line1'] ?? null,
            $data['address_line2'] ?? null,
            trim(implode(', ', array_filter([
                $data['city'] ?? null,
                trim(implode(' ', array_filter([
                    $data['province'] ?? null,
                    $data['postal_code'] ?? null,
                ]))),
            ]))),
            $data['country'] ?? null,
            filled($data['email'] ?? null) ? 'Email: '.$data['email'] : null,
            filled($data['phone'] ?? null) ? 'Phone: '.$data['phone'] : null,
        ])->filter(fn ($line): bool => filled($line))->implode("\n");
    }

    private function dropForeignIfSupported(string $table, string $foreign): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $table): mixed => $table->dropForeign($foreign));
        } catch (Throwable) {
            //
        }
    }

    private function addForeignIfSupported(string $table, string $column, string $references): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($column, $references): void {
                $table->foreign($column)->references('id')->on($references)->cascadeOnDelete();
            });
        } catch (Throwable) {
            //
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return collect(DB::select("SHOW INDEXES FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
