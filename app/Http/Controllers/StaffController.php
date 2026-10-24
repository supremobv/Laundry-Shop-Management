<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordBytes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index()
    {
        return view('staff.index', ['staff' => User::orderBy('name')->paginate(15)]);
    }

    public function create()
    {
        return view('staff.form', ['member' => new User(['role' => 'cashier', 'active' => true])]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['must_change_password'] = true;
        DB::transaction(function () use ($data) {
            $u = User::create($data);
            AuditLog::record('staff.created', (string) $u->id, ['role' => $u->role]);
        });

        return redirect()->route('staff.index')->with('success', 'Staff account created. Share the temporary password privately; they must change it at first login.');
    }

    public function edit(User $staff)
    {
        return view('staff.form', ['member' => $staff]);
    }

    public function update(Request $r, User $staff)
    {
        $data = $this->validated($r, $staff);
        DB::transaction(function () use ($data, $staff, $r) {
            $admins = User::where('role', 'admin')->where('active', true)->orderBy('id')->lockForUpdate()->get();
            if ($staff->id === $r->user()->id && (! $data['active'] || $data['role'] !== 'admin')) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own administrator access. Ask another administrator.']);
            }
            if ($staff->role === 'admin' && $staff->active && (! $data['active'] || $data['role'] !== 'admin') && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one active administrator is required.']);
            }
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['must_change_password'] = true;
            }
            $staff->update($data);
            DB::table('sessions')->where('user_id', $staff->id)->delete();
            AuditLog::record('staff.updated', (string) $staff->id, ['role' => $staff->role, 'active' => $staff->active]);
        });

        return redirect()->route('staff.index')->with('success', 'Staff account updated. Previous sessions have been revoked.');
    }

    private function validated(Request $r, ?User $staff = null): array
    {
        return $r->validate(['name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($staff?->id)], 'role' => ['required', Rule::in(array_keys(User::PERMISSIONS))], 'active' => 'required|boolean', 'password' => [$staff ? 'nullable' : 'required', 'string', 'confirmed', new PasswordBytes, Password::min(12)->mixedCase()->numbers()->symbols()]]);
    }

    public function audit()
    {
        return view('staff.audit', ['logs' => AuditLog::with('user')->latest('id')->paginate(30)]);
    }
}
