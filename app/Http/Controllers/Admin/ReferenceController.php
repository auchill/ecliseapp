<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\ProductBrand;
use App\Models\ProductCarrier;
use App\Models\ProductColor;
use App\Models\ProductCondition;
use App\Models\ProductGrade;
use App\Models\ProductModel;
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
        ],
        'product-sizes' => [
            'model' => ProductSize::class,
            'title' => 'Product Sizes',
            'singular' => 'Product Size',
            'route' => 'admin.product-sizes',
            'table' => 'product_sizes',
            'code_source' => true,
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
            'table' => 'productconditions',
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
        'product-carriers' => [
            'model' => ProductCarrier::class,
            'title' => 'Product Carriers',
            'singular' => 'Product Carrier',
            'route' => 'admin.product-carriers',
            'table' => 'product_carriers',
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

        return view('admin.taxonomies.index', [
            'title' => $config['title'],
            'items' => $model::query()->orderBy('sort_order')->orderBy('name')->paginate(20),
            'routePrefix' => $config['route'],
            'usesStatus' => true,
            'usesCodeSource' => (bool) ($config['code_source'] ?? false),
        ]);
    }

    public function create(string $reference)
    {
        $config = $this->config($reference);
        $model = $config['model'];

        return view('admin.taxonomies.form', [
            'title' => 'Add '.$config['singular'],
            'item' => new $model(['status' => 'active', 'sort_order' => 0]),
            'routePrefix' => $config['route'],
            'usesStatus' => true,
            'usesCodeSource' => (bool) ($config['code_source'] ?? false),
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
            'usesStatus' => true,
            'usesCodeSource' => (bool) ($config['code_source'] ?? false),
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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($config['table'], 'slug')->ignore($ignoreId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];

        if ($config['code_source'] ?? false) {
            $rules['code'] = ['nullable', 'string', 'max:255'];
            $rules['source'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        return $data;
    }
}
