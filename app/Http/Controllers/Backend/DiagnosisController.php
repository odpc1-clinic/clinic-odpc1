<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Diagnosi;
use Illuminate\Http\Request;

class DiagnosisController extends Controller
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
            $diagnosis = Diagnosi::where('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('disease', 'LIKE', "%$keyword%")
                ->orWhere('other_disease', 'LIKE', "%$keyword%")
                ->orWhere('term_syphilis', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $diagnosis = Diagnosi::latest()->paginate($perPage);
        }

        return view('backend.diagnosis.index', compact('diagnosis'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.diagnosis.create');
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
        
        Diagnosi::create($requestData);

        return redirect('backend/diagnosis')->with('flash_message', 'Diagnosi added!');
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
        $diagnosi = Diagnosi::findOrFail($id);

        return view('backend.diagnosis.show', compact('diagnosi'));
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
        $diagnosi = Diagnosi::findOrFail($id);

        return view('backend.diagnosis.edit', compact('diagnosi'));
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
        
        $diagnosi = Diagnosi::findOrFail($id);
        $diagnosi->update($requestData);

        return redirect('backend/diagnosis')->with('flash_message', 'Diagnosi updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy(Request $request, $id)
{
    $diagnosi = Diagnosi::findOrFail($id);
    $visit_id = $diagnosi->visit_id;
    $page = $request->get('page', 1); // รับค่าจาก request
    $diagnosi->delete();

    return redirect()->route('visit.edit', ['visit' => $visit_id, 'page' => $page])
        ->with('success', 'Diagnosis deleted successfully.');
}

}
