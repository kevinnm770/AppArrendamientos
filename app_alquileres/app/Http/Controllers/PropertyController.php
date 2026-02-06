<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index()
    {
        return view('admin.properties.index');
    }

    public function register()
    {
        return view('admin.properties.register');
    }
}
