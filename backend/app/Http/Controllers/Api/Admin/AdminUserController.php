<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Berkas;
use App\Traits\ApiResponse;
use App\Traits\UploadGambar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    use ApiResponse, UploadGambar;

    // ─── LIST ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Akun owner hanya terlihat oleh developer, yang mengurus akunnya.
        $disembunyikan = $request->user()->hasRole('developer') ? ['developer'] : ['owner', 'developer'];

        $users = User::with('role.parent')
            ->whereHas('role', fn($q) => $q->whereNotIn('nama', $disembunyikan))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when($request->role_id, fn($q) => $q->where('role_id', $request->role_id))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($u) => $this->format($u));

        return $this->success($users);
    }

    // ─── SHOW ─────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $user = User::with('role.parent')->findOrFail($id);
        return $this->success($this->format($user));
    }

    // ─── STORE ────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:6|confirmed',
            'role_id'     => 'required|integer|exists:roles,id',
            'is_admin'    => 'boolean',
            'permissions' => 'nullable|array',
            'avatar'      => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_url'] = $this->uploadCompressed($request->file('avatar'), 'avatars');
        }

        unset($data['avatar']);
        $data['password_confirmation'] = null;

        $user = User::create([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'password'    => $data['password'],
            'role_id'     => $data['role_id'],
            'is_admin'    => $data['is_admin'] ?? false,
            'permissions' => $data['permissions'] ?? [],
            'avatar_url'  => $data['avatar_url'] ?? null,
        ]);

        return $this->success($this->format($user->load('role.parent')), 'Akun admin berhasil dibuat', 201);
    }

    // ─── UPDATE ───────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($id)],
            'role_id'     => 'required|integer|exists:roles,id',
            'is_admin'    => 'boolean',
            'permissions' => 'nullable|array',
            'avatar'      => 'nullable|image|max:2048',
        ]);

        if ($user->isOwner() && (int) $data['role_id'] !== $user->role_id) {
            return $this->error('Peran akun owner tidak dapat diubah.', 422);
        }

        if ($request->hasFile('avatar')) {
            Berkas::hapus($user->avatar_url);
            $data['avatar_url'] = $this->uploadCompressed($request->file('avatar'), 'avatars');
        }

        $user->update([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'role_id'     => $data['role_id'],
            'is_admin'    => $data['is_admin'] ?? $user->is_admin,
            'permissions' => $data['permissions'] ?? $user->permissions,
            'avatar_url'  => $data['avatar_url'] ?? $user->avatar_url,
        ]);

        return $this->success($this->format($user->fresh('role.parent')), 'Akun admin berhasil diperbarui');
    }

    // ─── RESET PASSWORD ───────────────────────────────────────

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user->update(['password' => $request->password]);

        return $this->success(null, 'Password berhasil direset');
    }

    // ─── DESTROY ──────────────────────────────────────────────

    public function destroy(Request $request, int $id): JsonResponse
    {
        $me = $request->user();

        if ($me->id === $id) {
            return $this->error('Tidak dapat menghapus akun sendiri.', 422);
        }

        $user = User::findOrFail($id);

        if ($user->isOwner()) {
            return $this->error('Akun owner tidak dapat dihapus.', 422);
        }

        if ($user->role?->nama === 'developer') {
            return $this->error('Akun developer tidak dapat dihapus.', 422);
        }

        Berkas::hapus($user->avatar_url);

        $user->delete();

        return $this->success(null, 'Akun admin berhasil dihapus');
    }

    // ─── ROLES LIST ───────────────────────────────────────────

    public function roles(): JsonResponse
    {
        $roles = Role::active()
            ->where('nama', '!=', 'owner')
            ->where('nama', '!=', 'developer')
            ->orderBy('level')
            ->orderBy('nama')
            ->with('parent')
            ->get()
            ->map(fn($r) => [
                'id'     => $r->id,
                'nama'   => $r->nama,
                'label'  => $r->parent ? "└ {$r->label} ({$r->parent->label})" : $r->label,
                'parent' => $r->parent?->label,
            ]);

        return $this->success($roles);
    }

    // ─── FORMAT ───────────────────────────────────────────────

    private function format(User $u): array
    {
        return [
            'id'          => $u->id,
            'name'        => $u->name,
            'email'       => $u->email,
            'role_id'     => $u->role_id,
            'role_nama'   => $u->role?->nama,
            'role_label'  => $u->role?->label,
            'role_parent' => $u->role?->parent?->label,
            'is_admin'    => $u->is_admin,
            'permissions' => is_array($u->permissions) ? $u->permissions : [],
            'avatar_url'  => $u->avatar_url_full,
            'created_at'  => $u->created_at?->toDateTimeString(),
        ];
    }
}
