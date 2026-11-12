<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Authorizable;
use App\Http\Controllers\MailController;
use App\Http\Controllers\Member\TvpaketeController;
use App\Lib\JoinTvGate;
use App\Models\Membership;
use App\Models\User_log;
use App\Models\Purchase;
use App\Models\User_support_note;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use Authorizable;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $result = User::latest()->paginate();
        return view('backend.user.index', compact('result'));
    }

    public function create()
    {
        $roles = Role::pluck('name', 'id');
        return view('backend.user.new', compact('roles'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'bail|required|min:2',
            'email' => 'required|email|unique:users',
            'license_number' => 'nullable|string|max:50', // <--- เพิ่มตรงนี้
            'password' => ['required', new \App\Rules\StrongPassword()],
            'roles' => 'required|min:1'
        ]);

        // hash password
        $request->merge(['password' => bcrypt($request->get('password'))]);

        // Create the user
        if ( $user = User::create($request->except('roles', 'permissions')) ) {
            $this->syncPermissions($request, $user);
            flash('User has been created.');
        } else {
            flash()->error('Unable to create user.');
        }

        return redirect()->route('users.index');
    }

    public function edit($id)
    {
        $user = User::find($id);
        $roles = Role::pluck('name', 'id');
        $permissions = Permission::all('name', 'id');

        return view('backend.user.edit', compact('user', 'roles', 'permissions'));
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'bail|required|min:2',
            'email' => 'required|email|unique:users,email,' . $id,
            'license_number' => 'nullable|string|max:50', // <--- เพิ่มตรงนี้
            'roles' => 'required|min:1',
            // ของเดิมไม่ตรวจรหัสผ่านเลย พิมพ์ 1234 ก็ผ่าน (พบ 2569-08-27)
            // ต้องผ่านเกณฑ์เดียวกับหน้าเปลี่ยนรหัสผ่าน ไม่งั้นมีทางหลบเกณฑ์อยู่ทางหนึ่ง
            'password' => ['nullable', new \App\Rules\StrongPassword(
                optional(User::find($id))->password,
                [optional(User::find($id))->name, optional(User::find($id))->email]
            )],
        ]);

        // Get the user
        $user = User::findOrFail($id);

        // Update user
        $user->fill($request->except('roles', 'permissions', 'password'));

        // check for password change
        if($request->get('password')) {
            $user->password = bcrypt($request->get('password'));
            $user->password_changed_at = now();
            $user->must_change_password = false;
            // $user->password_org = $request->get('password');
            
        }

        // Handle the user roles
        $this->syncPermissions($request, $user);

        $user->save();
        flash()->success('User has been updated.');
        return redirect()->route('users.index');
    }

    public function destroy($id)
    {
        if ( Auth::user()->id == $id ) {
            flash()->warning('Deletion of currently logged in user is not allowed :(')->important();
            return redirect()->back();
        }

        if( User::findOrFail($id)->delete() ) {
            flash()->success('User has been deleted');
        } else {
            flash()->success('User not deleted');
        }

        return redirect()->back();
    }

    private function syncPermissions(Request $request, $user)
    {
        // Get the submitted roles
        $roles = $request->get('roles', []);
        $permissions = $request->get('permissions', []);

        // Get the roles
        $roles = Role::find($roles);

        // check for current role changes
        if( ! $user->hasAllRoles( $roles ) ) {
            // reset all direct permissions for user
            $user->permissions()->sync([]);
        } else {
            // handle permissions
            $user->syncPermissions($permissions);
        }

        $user->syncRoles($roles);
        return $user;
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
        $user = User::findOrFail($id);
        $currentPackageInfo = JoinTvGate::getDays($user->email);
        $remainDay = 0;
        $membership = null ; 
        if($currentPackageInfo ){
            $membership = Membership::where('token',$currentPackageInfo->package_id)->first();
            $remainDay = $currentPackageInfo->days;
        }

        $user_logs = User_log::where('user_id', $id)->get();
        $purchases = Purchase::where('user_id', $id)->get();

        $user_support_notes = User_support_note::where('user_id', $id)->get();
             
        return view('backend.user.show', compact('user','remainDay','membership','user_logs','purchases','user_support_notes'));
    }

    public function welcome($id)
    {
        $user = User::findOrFail($id);
        MailController::welcome( $user);
        // flash()->success('Welecome mail sent');
                          
        return redirect('admin/users/'.$id)->with('flash_message', 'Welecome mail sent');
    }

    public function purchase($id)
    {
        $purchase = Purchase::findOrFail($id);

        $purchase_tv_box = Purchase_tv_box::where('purchase_id',$id)->first();
        $purchase_membership = Purchase_membership::where('purchase_id',$id)->first();


        return view('backend.user.purchase', compact('purchase', 'purchase_tv_box', 'purchase_membership'));
    }

    public function addSuportNote(Request $request,$id)
    {
        // $data = $request->get();
        $sser_support_note = new User_support_note;
        $sser_support_note->user_id = $id;
        $sser_support_note->create_id = Auth::user()->id;
        $sser_support_note->detail = $request->get('detail');
        $sser_support_note->save();
        
        return redirect('admin/users/'.$id)->with('flash_message', 'add suport note done !!');
    }
    
    public function removeSuportNote(Request $request,$id)
    {
        $sser_support_note = User_support_note::find($id)->delete();

        
        return redirect('admin/users/'.$id)->with('flash_message', 'remoe suport note done !!');
    }
}
