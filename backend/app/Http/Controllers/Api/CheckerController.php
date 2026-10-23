<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checker;
use Illuminate\Http\Request;

class CheckerController extends Controller
{
    public function index(Request $request)
    {
        $query = Checker::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('nama', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->orderBy('nama')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'   => 'required|string|max:100',
            'status' => 'in:Aktif,Tidak Aktif',
        ]);

        $checker = Checker::create($data);

        return response()->json($checker, 201);
    }

    public function update(Request $request, $id)
    {
        $checker = Checker::findOrFail($id);

        $data = $request->validate([
            'nama'   => 'required|string|max:100',
            'status' => 'in:Aktif,Tidak Aktif',
        ]);

        $checker->update($data);

        return response()->json($checker);
    }

    public function destroy($id)
    {
        $checker = Checker::findOrFail($id);
        $checker->delete();

        return response()->json(['message' => 'Checker berhasil dihapus']);
    }
}
