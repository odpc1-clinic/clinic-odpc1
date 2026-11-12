<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\VisitMedicine;
use Illuminate\Http\Request;

class VisitMedicineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $visitmedicine = VisitMedicine::where('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('medicine', 'LIKE', "%$keyword%")
                ->orWhere('method', 'LIKE', "%$keyword%")
                ->orWhere('amount', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $visitmedicine = VisitMedicine::latest()->paginate($perPage);
        }

        return view('backend.visit-medicine.index', compact('visitmedicine'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.visit-medicine.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        
        $requestData = $request->all();
        
        VisitMedicine::create($requestData);

        return redirect('backend/visit-medicine')->with('flash_message', 'VisitMedicine added!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $visitmedicine = VisitMedicine::findOrFail($id);

        return view('backend.visit-medicine.show', compact('visitmedicine'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $visitmedicine = VisitMedicine::findOrFail($id);

        return view('backend.visit-medicine.edit', compact('visitmedicine'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $id)
    {
        
        $requestData = $request->all();
        
        $visitmedicine = VisitMedicine::findOrFail($id);
        $visitmedicine->update($requestData);

        return redirect('backend/visit-medicine')->with('flash_message', 'VisitMedicine updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        VisitMedicine::destroy($id);

        return redirect('backend/visit-medicine')->with('flash_message', 'VisitMedicine deleted!');
    }
}
