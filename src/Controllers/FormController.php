<?php
/*--------------------
https://github.com/jazmy/laravelformbuilder
Licensed under the GNU General Public License v3.0
Author: Jasmine Robinson (jazmy.com)
Last Updated: 12/29/2018
----------------------*/
namespace jazmy\FormBuilder\Controllers;

use App\Http\Controllers\Controller;
use jazmy\FormBuilder\Events\Form\FormCreated;
use jazmy\FormBuilder\Events\Form\FormDeleted;
use jazmy\FormBuilder\Events\Form\FormUpdated;
use jazmy\FormBuilder\Helper;
use jazmy\FormBuilder\Models\Form;
use jazmy\FormBuilder\Requests\SaveFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Auth;

class FormController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pageTitle = "Forms";

        $user = Auth::user();

        if ($user->user_type == 'admin' or $user->user_type == 'staff') {
            $forms = Form::getForAdmin();
        }
        else {
            // $forms = Form::getForUser(auth()->user());
            $forms = Form::where('coop', $user->coop)
                    ->withCount('submissions')
                    ->latest()
                    ->paginate(10);
        }
        // dd($forms);
        return view('formbuilder::forms.index', compact('pageTitle', 'forms'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $pageTitle = "Create New Form";

        $saveURL = route('formbuilder::forms.store');

        // get the roles to use to populate the make the 'Access' section of the form builder work
        $form_roles = Helper::getConfiguredRoles();

        return view('formbuilder::forms.create', compact('pageTitle', 'saveURL', 'form_roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  jazmy\FormBuilder\Requests\SaveFormRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(SaveFormRequest $request)
    {
        $user = $request->user();

        $input = $request->merge(['user_id' => $user->id])->except('_token');

        DB::beginTransaction();

        // generate a random identifier
        // $input['identifier'] = $user->id.'-'.Helper::randomString(20);
        $input['identifier'] = strtolower(str_replace(' ', '-', $request->name) . '-' . Helper::randomString(16));
        $input['name'] = $request->name;

        // $coop = \App\Loan::where('name', 'like', $request->name)->first();
        // $coop = \App\Loan::findOrFail($request->coop);

        $input['coop'] = $request->coop;
        $input['loan_id'] = $request->coop;
        $created = Form::create($input);

        try {
            // dispatch the event
            event(new FormCreated($created));

            DB::commit();

            return response()
                    ->json([
                        'success' => true,
                        'details' => 'Form successfully created!',
                        'dest' => route('formbuilder::forms.index'),
                    ]);
        } catch (Throwable $e) {
            info($e);

            DB::rollback();

            return response()->json(['success' => false, 'details' => 'Failed to create the form.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = auth()->user();

        if ($user->user_type == 'admin') {
            $form = Form::where(['id' => $id])
                        ->with('user')
                        ->withCount('submissions')
                        ->firstOrFail();
        } else {
            // $form = Form::where(['user_id' => $user->id, 'id' => $id])
            $form = Form::where(['id' => $id])
                        ->with('user')
                        ->withCount('submissions')
                        ->firstOrFail();
        }        

        $pageTitle = "Preview Form";

        return view('formbuilder::forms.show', compact('pageTitle', 'form'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = auth()->user();
		

        if ($user->user_type == 'admin')
            $form = Form::where(['id' => $id])->first();
        elseif ($user->user_type == 'koperasi')
            $form = Form::where(['id' => $id])->first();		
        else
            // $form = Form::where(['user_id' => $user->id, 'id' => $id])->firstOrFail();
            $form = Form::where(['id' => $id])->first();

        $pageTitle = 'Edit Form';

        $saveURL = route('formbuilder::forms.update', $form);

        // get the roles to use to populate the make the 'Access' section of the form builder work
        $form_roles = Helper::getConfiguredRoles();



        return view('formbuilder::forms.edit', compact('form', 'pageTitle', 'saveURL', 'form_roles'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  jazmy\FormBuilder\Requests\SaveFormRequest $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(SaveFormRequest $request, $id)
    {
        $user = auth()->user();

        if ($user->user_type == 'admin'){
            $form = Form::where(['id' => $id])->firstOrFail();
            $jalan = 'formbuilder::forms.index';
            $jalan2 = null;
        }
		
        elseif ($user->user_type == 'koperasi' ){
            $form = Form::where(['id' => $id])->firstOrFail();
            $jalan = 'formbuilder::forms.edit';
            $jalan2 = $id;
        }		
        else {
            // $form = Form::where(['user_id' => $user->id, 'id' => $id])->firstOrFail();
            $form = Form::where(['id' => $id])->firstOrFail();
            $jalan = 'formbuilder::forms.edit';
            $jalan2 = $id;
        }

        $input = $request->except('_token');
		
		

        if ($form->update($input)) {
            // dispatch the event
            event(new FormUpdated($form));

            return response()
                    ->json([
                        'success' => true,
                        'details' => 'Form successfully updated!',
                        'dest' => route($jalan, $jalan2),
                        // formbuilder::forms.index
                    ]);
        } else {
            response()->json(['success' => false, 'details' => 'Failed to update the form.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $form = Form::where(['id' => $id])->firstOrFail();
        $form->delete();

        // dispatch the event
        event(new FormDeleted($form));

        return back()->with('success', "'{$form->name}' deleted.");
    }
}
