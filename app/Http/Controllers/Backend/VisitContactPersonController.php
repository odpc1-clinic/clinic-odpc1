<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\VisitContactPerson;
use Illuminate\Http\Request;

class VisitContactPersonController extends Controller
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
            $visitcontactperson = VisitContactPerson::where('visit_id', 'LIKE', "%$keyword%")
                ->orWhere('CContactPerson_id', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $visitcontactperson = VisitContactPerson::latest()->paginate($perPage);
        }

        return view('backend.visit-contact-person.index', compact('visitcontactperson'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('backend.visit-contact-person.create');
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
        
        VisitContactPerson::create($requestData);

        return redirect('backend/visit-contact-person')->with('flash_message', 'VisitContactPerson added!');
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
        $visitcontactperson = VisitContactPerson::findOrFail($id);

        return view('backend.visit-contact-person.show', compact('visitcontactperson'));
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
        $visitcontactperson = VisitContactPerson::findOrFail($id);

        return view('backend.visit-contact-person.edit', compact('visitcontactperson'));
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
        
        $visitcontactperson = VisitContactPerson::findOrFail($id);
        $visitcontactperson->update($requestData);

        return redirect('backend/visit-contact-person')->with('flash_message', 'VisitContactPerson updated!');
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
        VisitContactPerson::destroy($id);

        return redirect('backend/visit-contact-person')->with('flash_message', 'VisitContactPerson deleted!');
    }
}
