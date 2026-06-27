<?php

namespace App\Http\Controllers;

use App\Models\Part;
use App\Models\Product;

class PublicPageController extends Controller
{
    public function home()
    {
        return view('pages.home', [
            'featuredProducts' => Product::query()->with('category')->active()->latest()->take(4)->get(),
            'featuredParts' => Part::query()->where('is_active', true)->where('status', 'active')->latest()->take(4)->get(),
        ]);
    }

    public function about()
    {
        return view('pages.about');
    }

    public function services()
    {
        return view('pages.services');
    }
}
