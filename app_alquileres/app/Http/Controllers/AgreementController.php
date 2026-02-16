<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $lessor = $user?->lessor;

        $agreements = collect();

        if($user->isLessor()){
            $lessor = $user?->lessor;
            $agreements = Agreement::where('lessor_id', $lessor->id)
                ->get();
        }
        if($user->isRoomer()){
            $roomer = $user?->roomer;
            $agreements = Agreement::where('roomer_id', $roomer->id)
                ->get();
        }

        return view('admin.agreements.index', [
            'agreements'=>$agreements
        ]);
    }

    public function register()
    {
        return view('admin.agreements.register');
    }
}
