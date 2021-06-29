<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Helpers\Session as AppSession;
use DB;
class Controller extends BaseController {

  use AuthorizesRequests,
      DispatchesJobs,
      ValidatesRequests;

  public function index() {
      $userData = AppSession::getLoginData();

      if(\App::environment('development') && !$userData){
          return view('home.underconstruction');
      }
	$hotdata = DB::table('users')
	->select('users.username',
	DB::raw('SUM(earnings.percent) as totla_percent'))
	->join('earnings', 'earnings.payTo', '=', 'users.id')  
	->groupBy('earnings.percent')->limit(10)->orderBy('totla_percent', 'desc')->get();
	
	$categoriesdata = DB::table('categories')
	->select('categories.*',
	DB::raw('COUNT(user_category.category_id) as totla_cat'))
	->join('user_category', 'categories.id', '=', 'user_category.category_id')  
	->groupBy('user_category.category_id')->limit(5)->orderBy('totla_cat', 'desc')->get();
	 
	$talents = DB::table('users')
	->select('users.username','users.avatar','performer.age',
	DB::raw('COUNT(performer.user_id) as totlastrem'))
	->join('performer', 'performer.user_id', '=', 'users.id')  
	->groupBy('performer.user_id')->limit(10)->orderBy('totlastrem', 'desc')->get();
	 exit;
    return view('home.index',compact('hotdata','categoriesdata','talents'));
  }

}
