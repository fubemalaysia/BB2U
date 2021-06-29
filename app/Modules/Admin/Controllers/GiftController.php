<?php

namespace App\Modules\Admin\Controllers;
 
use App\Gift;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Api\Models\EarningModel; 
use Illuminate\Http\Request;
use App\Helpers\Session as AppSession;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use App\Modules\Api\Models\UserModel;
use App\Modules\Api\Models\SettingModel;
use Redirect;
use DB;
use HTML;

class GiftController extends Controller {

	public function index()
    {
		$editdata = array();
		try{
			$gifts = Gift::paginate(10);
        }
        catch(Exception $e){
			print_r($e); exit;
            return $e->getMessage();
        } 
	 
		return view('Admin::gift_list')->with('listGift', $gifts);
       
    }
	public function delete($id){ 
	   DB::table('gifts')->where('id',$id)->delete();
	   return redirect('/admin/gift/')->with('msgInfo', 'Gift delete successfully updated');
	}
	public function store(Request $request)
	{
		$data= array();
		$userData = Input::all();
		if (Input::file('file')) {
          // checking file is valid.
          if (!Input::file('file')->isValid()) {
              return Back()->with('msgInfo', 'uploaded file is not valid');
          }
          $destinationPath = PATH_UPLOAD; // upload path
          $extension = Input::file('file')->getClientOriginalExtension();  
          $fileName = 'gift-' . rand(11111, 99999) . '.' . $extension;  
 
          Input::file('file')->move($destinationPath, $fileName); 
        
          $data['file'] = $fileName;
		}
		$data['name']=$userData['name'];
		$data['price']= $userData['price'];
		 DB::table('gifts')->insert($data);
	   return Back()->with('msgInfo', 'Gift was successfully added');
	}
	
	
	
	public function edit($id)
    { 
		$editdata = Gift::find($id);
 
		try{
			$gifts = Gift::paginate(10);
        }
        catch(Exception $e){ 
            return $e->getMessage();
        }
		return view('Admin::gift_edit')->with('listGift', $gifts)->with('editdata', $editdata);
    }
	public function editProcess(Request $request,$id)
	{
		$data= array();
		$userData = Input::all();
		if (Input::file('file')) {
          // checking file is valid.
          if (!Input::file('file')->isValid()) {
              return Back()->with('msgInfo', 'uploaded file is not valid');
          }
          $destinationPath = PATH_UPLOAD; // upload path
          $extension = Input::file('file')->getClientOriginalExtension();  
          $fileName = 'gift-' . rand(11111, 99999) . '.' . $extension;  
 
          Input::file('file')->move($destinationPath, $fileName); 
        
          $data['file'] = $fileName;
		}
		$data['name']=$userData['name'];
		$data['price']= $userData['price'];
		 DB::table('gifts')->where('id',$id)->update($data);
	   return Back()->with('msgInfo', 'Gift was successfully updated');
	}
}
