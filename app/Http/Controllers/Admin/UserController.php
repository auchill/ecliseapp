<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('permission')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = $request->string('q');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('permission_id'), fn ($query) => $query->where('permission_id', $request->integer('permission_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'permissions' => Permission::query()->orderBy('name')->get(),
            'statuses' => Permission::STATUSES,
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(['status' => 'active']),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'statuses' => Permission::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $permission = Permission::query()->findOrFail($data['permission_id']);
        $data['role'] = $permission->name;

        User::query()->create($data);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user->load('permission'),
            'permissions' => Permission::query()->orderBy('name')->get(),
            'statuses' => Permission::STATUSES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedData($request, $user);
        $permission = Permission::query()->findOrFail($data['permission_id']);
        $data['role'] = $permission->name;

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.edit', $user)->with('status', 'User updated.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $passwordRules = $user
            ? ['nullable', 'confirmed', Password::defaults()]
            : ['required', 'confirmed', Password::defaults()];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => $passwordRules,
            'permission_id' => ['required', 'integer', Rule::exists('permissions', 'id')],
            'status' => ['required', Rule::in(array_keys(Permission::STATUSES))],
        ]);
    }
}
