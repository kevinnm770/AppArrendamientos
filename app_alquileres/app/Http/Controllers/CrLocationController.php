<?php

namespace App\Http\Controllers;

use App\Models\CrCanton;
use App\Models\CrDistrict;
use Illuminate\Http\Request;

class CrLocationController extends Controller
{
    public function cantons(Request $request)
    {
        $province = (string) $request->query('province', '');

        $cantons = CrCanton::where('province_code', $province)
            ->orderBy('name')
            ->get(['code', 'name']);

        return response()->json(['results' => $cantons]);
    }

    public function districts(Request $request)
    {
        $province = (string) $request->query('province', '');
        $canton = (string) $request->query('canton', '');

        $districts = CrDistrict::where('province_code', $province)
            ->where('canton_code', $canton)
            ->orderBy('name')
            ->get(['code', 'name']);

        return response()->json(['results' => $districts]);
    }
}
