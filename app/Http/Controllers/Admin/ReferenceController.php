<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\ProductBrand;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
use App\Models\ProductNetwork;
use App\Models\ProductSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReferenceController extends Controller
{
    private const REFERENCES = [
        'product-brands' => [
            'model' => ProductBrand::class,
            'title' => 'Product Brands',
            'singular' => 'Product Brand',
            'route' => 'admin.product-brands',
            'table' => 'product_brands',
            'code_source' => true,
        ],
        'product-models' => [
            'model' => ProductModel::class,
            'title' => 'Product Models',
            'singular' => 'Product Model',
            'route' => 'admin.product-models',
            'table' => 'product_models',
            'code_source' => true,
            'product_brand' => true,
        ],
        'product-sizes' => [
            'model' => ProductSize::class,
            'title' => 'Product Sizes',
            'singular' => 'Product Size',
            'route' => 'admin.product-sizes',
            'table' => 'product_sizes',
            'uses_status' => false,
            'uses_type' => true,
        ],
        'product-grades' => [
            'model' => ProductGrade::class,
            'title' => 'Product Grades',
            'singular' => 'Product Grade',
            'route' => 'admin.product-grades',
            'table' => 'product_grades',
            'code_source' => true,
        ],
        'product-conditions' => [
            'model' => ProductCondition::class,
            'title' => 'Product Conditions',
            'singular' => 'Product Condition',
            'route' => 'admin.product-conditions',
            'table' => 'product_conditions',
            'code_source' => true,
        ],
        'product-colors' => [
            'model' => ProductColor::class,
            'title' => 'Product Colors',
            'singular' => 'Product Color',
            'route' => 'admin.product-colors',
            'table' => 'product_colors',
            'code_source' => true,
        ],
        'product-networks' => [
            'model' => ProductNetwork::class,
            'title' => 'Product Networks',
            'singular' => 'Product Network',
            'route' => 'admin.product-networks',
            'table' => 'product_networks',
            'code_source' => true,
        ],
        'device-types' => [
            'model' => DeviceType::class,
            'title' => 'Device Types',
            'singular' => 'Device Type',
            'route' => 'admin.device-types',
            'table' => 'repair_device_types',
        ],
        'issue-categories' => [
            'model' => IssueCategory::class,
            'title' => 'Issue Categories',
            'singular' => 'Issue Category',
            'route' => 'admin.issue-categories',
            'table' => 'issue_categories',
        ],
    ];

    public function index(Request $request, string $reference)
    {
        $config = $this->config($reference);
        $model = $config['model'];
        $items = $model::query()
            ->when($config['product_brand'] ?? false, fn ($query) => $query->with('brand'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.taxonomies.index', [
            'title' => $config['title'],
            'items' => $items,
            'routePrefix' => $config['route'],
            'usesStatus' => (bool) ($config['uses_status'] ?? true),
            'usesCodeSource' => (bool) ($config['code_source'] ?? false),
            'usesType' => (bool) ($config['uses_type'] ?? false),
            'usesProductBrand' => (bool) ($config['product_brand'] ?? false),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(string $reference)
    {
        $config = $this->config($reference);
        $model = $config['model'];
        $defaults = ['status' => 'active', 'is_active' => true, 'sort_order' => 0];

        if ($config['uses_type'] ?? false) {
            $defaults['type'] = 'storage';
        }

        return view('admin.taxonomies.form', [
            'title' => 'Add '.$config['singular'],
            'item' => new $model($defaults),
            'routePrefix' => $config['route'],
            'usesStatus' => (bool) ($config['uses_status'] ?? true),
            'usesCodeSource' => (bool) ($config['code_source'] ?? false),
            'usesType' => (bool) ($config['uses_type'] ?? false),
            'usesProductBrand' => (bool) ($config['product_brand'] ?? false),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $reference)
    {
        $config = $this->config($reference);
        $model = $config['model'];

        $model::query()->create($this->validatedData($request, $config));

        return redirect()->route($config['route'].'.index')->with('status', $config['singular'].' created.');
    }

    public function edit(string $reference, int $id)
    {
        $config = $this->config($reference);
        $item = $this->findModel($config, $id);

        return view('admin.taxonomies.form', [
            'title' => 'Edit '.$config['singular'],
            'item' => $item,
            'routePrefix' => $config['route'],
            'usesStatus' => (bool) ($config['uses_status'] ?? true),
            'usesCodeSource' => (bool) ($config['code_source'] ?? false),
            'usesType' => (bool) ($config['uses_type'] ?? false),
            'usesProductBrand' => (bool) ($config['product_brand'] ?? false),
            'productBrands' => ProductBrand::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, string $reference, int $id)
    {
        $config = $this->config($reference);
        $item = $this->findModel($config, $id);
        $item->update($this->validatedData($request, $config, $item->id));

        return redirect()->route($config['route'].'.edit', $item)->with('status', $config['singular'].' updated.');
    }

    public function destroy(string $reference, int $id)
    {
        $config = $this->config($reference);
        $this->findModel($config, $id)->delete();

        return redirect()->route($config['route'].'.index')->with('status', $config['singular'].' deleted.');
    }

    private function config(string $reference): array
    {
        abort_unless(isset(self::REFERENCES[$reference]), 404);

        return self::REFERENCES[$reference];
    }

    private function findModel(array $config, int $id): Model
    {
        return $config['model']::query()->findOrFail($id);
    }

    private function validatedData(Request $request, array $config, ?int $ignoreId = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];

        if (($config['uses_status'] ?? true) === true) {
            $rules['status'] = ['required', Rule::in(['active', 'inactive'])];
        } else {
            $rules['is_active'] = ['nullable', 'boolean'];
        }

        if ($config['code_source'] ?? false) {
            $rules['code'] = ['nullable', 'string', 'max:255'];
            $rules['source'] = ['nullable', 'string', 'max:255'];
        }

        if ($config['uses_type'] ?? false) {
            $rules['type'] = ['required', 'string', 'max:255'];
        }

        if ($config['product_brand'] ?? false) {
            $rules['product_brand_id'] = ['required', 'exists:product_brands,id'];
        }

        $data = $request->validate($rules);

        $slug = Str::slug($data['name']);
        $data['slug'] = $this->uniqueSlug($config['table'], $slug, $ignoreId);

        if (($config['uses_status'] ?? true) === false) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    private function uniqueSlug(string $table, string $base, ?int $ignoreId = null): string
    {
        $slug = $base ?: 'item';
        $candidate = $slug;
        $counter = 2;

        while (\DB::table($table)
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = "{$slug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
