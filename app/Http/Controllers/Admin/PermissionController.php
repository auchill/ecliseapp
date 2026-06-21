<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissions = Permission::query()
            ->withCount('users')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.permissions.index', [
            'permissions' => $permissions,
            'statuses' => Permission::STATUSES,
        ]);
    }

    public function create()
    {
        return view('admin.permissions.form', [
            'permission' => new Permission(['status' => 'active']),
            'statuses' => Permission::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        Permission::query()->create($this->validatedData($request));

        return redirect()->route('admin.permissions.index')->with('status', 'Permission created.');
    }

    public function edit(Permission $permission)
    {
        return view('admin.permissions.form', [
            'permission' => $permission,
            'statuses' => Permission::STATUSES,
        ]);
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $this->validatedData($request, $permission);

        if (in_array($permission->name, ['admin', 'customer'], true)) {
            $data['name'] = $permission->name;
        }

        if ($permission->name === 'admin' && $data['status'] !== 'active' && $permission->users()->where('status', 'active')->exists()) {
            return back()
                ->withErrors(['status' => 'The admin permission cannot be disabled while active admin users are assigned to it.'])
                ->withInput();
        }

        $permission->update($data);

        return redirect()->route('admin.permissions.edit', $permission)->with('status', 'Permission updated.');
    }

    public function destroy(Permission $permission)
    {
        if (in_array($permission->name, ['admin', 'customer'], true)) {
            return back()->withErrors(['permission' => 'Default permissions cannot be deleted.']);
        }

        if ($permission->users()->exists()) {
            return back()->withErrors(['permission' => 'Reassign users before deleting this permission.']);
        }

        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('status', 'Permission deleted.');
    }

    private function validatedData(Request $request, ?Permission $permission = null): array
    {
        $request->merge([
            'name' => Str::slug(Str::lower((string) $request->input('name')), '_'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('permissions', 'name')->ignore($permission?->id)],
            'status' => ['required', Rule::in(array_keys(Permission::STATUSES))],
        ]);

        return $data;
    }
}
