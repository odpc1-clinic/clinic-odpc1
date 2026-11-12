<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use RealRashid\SweetAlert\Facades\Alert;

use App\Models\Item;
use Illuminate\Validation\ValidationException;

class ItemsController extends Controller
{
  
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'item title',
            'price' => 10
        ]);

        try {
            Item::create([
                'name' => $request->name,
                'price' => $request->price
            ]);

            return redirect()->back()
                ->with('success', 'Created successfully!');
        } catch (\Exception $e){
            return redirect()->back()
                ->with('error', 'Error during the creation!');
        }
    }
}