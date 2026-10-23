<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Berkas;
use App\Traits\ApiResponse;
use App\Traits\UploadGambar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use ApiResponse, UploadGambar;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'remember' => 'sometimes|boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Email atau password salah.', 401);
        }

        if (!$user->isAdminUser()) {
            return $this->forbidden('Akun ini tidak memiliki akses admin.');
        }

        // Akun admin sering dipakai bergantian oleh beberapa staf untuk fungsi
        // yang sama (mis. "Admin Penjualan") — jadi login baru TIDAK menendang
        // sesi lain yang masih aktif di tab/device lain. Token lama tetap hidup
        // sampai logout eksplisit atau expired (lihat config/sanctum.php).
        // "Ingat saya" dicentang -> token 30 hari (mis. app desktop, tidak mau
        // login ulang tiap hari); tidak dicentang -> default 12 jam.
        $expiresAt = $request->boolean('remember') ? now()->addDays(30) : now()->addHours(12);
        $token = $user->createToken('admin_token', ['*'], $expiresAt)->plainTextToken;

        // Load role agar role_nama tersedia
        $user->load('role:id,nama,label');

        return $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'role'        => $user->role?->label ?? 'Admin',
                'role_nama'   => $user->role?->nama,
                'permissions' => $user->getEffectivePermissions(),
                'is_admin'    => $user->is_admin,
                'avatar'      => $user->avatar_url_full,
            ],
        ], 'Login berhasil');
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,' . $request->user()->id,
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();
        $data = $request->only('name', 'email');

        // Handle avatar upload with compression
        if ($request->hasFile('avatar')) {
            // Delete old avatar (stored as relative path in avatar_url)
            if (!str_starts_with((string) $user->avatar_url, 'http')) {
                Berkas::hapus($user->avatar_url);
            }
            $data['avatar_url'] = $this->uploadCompressed($request->file('avatar'), 'avatars');
        }

        $user->update($data);

        return $this->success([
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'role'   => $user->role?->label ?? 'Admin',
            'avatar' => $user->avatar_url_full,
        ], 'Profil berhasil diperbarui');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'          => 'required|string',
            'new_password'              => ['required', 'string', 'min:6', 'confirmed'],
            'new_password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Password saat ini tidak sesuai.', 422);
        }

        $user->update(['password' => $request->new_password]);

        return $this->success(null, 'Password berhasil diubah');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'Logout berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role:id,nama,label');

        return $this->success([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role?->label ?? 'Admin',
            'role_nama'   => $user->role?->nama,
            'permissions' => $user->getEffectivePermissions(),
            'is_admin'    => $user->is_admin,
            'avatar'      => $user->avatar_url_full,
        ], 'Data user berhasil diambil');
    }
}
