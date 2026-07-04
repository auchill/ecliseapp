<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceBrand;
use App\Models\DeviceCarrier;
use App\Models\DeviceColor;
use App\Models\DeviceCondition;
use App\Models\DeviceGrade;
use App\Models\DeviceManufacturer;
use App\Models\DeviceModel;
use App\Models\DeviceSize;
use App\Models\DeviceType;
use App\Models\IssueCategory;
use App\Models\PartModel;
use App\Models\ProductModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReferenceController extends Controller
{
    private const REFERENCES = [
        'product-models' => [
            'model' => ProductModel::class,
            'title' => 'Product Models',
            'singular' => 'Product Model',
            'route' => 'admin.product-models',
            'table' => 'product_models',
        ],
        'part-models' => [
            'model' => PartModel::class,
            'title' => 'Parts Models',
            'singular' => 'Parts Model',
            'route' => 'admin.part-models',
            'table' => 'part_models',
        ],
        'device-types' => [
            'model' => DeviceType::class,
            'title' => 'Device Types',
            'singular' => 'Device Type',
            'route' => 'admin.device-types',
            'table' => 'repair_device_types',
        ],
        'device-brands' => [
            'model' => DeviceBrand::class,
            'title' => 'Device Brands',
            'singular' => 'Device Brand',
            'route' => 'admin.device-brands',
            'table' => 'device_brands',
        ],
        'device-models' => [
            'model' => DeviceModel::class,
            'title' => 'Device Models',
            'singular' => 'Device Model',
            'route' => 'admin.device-models',
            'table' => 'device_models',
        ],
        'device-manufacturers' => [
            'model' => DeviceManufacturer::class,
            'title' => 'Device Manufacturers',
            'singular' => 'Device Manufacturer',
            'route' => 'admin.device-manufacturers',
            'table' => 'device_manufacturers',
        ],
        'device-colors' => [
            'model' => DeviceColor::class,
            'title' => 'Device Colors',
            'singular' => 'Device Color',
            'route' => 'admin.device-colors',
            'table' => 'device_colors',
        ],
        'device-conditions' => [
            'model' => DeviceCondition::class,
            'title' => 'Device Conditions',
            'singular' => 'Device Condition',
            'route' => 'admin.device-conditions',
            'table' => 'device_conditions',
        ],
        'device-carriers' => [
            'model' => DeviceCarrier::class,
            'title' => 'Device Carriers',
            'singular' => 'Device Carrier',
            'route' => 'admin.device-carriers',
            'table' => 'device_carriers',
        ],
        'device-sizes' => [
            'model' => DeviceSize::class,
            'title' => 'Device Sizes',
            'singular' => 'Device Size',
            'route' => 'admin.device-sizes',
            'table' => 'device_sizes',
        ],
        'device-grades' => [
            'model' => DeviceGrade::class,
            'title' => 'Device Grades',
            'singular' => 'Device Grade',
            'route' => 'admin.device-grades',
            'table' => 'device_grades',
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique($config['table'], 'slug')->ignore($ignoreId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        return $data;
    }
}
