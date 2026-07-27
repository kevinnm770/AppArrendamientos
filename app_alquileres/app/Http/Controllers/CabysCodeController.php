<?php

namespace App\Http\Controllers;

use App\Models\CabysCode;
use Illuminate\Http\Request;

class CabysCodeController extends Controller
{
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 3) {
            return response()->json(['results' => []]);
        }

        $results = CabysCode::query()
            ->where('code', 'like', "{$term}%")
            ->orWhere('description', 'like', "%{$term}%")
            ->orderBy('code')
            ->limit(20)
            ->get(['code', 'description', 'tax_rate']);

        return response()->json(['results' => $results]);
    }
}
