<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kebijakan;
use Illuminate\Http\Request;

class KebijakanController extends Controller
{
    // GET /api/ketua/kebijakan -> pages/ketua/KendaliKebijakan.jsx
    public function show()
    {
        return response()->json(Kebijakan::current()->load('updatedBy:id,nama'));
    }

    // PUT /api/ketua/kebijakan -> update salah satu / beberapa parameter sekaligus
    public function update(Request $request)
    {
        $validated = $request->validate([
            'plafon_maksimal' => 'sometimes|numeric|min:0',
            'suku_bunga_persen' => 'sometimes|numeric|min:0|max:100',
            'simpanan_wajib_nominal' => 'sometimes|numeric|min:0',
            'minimal_progress_topup_persen' => 'sometimes|numeric|min:0|max:100',
            'catatan_terakhir' => 'nullable|string',
        ]);

        $kebijakan = Kebijakan::current();
        $kebijakan->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($kebijakan->fresh()->load('updatedBy:id,nama'));
    }
}
