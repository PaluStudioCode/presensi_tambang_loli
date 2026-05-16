<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('q', ''));

        $employees = User::query()
            ->where('role', 'Employee')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('id_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Employees', [
            'filters' => [
                'q' => $search,
            ],
            'summary' => [
                'totalEmployees' => User::query()->where('role', 'Employee')->count(),
                'totalAdmins' => User::query()->where('role', 'Admin')->count(),
            ],
            'employees' => $employees->through(function (User $employee): array {
                return [
                    'id' => $employee->id,
                    'id_number' => $employee->id_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department,
                    'address' => $employee->address,
                    'email' => $employee->email,
                    'created_at' => optional($employee->created_at)->toDateTimeString(),
                ];
            }),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_number' => ['required', 'string', 'max:50', 'unique:users,id_number'],
            'full_name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::query()->create([
            'id_number' => $validated['id_number'],
            'full_name' => $validated['full_name'],
            'department' => $validated['department'] ?? null,
            'address' => $validated['address'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'Employee',
        ]);

        return back()->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);

        $validated = $request->validate([
            'id_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'id_number')->ignore($employee->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->id),
            ],
        ]);

        $payload = [
            'id_number' => $validated['id_number'],
            'full_name' => $validated['full_name'],
            'department' => $validated['department'] ?? null,
            'address' => $validated['address'] ?? null,
            'email' => $validated['email'],
        ];

        $employee->update($payload);

        return back()->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);

        $employee->delete();

        return back()->with('success', 'Karyawan berhasil dihapus.');
    }

    private function ensureEmployee(User $user): void
    {
        abort_if($user->role !== 'Employee', 404);
    }
}
