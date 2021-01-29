<?php
/*--------------------
https://github.com/jazmy/laravelformbuilder
Licensed under the GNU General Public License v3.0
Author: Jasmine Robinson (jazmy.com)
Last Updated: 12/29/2018
----------------------*/
namespace jazmy\FormBuilder\Controllers;

use App\Http\Controllers\Controller;
use jazmy\FormBuilder\Helper;
use jazmy\FormBuilder\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Auth;

class RenderFormController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('public-form-access');
    }

    /**
     * Render the form so a user can fill it
     *
     * @param string $identifier
     * @return Response
     */
    public function render_without($identifier)
    {
        $form = Form::where('identifier', $identifier)->firstOrFail();

        $coop = $form->loan;

        $user = Auth::user()->user_type;

        if ($user == 'admin' or $user == 'staff' or $user == 'koperasi')
            $user = true;
        else
            $user = false;

        $pageTitle = "{$form->name}";

        return view('formbuilder::render.index_without', compact('form', 'pageTitle' ,'user', 'coop'));
    }

    /**
     * Render the form so a user can fill it
     *
     * @param string $identifier
     * @return Response
     */
    public function render($identifier, $order_id = null)
    {
        // dd(session()->get('order_loan_id'));

        $order_loan_id = session()->get('order_loan_id');
        $order = \App\Order::findOrFail($order_id);

        $form = Form::where('identifier', $identifier)->firstOrFail();

        //$coop = $form->loan;
		
		$coop = \App\Loan::find($form->coop);
		
		

        $user = Auth::user()->user_type;

        if ($user == 'admin' or $user == 'staff' or $user == 'koperasi')
            $user = true;
        else
            $user = false;

        $pageTitle = "{$form->name}";

        return view('formbuilder::render.index', compact('form', 'pageTitle' ,'user', 'order', 'coop', 'order_loan_id'));
    }

    /**
     * Process the form submission
     *
     * @param Request $request
     * @param string $identifier
     * @return Response
     */
    public function submit(Request $request, $identifier)
    {

        // foreach ($request->all() as $key => $value) {
        //     $data[$key] = $value;
        // }

        // dd($request->all());
        $form = Form::where('identifier', $identifier)->firstOrFail();

        DB::beginTransaction();

        try {
            $input = $request->except('_token');

            // dd($input);

            $uploads = [];
            $i = 0;

            // check if files were uploaded and process them
            $uploadedFiles = $request->allFiles();
            foreach ($uploadedFiles as $key => $file) {
                // store the file and set it's path to the value of the key holding it
                if ($file->isValid()) {
                    $input[$key] = $file->store('uploads/loans_applied/' . $form->coop_name->slug);
                    $uploads[$key] = $input[$key];
                }
                $i++;
            }

            if(count($uploads) > 0){
                $test = json_encode($uploads);
            } else {
                $test = null;
            }

            $user_id = auth()->user()->id ?? null;

            $form->submissions()->create([
                'user_id' => $user_id,
                'content' => $input,
            ]);

            // dd($input);

            DB::commit();


            // Save to loan_applieds

            $order = \App\Order::findOrFail($request->order_id);
            // dd($order->loan_applied);

            // $uploads['ic_upload'] = $input['ic_upload'];
            // $uploads['payslip'] = $input['payslip'];

            $data = json_encode($input);
            // $test = json_encode($uploads);

            $loan_info = new \App\LoanInfo;
            $loan_info->user_id      = Auth::user()->id;
            $loan_info->loan_id      = $form->loan_id;
            $loan_info->contact_info = $data;
            $loan_info->kyc          = $test;
            $loan_info->save();

            $loan = \App\LoanApplied::where('id', $input['order_loan_id'])->first();
            if (!$loan) {
                $loan = $order->loan_applied;
            }
            $loan->loan_info_id    = $loan_info->id;
            // $loan->loan_amount     = $order->grand_total * ($request->loan_amount);
            $loan->info            = $data;
            $loan->uploads         = $test;
            $loan->status          = 0;
            $loan->save();

            // $loan = new \App\LoanApplied;
            // $loan->user_id         = Auth::user()->id;
            // $loan->order_id        = $request->order_id;
            // $loan->loan_id         = $form->loan_id;
            // $loan->loan_info_id    = $loan_info->id;
            // $loan->loan_amount     = $order->grand_total * ($request->loan_amount);
            // $loan->info            = $data;
            // $loan->uploads         = $test;
            // $loan->status          = 0;
            // $loan->save();            

            flash(__('Success'))->success();
            return redirect()->route('loan_history.index');

            // return redirect()
            //         ->route('formbuilder::form.feedback', $identifier)
            //         ->with('success', 'Form successfully submitted.');
        } catch (Throwable $e) {
            info($e);
            dd($e);

            DB::rollback();

            return back()->withInput()->with('error', Helper::wtf());
        }
    }

    /**
     * Display a feedback page
     *
     * @param string $identifier
     * @return Response
     */
    public function feedback($identifier)
    {
        $form = Form::where('identifier', $identifier)->firstOrFail();

        $pageTitle = "Form Submitted!";

        return view('formbuilder::render.feedback', compact('form', 'pageTitle'));
    }
}
