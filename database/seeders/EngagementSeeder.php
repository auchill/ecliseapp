<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\ProductBrand;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Pre-sales touchpoints: quote requests and contact enquiries.
 */
class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $deviceTypeId = DeviceType::query()->value('id');
        $issueId = IssueCategory::query()->value('id');
        $brandId = ProductBrand::query()->value('id');

        foreach ([
            ['amara@example.com', 'iPhone 15 Pro', 'Screen flickers intermittently.', 'pending'],
            ['daniel@example.com', 'Galaxy S24 Ultra', 'Rear camera will not focus.', 'reviewed'],
            ['priya@example.com', 'MacBook Air M2', 'Keyboard keys unresponsive after spill.', 'pending'],
        ] as [$email, $model, $description, $status]) {
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                continue;
            }

            Quote::query()->create([
                'customer_id' => Customer::forUser($user)->id,
                'device_type_id' => $deviceTypeId,
                'product_brand_id' => $brandId,
                'device_model' => $model,
                'issue_category_id' => $issueId,
                'preferred_date' => now()->addDays(random_int(2, 9))->toDateString(),
                'preferred_time' => '14:00',
                'issue_description' => $description,
                'status' => $status,
                'converted_to_repair' => false,
            ]);
        }

        foreach ([
            ['Thomas Reed', 'thomas.reed@example.com', '905-555-0122', 'Repair', 'Do you repair water damaged tablets?', null],
            ['Chloe Martin', 'chloe.martin@example.com', '613-555-0166', 'Parts', 'Can you quote an iPhone 13 battery?', now()->subDays(2)],
            ['Sam Whitfield', 'sam.whitfield@example.com', '778-555-0190', 'Existing Order', 'When will my order ship?', null],
        ] as [$name, $email, $phone, $subject, $message, $readAt]) {
            ContactMessage::query()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject,
                'message' => $message,
                'read_at' => $readAt,
            ]);
        }
    }
}
