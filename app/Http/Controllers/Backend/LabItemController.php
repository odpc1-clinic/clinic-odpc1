<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\LabItem;
use Illuminate\Http\Request;

class LabItemController extends Controller
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
            $labitem = LabItem::where('lab_id', 'LIKE', "%$keyword%")
                ->orWhere('urethra', 'LIKE', "%$keyword%")
                ->orWhere('begina', 'LIKE', "%$keyword%")
                ->orWhere('cervix', 'LIKE', "%$keyword%")
                ->orWhere('anus', 'LIKE', "%$keyword%")
                ->orWhere('pharaynx', 'LIKE', "%$keyword%")
                ->orWhere('other_specimen', 'LIKE', "%$keyword%")
                ->orWhere('gram_stain', 'LIKE', "%$keyword%")
                ->orWhere('gncd', 'LIKE', "%$keyword%")
                ->orWhere('pmn', 'LIKE', "%$keyword%")
                ->orWhere('wet_preparation', 'LIKE', "%$keyword%")
                ->orWhere('tv', 'LIKE', "%$keyword%")
                ->orWhere('pmcure_celln', 'LIKE', "%$keyword%")
                ->orWhere('koh', 'LIKE', "%$keyword%")
                ->orWhere('yeast_hyphae', 'LIKE', "%$keyword%")
                ->orWhere('bacterial_ulture', 'LIKE', "%$keyword%")
                ->orWhere('bc_result', 'LIKE', "%$keyword%")
                ->orWhere('other_test', 'LIKE', "%$keyword%")
                ->orWhere('other_result', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $labitem = LabItem::latest()->paginate($perPage);
        }

        return view('backend.lab-item.index', compact('labitem'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.lab-item.create');
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
        
        LabItem::create($requestData);

        return redirect('backend/lab-item')->with('flash_message', 'LabItem added!');
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
        $labitem = LabItem::findOrFail($id);

        return view('backend.lab-item.show', compact('labitem'));
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
        $labitem = LabItem::findOrFail($id);

        return view('backend.lab-item.edit', compact('labitem'));
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
        
        $labitem = LabItem::findOrFail($id);
        $labitem->update($requestData);

        return redirect('backend/lab-item')->with('flash_message', 'LabItem updated!');
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
        LabItem::destroy($id);

        return redirect('backend/lab-item')->with('flash_message', 'LabItem deleted!');
    }
}
