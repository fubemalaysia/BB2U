<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\Session as AppSession;
use App\Helpers\Helper as AppHelper;
use App\Modules\Model\Models\PerformerTag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use App\Modules\Api\Models\UserModel;
use App\Modules\Api\Models\PerformerModel;
use App\Modules\Api\Models\CategoryModel;
use App\Events\AddModelPerformerChatEvent;
use App\Events\AddModelScheduleEvent;
use App\Events\AddEarningSettingEvent;
use App\Events\AddModelPerformerEvent;
use App\Modules\Api\Models\CountryModel;
use App\Events\MakeChatRoomEvent;
use App\Modules\Api\Models\PerformerChatModel;
use App\Modules\Api\Models\AttachmentModel;
use App\Modules\Api\Models\PaymentTokensModel;
use App\Modules\Api\Models\EarningModel;
use App\Modules\Api\Models\FavoriteModel;
use App\Modules\Api\Models\MessageReplyModel;
use App\Modules\Api\Models\MessageConversationModel;
use App\Modules\Api\Models\GalleryModel;
use App\Modules\Api\Models\ScheduleModel;
use App\Modules\Api\Models\EarningSettingModel;
use App\Jobs\DeleteGalleryByOwner;
use App\Jobs\deleteAttachmentByOwner;
use DB;
use HTML;
use Nayjest\Grids\Components\Base\RenderableRegistry;
use Nayjest\Grids\Components\ColumnHeadersRow;
use Nayjest\Grids\Components\ColumnsHider;
use Nayjest\Grids\Components\CsvExport;
use Nayjest\Grids\Components\ExcelExport;
use Nayjest\Grids\Components\FiltersRow;
use Nayjest\Grids\Components\HtmlTag;
use Nayjest\Grids\Components\Laravel5\Pager;
use Nayjest\Grids\Components\OneCellRow;
use Nayjest\Grids\Components\RecordsPerPage;
use Nayjest\Grids\Components\ShowingRecords;
use Nayjest\Grids\Components\TFoot;
use Nayjest\Grids\Components\THead;
use Nayjest\Grids\EloquentDataProvider;
use Nayjest\Grids\FieldConfig;
use Nayjest\Grids\FilterConfig;
use Nayjest\Grids\SelectFilterConfig;
use Nayjest\Grids\Grid;
use Nayjest\Grids\GridConfig;
use App\Modules\Api\Models\DocumentModel;
use App\Helpers\MediaHelper;

class UserController extends Controller {

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response members
   */
  public function getMyProfile() {
    $adminData = AppSession::getLoginData();
    if(env('DISABLE_EDIT_ADMIN') && !$adminData->isSuperAdmin) {
      return redirect('admin/dashboard')->with('msgError', 'Can not access this page!'); 
    }
    $userData = AppSession::getLoginData();

    return view('Admin::admin_profile')->with('profile', $userData);
  }

  /**
   * @param post $params user data
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response
   */
  public function updateMyProfile() {
    $adminData = AppSession::getLoginData();
    if(env('DISABLE_EDIT_ADMIN') && !$adminData->isSuperAdmin) {
      return redirect('admin/dashboard')->with('msgError', 'Can not access this page!'); 
    }
    $userData = AppSession::getLoginData();
    if (!$userData) {
      return Redirect::to('admin/login')->with('Your session was expired.');
    }
    Validator::extend('hashmatch', function($attribute, $value, $parameters) {
//      return Hash::check($value, AuthController::user()->$parameters[0]);
      return AuthController::checkPassword($attribute, $value, $parameters);
//      var_dump($attribute,$value, $parameters);
    });

    $messages = array(
      'hashmatch' => 'Your current password must match your account password.'
    );
    $validator = Validator::make(Input::all(), [
        'email' => 'Required|Between:3,64|Email|Unique:users,email,' . $userData->id,
        'firstName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'lastName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'password' => 'Required|hashmatch:passwordHash',
        'newPassword' => 'Between:6,32|Confirmed',
        'newPassword_confirmation' => 'Between:6,32'
        ], $messages);
    //check current password

    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }
    $profile = UserModel::find($userData->id);
    if (!$profile) {
      AppSession::getLogout();
      return Redirect::to('admin/login')->with('msgError', 'Your account not found.');
    }
    $profile->firstName = preg_replace('/\s+/', ' ',  Input::get('firstName'));
    $profile->lastName = preg_replace('/\s+/', ' ',  Input::get('lastName'));
    $profile->email = Input::get('email');
    if (Input::has('newPassword')) {
      $profile->passwordHash = md5(Input::get('newPassword'));
    }
    if ($profile->save()) {
      $userData->firstName = $profile->firstName;
      $userData->lastName = $profile->lastName;
      $userData->email = $profile->email;
      AppSession::setLogin($userData);
      return Back()->with('msgInfo', 'Your account was successfully updated.');
    }
    return Back()->with('msgError', 'System error.');
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response members
   */
  public function getMemberUsers($role = 'member') {
    $adminData = AppSession::getLoginData();
    $query = UserModel
      ::leftJoin('countries as c', 'users.location_id', '=', 'c.id')
      ->select('users.*', 'users.id as check', 'users.id as action', DB::raw('(SELECT sum(p.tokens) FROM paymenttokens p WHERE p.ownerId = users.id) as spendTokens'), DB::raw("case users.role when 'model' then (SELECT SUM(c.streamingTime) FROM chatthreads c WHERE c.ownerId=users.id) when 'member' then (SELECT SUM(tu.streamingTime) FROM chatthreadusers tu WHERE tu.userId=users.id) END as streamingTime"))
      // Column alias 'country_name' used to avoid naming conflicts, suggest that customers table also has 'name' column.
      ->addSelect('c.name')
      ->whereRaw('(users.role = "'.UserModel::ROLE_ADMIN.'" OR users.role = "'.UserModel::ROLE_MEMBER.'")');
    $columns = [
      (new FieldConfig)
      ->setName('check')
      ->setLabel('<input type="checkbox" name="checklist[]" class="check-all">')
      ->setCallback(function ($val) {
          return '<input type="checkbox" name="checklist[]" class="case" value="' . $val . '">';
        })
      ->setSortable(false)
      ,
      (new FieldConfig)
      ->setName('id')
      ->setLabel('ID')
      ->setSortable(true)
      ->setSorting(Grid::SORT_ASC)
      ,
      (new FieldConfig)
      ->setName('username')
      ->setLabel('Username')
      ->setCallback(function ($val) {
          return "<span class='glyphicon glyphicon-user'></span>{$val}";
        })
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('email')
      ->setLabel('Email')
      ->setSortable(true)
      ->setCallback(function ($val) {
          $icon = '<span class="glyphicon glyphicon-envelope"></span>&nbsp;';
          return
            '<small>'
            . $icon
            . HTML::link("mailto:$val", $val)
            . '</small>';
        })
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      ),
      (new FieldConfig)
      ->setName('role')
      ->setLabel('Role')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('role')
        ->setOptions(['admin' => 'Admin', 'member' => 'Member'])
      )
      ,
      (new FieldConfig)
      ->setName('gender')
      ->setLabel('Gender')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('gender')
        ->setOptions(['male' => 'Male', 'female' => 'Female', 'transgender' => 'Transgender'])
      )
      ,
      (new FieldConfig)
      ->setName('spendTokens')
      ->setLabel('Spend')
      ->setSortable(true)
      ,
      (new FieldConfig)
      ->setName('tokens')
      ->setLabel('Tokens')
      ->setSortable(true)
      ,
      (new FieldConfig)
      ->setName('accountStatus')
      ->setLabel('Status')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('accountStatus')
        ->setOptions(['active'=>'Active','suspend'=>'Suspend','notConfirmed'=>'Not Confirmed','disable'=>'Disable','waiting'=>'Pending'])
      )
      ->setCallback(function($val){
          $return = '';
          switch ($val){
              case UserModel::ACCOUNT_ACTIVE: $return = 'Active';
                  break;
              case UserModel::ACCOUNT_DISABLE: $return = 'Disable';
                  break;
              case UserModel::ACCOUNT_NOTCONFIRMED: $return = 'Not Confirmed';
                  break;
              case UserModel::ACCOUNT_SUSPEND: $return = 'Suspend';
                  break;
              case UserModel::ACCOUNT_WAITING: $return = 'Pending';
                  break;
              default: $return = '';
                  break;
          }
          return $return;
      }),

      (new FieldConfig)
      ->setName('streamingTime')
      ->setLabel('Watching Time')
      ->setSortable(true)
      ->setCallback(function ($val){

        return AppHelper::convertToHoursMins($val, '%02d hours %02d minutes');
      })
      ,
      (new FieldConfig)
      ->setName('mobilePhone')
      ->setLabel('Phone')
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('name')
      ->setLabel('Country')
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ->setSortable(true)
      ,
      (new FieldConfig)
      ->setName('createdAt')
      ->setLabel('reg. Date')
      ->setSortable(true)
      ->setCallback(function ($val) {
        return date('F d, Y', strtotime($val));
        })
    ];
    if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) {
      $columns[] = (new FieldConfig)
    ->setName('action')
    ->setLabel('Actions')
    ->setCallback(function ($val, $row) {
      /*<a class="btn btn-info btn-sm" ng-click="addToAdmin({{$user->id}})">Add to admin</a>&nbsp;&nbsp;<a class="btn btn-success btn-sm" ng-click="setAccountStatus({{$user->id}})">Change</a>&nbsp;&nbsp;<a class="btn btn-warning btn-sm" href="{{URL('admin/manager/member-profile/'.$user->id)}}">Edit</a>*/
        $item = $row->getSrc();
        $url = "<a title='Edit account' href='" . URL('admin/manager/member-profile/' . $val) . "'><span class='fa fa-pencil'></span></a>&nbsp;&nbsp;<a title='Delete account' onclick=\"return confirm('Are you sure you want to delete this account?')\" href='" . URL('admin/manager/profile/delete/' . $val) . "'><span class='fa fa-trash'></span></a>";  
        if($item->accountStatus != UserModel::ACCOUNT_ACTIVE){
            $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/approve/' . $val)."' title='Approve account'><i class='fa fa-check-circle-o' aria-hidden='true'></i></a>";
        }
        if($item->accountStatus != UserModel::ACCOUNT_DISABLE){
            $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/disable/' . $val) . "' title='Disable account' onclick=\"return confirm('Are you sure you want to disable this account?')\"><span class='fa fa-ban'></span></a>";
        }
        if($item->accountStatus == UserModel::ACCOUNT_ACTIVE){
            $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/suspend/' . $val)."' title='Suspend account'><i class='fa fa-exclamation-circle' aria-hidden='true'></i></a>";
        }
        
        return $url;
      })
    ->setSortable(false);
    }
    
    $grid = new Grid(
      (new GridConfig)
        ->setDataProvider(
          new EloquentDataProvider($query)
        )
        ->setName('Users')
        ->setPageSize(10)
        ->setColumns($columns)
        ->setComponents([
          (new THead)
          ->setComponents([
            (new ColumnHeadersRow),
            (new FiltersRow)
            ,
            (new OneCellRow)
            ->setRenderSection(RenderableRegistry::SECTION_END)
            ->setComponents([
              (new RecordsPerPage)
              ->setVariants([
                10,
                20,
                30,
                40,
                50,
                100,
                200,
                300,
                400,
                500
              ]),
               # Control to show/hide rows in table
              (new ColumnsHider)
                  ->setHiddenByDefault([
                      'email',
                      'name',
                      'mobilePhone',
                  ])
              ,
              (new CsvExport)
              ->setFileName('my_report' . date('Y-m-d'))
              ,
              (new ExcelExport())
              ->setFileName('Users-' . date('Y-m-d'))->setSheetName('Excel sheet'),
              (new HtmlTag)
              ->setContent('<span class="glyphicon glyphicon-refresh"></span> Filter')
              ->setTagName('button')
              ->setRenderSection(RenderableRegistry::SECTION_END)
              ->setAttributes([
                'class' => 'btn btn-success btn-sm',
                'id' => 'formFilter'
              ])
            ])
          ])
          ,
          (new TFoot)
          ->setComponents([
            (new OneCellRow)
            ->setComponents([
              new Pager,
              (new HtmlTag)
              ->setAttributes(['class' => 'pull-right'])
              ->addComponent(new ShowingRecords)
              ,
            ])
          ])
        ])
    );

    return view('Admin::member-manager')->with('title', 'List Members')->with('grid', $grid->render());
  }
    /**
    * delete account
    * @param int $id model id
    */
   public function deleteAccount($id) {
      $adminData = AppSession::getLoginData();
       $user = UserModel::find($id);
       if (!$user) {
           return Back()->with('msgError', 'User not exist!');
       }
       if(env('DISABLE_EDIT_ADMIN') && !$adminData->isSuperAdmin)
          return redirect('admin/manager/members')->with('msgError', 'Can not access this page!'); 

       //TODO delete earning

       EarningModel::whereRaw("(payFrom = {$user->id} OR payTo = {$user->id})")->delete();

       //TODO delete all user payment

       PaymentTokensModel::where('ownerId', $user->id)->delete();

       //TODO delete all favorites

       FavoriteModel::where('ownerId', $user->id)->delete();


       //TODO delete all messages
       MessageReplyModel::whereRaw("(userId={$user->id} OR receivedId = {$user->id})")->delete();
       MessageConversationModel::whereRaw("userOne={$user->id} OR userTwo={$user->id}")->delete();

       //TODO DELETE all profile image
       if ($user->role == UserModel::ROLE_MEMBER && AppHelper::is_serialized($user->avatar)) {
           //delete file
           $avatar = unserialize($user->avatar);
           foreach ($avatar as $key => $value) {
               if (file_exists(public_path($value))) {
                   \File::Delete(public_path($value));
               }
           }
       }
       if ($user->role == UserModel::ROLE_MODEL) {
           //Delete model profiles
           $job = (new deleteAttachmentByOwner($user->id, AttachmentModel::TYPE_PROFILE));
           $this->dispatch($job);
           //delete image gallery
           $imageGalleryJob = (new DeleteGalleryByOwner($user->id, GalleryModel::IMAGE));
           $this->dispatch($imageGalleryJob);
           //Delete video gallery
           $videoGalleryJob = (new DeleteGalleryByOwner($user->id, GalleryModel::VIDEO));
           $this->dispatch($videoGalleryJob);

           //TODO delete model schedule
           ScheduleModel::where('modelId', $user->id)->delete();
           //TODO delete performer and performer chat settings
           PerformerModel::where('user_id', $user->id)->delete();
           //TODO delete earning settings
           EarningSettingModel::where('userId', $user->id)->delete();
           //DELETE performer chat settings.
           $performerChat = PerformerChatModel::where('model_id', $user->id)->first();
           if($performerChat){
               if($performerChat->thumbnail){
                   $thumbnail = AttachmentModel::find($performerChat->thumbnail);
                   if($thumbnail){
                       if(file_exists(public_path($thumbnail->path))){
                         \File::Delete(public_path($thumbnail->path));
                       }
                       $thumbnail->delete();
                   }

               }
               $performerChat->delete();
           }
       }


       if ($user->delete()) {
           return Back()->with('msgInfo', 'User was successfully deleted');
       }
       return Back()->with('msgError', 'System error.');
   }
   /**
   * disable account
   * @param int $id model id
   */
  public function disableAccount($id) {
    $user = UserModel::find($id);
    if (!$user) {
      return Back()->with('msgError', 'User not exist!');
    }
    $user->accountStatus = UserModel::ACCOUNT_DISABLE;
    if ($user->save()) {
        return Back()->with('msgInfo', 'User was successfully disabled');

    }
    return Back()->with('msgError', 'System error.');
  }

  /**
   * approval account
   * @param int $id model id
   */
  public function approveAccount($id) {
    $user = UserModel::find($id);
    $adminData = AppSession::getLoginData();
    if (!$user) {
      return Back()->with('msgError', 'User not exist!');
    }
    if(env('DISABLE_EDIT_ADMIN') && !$adminData->isSuperAdmin)
      return redirect('admin/manager/members')->with('msgError', 'Can not access this page!');
    if ($user->accountStatus == UserModel::ACCOUNT_ACTIVE) {
      return Back()->with('msgWarning', 'User has already approved');
    }
    $userStatus = $user->accountStatus;

    $user->accountStatus = UserModel::ACCOUNT_ACTIVE;
    $user->emailVerified = UserModel::EMAIL_VERIFIED;
    if ($user->save()) {
      if ($userStatus == UserModel::ACCOUNT_WAITING) {
        $username = $user->username;
        $email = $user->email;
        //send mail here
        $send = \Mail::send('email.approve', array('username' => $username, 'email' => $email), function($message) use($email) {
            $message->from(env('FROM_EMAIL') , app('settings')->siteName)->to($email)->subject('Approve Acount | '. app('settings')->siteName);
          });
        if ($send) {
          return Back()->with('msgInfo', 'Email has been sent to model!');
        } else {
          return Back()->with('msgError', 'Sent mail error.');
        }
      }else{
        return Back()->with('msgInfo', 'Account was successfully activated.');
      }

    }
    return Back()->with('msgError', 'System error.');
  }

  /**
   * suspend account
   * @param int $id model id
   */
  public function suspendAccount($id) {
    $user = UserModel::find($id);
    $adminData = AppSession::getLoginData();
    if (!$user) {
      return Back()->with('msgError', 'User not exist!');
    }
    if(env('DISABLE_EDIT_ADMIN') && !$adminData->isSuperAdmin) 
      return redirect('admin/manager/members')->with('msgError', 'Can not access this page!');
    $user->accountStatus = UserModel::ACCOUNT_SUSPEND;
    if ($user->save()) {
      return Back()->with('msgInfo', 'User was successfully suspended');
    }
    return Back()->with('msgError', 'System error.');
  }

  /**
   * @return view add member view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function addMember() {
    $countries = CountryModel::orderBy('name')->lists('name', 'id')->all();
    return view('Admin::add-member', compact('countries'));
  }

  /**
   * @return view add member view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function addMemberProcess() {
    $validator = Validator::make(Input::all(), [
        'username' => 'unique:users|Between:3,64|required',
        'email' => 'Required|Between:3,64|Email|Unique:users',
        'firstName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'lastName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'location' => 'Required',
        'password' => 'Required|Between:6,32|Confirmed',
        'password_confirmation' => 'Required|Between:6,32',
        'tokens'    => 'Integer',
        'gender'    => 'Required|in:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER
    ]);

    if ($validator->fails()) {
      return back()
          ->withErrors($validator)
          ->withInput();
    }
    $userData = Input::all();

    $newMember = new UserModel ();
    $newMember->firstName = preg_replace('/\s+/', ' ',  Input::get('firstName'));
    $newMember->lastName = preg_replace('/\s+/', ' ',  Input::get('lastName'));
    if (!empty($userData['gender'])) {
      $newMember->gender = $userData['gender'];
    }
    $newMember->username = $userData['username'];
    $newMember->email = $userData['email'];
    $newMember->passwordHash = md5($userData['password']);
    $newMember->location_id = $userData['location'];
    $newMember->emailVerified = 1;
    $newMember->accountStatus = UserModel::ACCOUNT_ACTIVE;
    $newMember->role = UserModel::ROLE_MEMBER;
    $newMember->tokens = $userData['tokens'];

    if ($newMember->save()) {
      return redirect('admin/manager/member-profile/' . $newMember->id)->with('msgInfo', 'Created user successfully!');
    } else {
      return redirect()->back()->withInput()->with('msgError', 'System error.');
    }
  }

  /**
   * @return view member view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function updateMemberProcess($id) {

    $validator = Validator::make(Input::all(), [
        'username' => 'Required|Between:3,32|alphaNum|Unique:users,username,' . $id,
        'firstName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'lastName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'location' => 'Required',
        'passwordHash' => 'AlphaNum|Between:6,32',
        'tokens'    => 'Integer|Min:0',
        'gender'    => 'Required|in:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER,
        'role'      => 'Required|in:'.UserModel::ROLE_ADMIN.','.UserModel::ROLE_MEMBER
    ]);

    if ($validator->fails()) {
      return back()
          ->withErrors($validator)
          ->withInput();
    }
    $userData = Input::all();

    $member = UserModel::find($id);
    if (!$member)
      return redirect('admin/manager/members')->with('msgError', 'User not exist!');
    $member->firstName = preg_replace('/\s+/', ' ',  Input::get('firstName'));
    $member->lastName = preg_replace('/\s+/', ' ',  Input::get('lastName'));

    $member->gender = $userData['gender'];
    $member->role = $userData['role'];

    if (Input::has('passwordHash') && !empty($userData['passwordHash'])) {
      $member->passwordHash = md5($userData['passwordHash']);
    }
    $member->username = $userData['username'];
    $member->location_id = $userData['location'];
    $member->tokens = $userData['tokens'];

    if ($member->save()) {
      return back()->with('msgInfo', 'Member was successfully updated!');
    } else {
      return redirect()->back()->withInput()->with('msgError', 'System error.');
    }
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @param integer $id member id
   * @return view
   */
  public function getMemberProfile($id) {
    $adminData = AppSession::getLoginData();
    $user = UserModel::where('id', $id)
      ->whereRaw('(users.role = "'.UserModel::ROLE_ADMIN.'" OR users.role = "'.UserModel::ROLE_MEMBER.'")')
      ->first();
    if (!$user)
      return redirect('admin/manager/members')->with('msgInfo', 'User not exist!');
    if(env('DISABLE_EDIT_ADMIN') && !$adminData->isSuperAdmin) 
      return redirect('admin/manager/members')->with('msgError', 'Can not access this page!');

    $countries = CountryModel::orderBy('name')->lists('name', 'id')->all();
    return view('Admin::member-edit', compact('countries', 'user'));
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response models
   */
  public function getModelUsers($role = 'model') {
      $query = UserModel
      ::leftJoin('countries as c', 'users.countryId', '=', 'c.id')
      ->select('users.*', 'users.id as check', 'users.id as action')
      // Column alias 'country_name' used to avoid naming conflicts, suggest that customers table also has 'name' column.
      ->addSelect('c.name')

      ->where('users.role', UserModel::ROLE_MODEL);
      $studios = UserModel::where('role', UserModel::ROLE_STUDIO)->get();
      $dropdownStudios = [];
      foreach($studios as $studio) {
        $dropdownStudios[$studio->id] = $studio->username;
      }
    $grid = new Grid(
      (new GridConfig)
        ->setDataProvider(
          new EloquentDataProvider($query)
        )
        ->setName('Models')
        ->setPageSize(10)
        ->setColumns([
          (new FieldConfig)
          ->setName('check')
          ->setLabel('<input type="checkbox" name="checklist[]" class="check-all">')
          ->setCallback(function ($val) {
              return '<input type="checkbox" name="checklist[]" class="case" value="' . $val . '">';
            })
          ->setSortable(false)
          ,
          (new FieldConfig)
          ->setName('id')
          ->setLabel('ID')
          ->setSortable(true)
          ->setSorting(Grid::SORT_ASC)
          ,
          (new FieldConfig)
          ->setName('username')
          ->setLabel('Username')
          ->setCallback(function ($val) {
              return "{$val}";
            })
          ->setSortable(true)
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ,
          (new FieldConfig)
          ->setName('parentId')
          ->setLabel('Studio')
          ->setCallback(function ($val) {
            if(!$val || $val === 1){
              return '';
            }
            $user = UserModel::findMe($val);
            return $user->username;
          })
          ->setSortable(true)
          ->addFilter(
            (new SelectFilterConfig)
            ->setName('parentId')
            ->setOptions($dropdownStudios)
          )
          ,
          (new FieldConfig)
          ->setName('email')
          ->setLabel('Email')
          ->setSortable(true)
          ->setCallback(function ($val) {
              $icon = '<span class="glyphicon glyphicon-envelope"></span>&nbsp;';
              return
                '<small>'
                . HTML::link("mailto:$val", $val)
                . '</small>';
            })
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ,
          (new FieldConfig)
          ->setName('tokens')
          ->setLabel('Tokens')
          ->setSortable(true)
          ,

          (new FieldConfig)
          ->setName('minPayment')
          ->setLabel('Min Payment')
          ->setSortable(true)
          ->setCallback(function($val){
              return $val . '$';
          })
          ,

          (new FieldConfig)
          ->setName('gender')
          ->setLabel('Gender')
          ->setSortable(true)
          ->addFilter(
            (new SelectFilterConfig)
            ->setName('gender')
            ->setOptions(['male'=>'Male','female'=>'Female', 'transgender' => 'Transgender'])
          )
          ,
          (new FieldConfig)
          ->setName('accountStatus')
          ->setLabel('Status')
          ->setSortable(true)
          ->addFilter(
            (new SelectFilterConfig)
            ->setName('accountStatus')
            ->setOptions(['active'=>'Active','suspend'=>'Suspend','notConfirmed'=>'Not Confirmed','disable'=>'Disable','waiting'=>'Pending'])
          )
          ->setCallback(function($val){
              $return = '';
              switch ($val){
                  case UserModel::ACCOUNT_ACTIVE: $return = 'Active';
                      break;
                  case UserModel::ACCOUNT_DISABLE: $return = 'Disable';
                      break;
                  case UserModel::ACCOUNT_NOTCONFIRMED: $return = 'Not Confirmed';
                      break;
                  case UserModel::ACCOUNT_SUSPEND: $return = 'Suspend';
                      break;
                  case UserModel::ACCOUNT_WAITING: $return = 'Pending';
                      break;
                  default: $return = '';
                      break;
              }
              return $return;
          }),
            (new FieldConfig)
            ->setName('suspendReason')
            ->setLabel('Suspend Reason')
          ,
          (new FieldConfig)
          ->setName('mobilePhone')
          ->setLabel('Phone')
          ->setSortable(true)
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ,
          (new FieldConfig)
          ->setName('name')
          ->setLabel('Country')
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ->setSortable(true)
          ,
          (new FieldConfig)
          ->setName('createdAt')
          ->setLabel('reg. Date')
          ->setSortable(true)
          ->setCallback(function($val){
            $d = new \DateTime($val);
            return $d->format('M d, Y');
          })
          ,
          (new FieldConfig)
          ->setName('action')
          ->setLabel('Actions')
          ->setCallback(function ($val, $row) {
              $item = $row->getSrc();
              $url = "<a href='" . URL('admin/manager/model-profile/' . $val) . "' title='Edit account'><span class='fa fa-pencil'></span></a>&nbsp;&nbsp;<a title='Delete account' onclick=\"return confirm('Are you sure you want to delete this account?')\" href='" . URL('admin/manager/profile/delete/' . $val) . "'><span class='fa fa-trash'></span></a>";
              if($item->accountStatus != UserModel::ACCOUNT_ACTIVE){
                  $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/approve/' . $val)."' title='Approve account'><i class='fa fa-check-circle-o' aria-hidden='true'></i></a>";
              }
              if($item->accountStatus != UserModel::ACCOUNT_DISABLE){
                  $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/disable/' . $val) . "' title='Disable account' onclick=\"return confirm('Are you sure you want to disable this account?')\"><span class='fa fa-ban'></span></a>";
              }
              if($item->accountStatus == UserModel::ACCOUNT_ACTIVE){
                  $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/suspend/' . $val)."' title='Suspend account'><i class='fa fa-exclamation-circle' aria-hidden='true'></i></a>";
              }
              $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/image-gallery/' . $val) . "' title='Image Galleries'><span class='fa fa-picture-o'></span></a>&nbsp;&nbsp;<a href='" . URL('admin/manager/video-gallery/' . $val) . "' title='Video Galleries'><span class='fa fa-video-camera'></span></a>&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/identification/' . $val) . "' title='Model Identification'><span class='fa fa-file-text-o'></span></a>&nbsp;&nbsp;<a href='" . URL('admin/manager/model/chat/' . $val) . "' title='Manage Chat Messages'><span class='fa fa-comments-o' aria-hidden='true'></span></a>";
              return $url;

            })
          ->setSortable(false)
          ,
        ])
        ->setComponents([
          (new THead)
          ->setComponents([
            (new ColumnHeadersRow),
            (new FiltersRow)
            ,
            (new OneCellRow)
            ->setRenderSection(RenderableRegistry::SECTION_END)
            ->setComponents([
              (new RecordsPerPage)
              ->setVariants([
                10,
                20,
                30,
                40,
                50,
                100,
                200,
                300,
                400,
                500
              ]),
              new ColumnsHider,
              (new CsvExport)
              ->setFileName('my_report' . date('Y-m-d'))
              ,
              (new ExcelExport())
              ->setFileName('Models-'.  date('Y-m-d'))->setSheetName('Excel sheet'),
              (new HtmlTag)
              ->setContent('<span class="glyphicon glyphicon-refresh"></span> Filter')
              ->setTagName('button')
              ->setRenderSection(RenderableRegistry::SECTION_END)
              ->setAttributes([
                'class' => 'btn btn-success btn-sm',
                'id' => 'formFilter'
              ])
            ])
          ])
          ,
          (new TFoot)
          ->setComponents([
            (new OneCellRow)
            ->setComponents([
              new Pager,
              (new HtmlTag)
              ->setAttributes(['class' => 'pull-right'])
              ->addComponent(new ShowingRecords)
              ,
            ])
          ])
        ])
    );
    $grid = $grid->render();

    return view('Admin::model-manager', compact('grid'))->with('title', 'List Models');
  }
  public function getModelPending(){
    $query = UserModel
      ::leftJoin('countries as c', 'users.countryId', '=', 'c.id')
      ->select('users.*', 'users.id as check', 'users.id as action')
      // Column alias 'country_name' used to avoid naming conflicts, suggest that customers table also has 'name' column.
      ->addSelect('c.name')
      ->where('users.role', UserModel::ROLE_MODEL)
      ->where('accountStatus', 'waiting');
      $studios = UserModel::where('role', UserModel::ROLE_STUDIO)->get();
      $dropdownStudios = [];
      foreach($studios as $studio) {
        $dropdownStudios[$studio->id] = $studio->username;
      }
    $columns = [
      (new FieldConfig)
      ->setName('check')
      ->setLabel('<input type="checkbox" name="checklist[]" class="check-all">')
      ->setCallback(function ($val) {
          return '<input type="checkbox" name="checklist[]" class="case" value="' . $val . '">';
        })
      ->setSortable(false)
      ,
      (new FieldConfig)
      ->setName('id')
      ->setLabel('ID')
      ->setSortable(true)
      ->setSorting(Grid::SORT_ASC)
      ,
      (new FieldConfig)
      ->setName('username')
      ->setLabel('Username')
      ->setCallback(function ($val) {
          return "{$val}";
        })
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('parentId')
      ->setLabel('Studio')
      ->setCallback(function ($val) {
        if(!$val || $val === 1){
          return '';
        }
        $user = UserModel::findMe($val);
        return $user->username;
      })
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('parentId')
        ->setOptions($dropdownStudios)
      )
      ,
      (new FieldConfig)
      ->setName('email')
      ->setLabel('Email')
      ->setSortable(true)
      ->setCallback(function ($val) {
          $icon = '<span class="glyphicon glyphicon-envelope"></span>&nbsp;';
          return
            '<small>'
            . HTML::link("mailto:$val", $val)
            . '</small>';
        })
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('tokens')
      ->setLabel('Tokens')
      ->setSortable(true)
      ,

      (new FieldConfig)
      ->setName('minPayment')
      ->setLabel('Min Payment')
      ->setSortable(true)
      ->setCallback(function($val){
          return $val . '$';
      })
      ,

      (new FieldConfig)
      ->setName('gender')
      ->setLabel('Gender')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('gender')
        ->setOptions(['male'=>'Male','female'=>'Female', 'transgender' => 'Transgender'])
      )
      ,
      (new FieldConfig)
      ->setName('suspendReason')
      ->setLabel('Suspend Reason')
      ,
      (new FieldConfig)
      ->setName('mobilePhone')
      ->setLabel('Phone')
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('name')
      ->setLabel('Country')
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ->setSortable(true)
      ,
      (new FieldConfig)
      ->setName('createdAt')
      ->setLabel('reg. Date')
      ->setSortable(true)
      ->setCallback(function($val){
        $d = new \DateTime($val);
        return $d->format('M d, Y');
      })
      
    ];
    $adminData = AppSession::getLoginData();
    if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) {
      $columns[] = (new FieldConfig)
      ->setName('action')
      ->setLabel('Actions')
      ->setCallback(function ($val, $row) {
          $item = $row->getSrc();
          $url = "<a href='" . URL('admin/manager/model-profile/' . $val) . "' title='Edit account'><span class='fa fa-pencil'></span></a>&nbsp;&nbsp;<a title='Delete account' onclick=\"return confirm('Are you sure you want to delete this account?')\" href='" . URL('admin/manager/profile/delete/' . $val) . "'><span class='fa fa-trash'></span></a>";
          if($item->accountStatus != UserModel::ACCOUNT_ACTIVE){
              $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/approve/' . $val)."' title='Approve account'><i class='fa fa-check-circle-o' aria-hidden='true'></i></a>";
          }
          if($item->accountStatus != UserModel::ACCOUNT_DISABLE){
              $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/disable/' . $val) . "' title='Disable account' onclick=\"return confirm('Are you sure you want to disable this account?')\"><span class='fa fa-ban'></span></a>";
          }
          if($item->accountStatus == UserModel::ACCOUNT_ACTIVE){
              $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/suspend/' . $val)."' title='Suspend account'><i class='fa fa-exclamation-circle' aria-hidden='true'></i></a>";
          }
          $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/image-gallery/' . $val) . "' title='Image Galleries'><span class='fa fa-picture-o'></span></a>&nbsp;&nbsp;<a href='" . URL('admin/manager/video-gallery/' . $val) . "' title='Video Galleries'><span class='fa fa-video-camera'></span></a>&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/identification/' . $val) . "' title='Model Identification'><span class='fa fa-file-text-o'></span></a>&nbsp;&nbsp;<a href='" . URL('admin/manager/model/chat/' . $val) . "' title='Manage Chat Messages'><span class='fa fa-comments-o' aria-hidden='true'></span></a>";
          return $url;

        })
      ->setSortable(false);
    }
    $grid = new Grid(
      (new GridConfig)
        ->setDataProvider(
          new EloquentDataProvider($query)
        )
        ->setName('Models')
        ->setPageSize(10)
        ->setColumns($columns)
        ->setComponents([
          (new THead)
          ->setComponents([
            (new ColumnHeadersRow),
            (new FiltersRow)
            ,
            (new OneCellRow)
            ->setRenderSection(RenderableRegistry::SECTION_END)
            ->setComponents([
              (new RecordsPerPage)
              ->setVariants([
                10,
                20,
                30,
                40,
                50,
                100,
                200,
                300,
                400,
                500
              ]),
              new ColumnsHider,
              (new CsvExport)
              ->setFileName('my_report' . date('Y-m-d'))
              ,
              (new ExcelExport())
              ->setFileName('Models-'.  date('Y-m-d'))->setSheetName('Excel sheet'),
              (new HtmlTag)
              ->setContent('<span class="glyphicon glyphicon-refresh"></span> Filter')
              ->setTagName('button')
              ->setRenderSection(RenderableRegistry::SECTION_END)
              ->setAttributes([
                'class' => 'btn btn-success btn-sm',
                'id' => 'formFilter'
              ])
            ])
          ])
          ,
          (new TFoot)
          ->setComponents([
            (new OneCellRow)
            ->setComponents([
              new Pager,
              (new HtmlTag)
              ->setAttributes(['class' => 'pull-right'])
              ->addComponent(new ShowingRecords)
              ,
            ])
          ])
        ])
    );
    $grid = $grid->render();

    return view('Admin::model-pending-manager', compact('grid'))->with('title', 'List Models');
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response models
   */
  public function getModelOnline() {
    $search = (Input::has('q')) ? Input::get('q') : null;
    $page = (Input::has('page')) ? Input::get('page') : 1;
    $options = array(
      'take' => LIMIT_PER_PAGE,
      'skip' => ($page - 1) * LIMIT_PER_PAGE,
      'q' => $search,
      'filter' => (Input::has('filter')) ? Input::get('filter') : null
    );
    $users = UserModel::select('users.id', 'users.username', 'users.email', 't.lastStreamingTime', DB::raw("(SELECT COUNT(tu.id) FROM chatthreadusers as tu WHERE tu.threadId=t.id AND tu.isStreaming=1 AND tu.userId!=0) as totalWatching"), DB::raw("(SELECT COUNT(tu.id) FROM chatthreadusers as tu WHERE tu.threadId=t.id AND tu.isStreaming=1 AND tu.userId=0) as totalGuestWatching"), 't.id as roomId', 't.type as streamType')
      ->join('chatthreads as t', 't.ownerId', '=', 'users.id')
      ->where('users.role', UserModel::ROLE_MODEL)
      ->where('t.isStreaming', true);
    if ($search) {
      $users = $users->where('users.username', 'like', $search . '%');
    }
    if (isset($options['filter']) && $options['filter'] != null && in_array($options['filter'], ['username', 'email', 'status', 'createdAt', 'tokens'])) {
      $users = $users->orderBy($options['filter'], 'desc');
    }

    $users = $users->paginate(LIMIT_PER_PAGE);

    return view('Admin::model-online-manager')->with('title', 'Live Models')->with('users', $users);
  }

  /**
   * get watching users
   * @param int $threadId
   * @return response
   */
  public function getWatching($threadId) {
    $watching = UserModel::select('users.id', 'users.username', 'users.email', 'tu.lastStreamingTime')
      ->join('chatthreadusers as tu', 'tu.userId', '=', 'users.id')
      ->where('tu.threadId', $threadId)
      ->paginate(LIMIT_PER_PAGE);
    return view('Admin::model-watching-manager')->with('watching', $watching);
  }

  /**
   * @return add model view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function addModel() {
    $manager = UserModel::where('role', UserModel::ROLE_STUDIO)->where('accountStatus', UserModel::ACCOUNT_ACTIVE)->orderBy('username')->lists('username', 'id');
    $countries = CountryModel::orderBy('name')->lists('name', 'id')->all();
    $bankTransferOptions = (object)[
      'withdrawCurrency' => '',
      'taxPayer' => '',
      'bankName' => '',
      'bankAddress' => '',
      'bankCity' => '',
      'bankState' => '',
      'bankZip' => '',
      'bankCountry' => '',
      'bankAcountNumber' => '',
      'bankSWIFTBICABA' => '',
      'holderOfBankAccount' => '',
      'additionalInformation' => '',
      'payPalAccount' => '',
      'checkPayable' => ''
    ];
    $directDeposit = (object)[
      'depositFirstName' => '',
      'depositLastName' => '',
      'accountingEmail' => '',
      'directBankName' => '',
      'accountType' => '',
      'accountNumber' => '',
      'routingNumber' => ''
    ];
    $paxum = (object)[
      'paxumName' => '',
      'paxumEmail' => '',
      'paxumAdditionalInformation' => ''
    ];
    $bitpay = (object)[
      'bitpayName' => '',
      'bitpayEmail' => '',
      'bitpayAdditionalInformation' => ''
    ];
    return view('Admin::add-model', compact('manager', 'countries', 'bankTransferOptions', 'directDeposit', 'paxum', 'bitpay'));
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @param integer $id member id
   * @return view
   */
  public function getModelProfile($id) {
    $user = UserModel::where('id', $id)
      ->where('role', UserModel::ROLE_MODEL)
      ->first();
    if (!$user)
      return redirect('admin/manager/performers')->with('msgInfo', 'Model not exist!');
    $managers = UserModel::where('role', UserModel::ROLE_STUDIO)->where('accountStatus', UserModel::ACCOUNT_ACTIVE)->lists('username', 'id')->all();
    $performer = PerformerModel::where('user_id', '=', $id)->first();
    if (!$performer) {
      $performer = new PerformerModel;
      $performer->user_id = $user->id;
      $performer->sex = $user->gender;
      if (!$performer->save()) {
        return redirect('admin/manager/performers')->with('msgError', 'Performer Setting error!');
      }
    }
    $categories = CategoryModel::orderBy('name')->lists('name', 'id')->all();
    $countries = CountryModel::orderBy('name')->lists('name', 'id')->all();
    $cat = $user->categories->pluck('id')->toArray();

    $heightList = UserModel::getHeightList();
    $weightList = UserModel::getWeightList();
    $bankTransferOptions = (object)[
      'withdrawCurrency' => '',
      'taxPayer' => '',
      'bankName' => '',
      'bankAddress' => '',
      'bankCity' => '',
      'bankState' => '',
      'bankZip' => '',
      'bankCountry' => '',
      'bankAcountNumber' => '',
      'bankSWIFTBICABA' => '',
      'holderOfBankAccount' => '',
      'additionalInformation' => '',
      'payPalAccount' => '',
      'checkPayable' => ''
    ];
    $directDeposit = (object)[
      'depositFirstName' => '',
      'depositLastName' => '',
      'accountingEmail' => '',
      'directBankName' => '',
      'accountType' => '',
      'accountNumber' => '',
      'routingNumber' => ''
    ];
    $paxum = (object)[
      'paxumName' => '',
      'paxumEmail' => '',
      'paxumAdditionalInformation' => ''
    ];
    $bitpay = (object)[
      'bitpayName' => '',
      'bitpayEmail' => '',
      'bitpayAdditionalInformation' => ''
    ];
    if($user->bankTransferOptions){
      $bankTransferOptions = json_decode($user->bankTransferOptions);
    }
    if($user->directDeposit){
      $directDeposit = json_decode($user->directDeposit);
    }
    if($user->paxum){
      $paxum = json_decode($user->paxum);
    }
    if($user->bitpay){
      $bitpay = json_decode($user->bitpay);
    }
    $document = DocumentModel::where('ownerId', $id)->first();

    return view('Admin::model-edit')
      ->with('user', $user)->with('managers', $managers)
      ->with('categories', $categories)
      ->with('performer', $performer)
      ->with('heightList', $heightList)
      ->with('weightList', $weightList)
      ->with('countries', $countries)
      ->with('bankTransferOptions', $bankTransferOptions)
      ->with('directDeposit', $directDeposit)
      ->with('paxum', $paxum)
      ->with('bitpay', $bitpay)
      ->with('document', $document)
      ->with('cat', $cat);
  }

  /**
   * @param object $object model field
   * @return Response
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function updateModelProcess($id) {
    $adminData = AppSession::getLoginData();
    if (!$adminData) {
      return Redirect('admin/login')->with('msgError', 'Your session was expired.');
    }
    $rules = [
      'username' => 'Required|Between:3,32|alphaNum|Unique:users,username,' . $id,
      'passwordHash' => 'Between:6,32',
      'firstName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
      'lastName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
      'country' => 'Required',
      'manager' => 'Exists:users,id,role,studio',
        'tokens'    => 'Integer|Min:0',
      'gender'    => 'Required|in:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER,
      'age' => 'Required|Integer|Min:18|Max:59',
      'category' => 'required',
      'sexualPreference' => 'Required',
      'idImage' => 'Max:2000|Mimes:jpg,jpeg,png',
      'faceId' => 'Max:2000|Mimes:jpg,jpeg,png',
      'releaseForm' => 'Max:2000|Mimes:doc,docx,pdf',
      'tags' => 'string',
      'stateName' => 'Required|String|Max:100',
      'cityName' => 'Required|String|Max:32',
      'zip' => 'Required|String|Max:10',
      'address1' => 'Required|String',
      'address2' => 'String',
      'mobilePhone' => 'Required|Max:15|phone',
      'myFiles' => 'Max:2000|Mimes:jpg,jpeg,png'
    ];
    $validator = Validator::make(Input::all(), $rules);
    if ($validator->fails()) {
      return back()->withErrors($validator)->withInput();
    }
    $user = UserModel::find($id);
    if (!$user)
      return redirect('/admin/manager/performers')->with('msgError', 'Model not exist!');

    $userData = Input::all();
    $user->firstName = preg_replace('/\s+/', ' ',  Input::get('firstName'));
    $user->lastName = preg_replace('/\s+/', ' ',  Input::get('lastName'));
    $user->gender = $userData['gender'];
    $user->username = $userData['username'];
    $user->countryId = $userData['country'];
    $user->parentId = Input::get('manager', 0);
    $user->tokens = intval(Input::get('tokens'));
    $user->stateName = $userData['stateName'];
    $user->cityName = $userData['cityName'];
    $user->zip = $userData['zip'];
    $user->address1 = $userData['address1'];
    $user->address2 = $userData['address2'];
    $user->mobilePhone = $userData['mobilePhone'];
    $user->autoApprovePayment = (isset($userData['autoApprovePayment'])) ? $userData['autoApprovePayment'] : null;
    if(Input::get('manager', 0)) {
      $earningSettingModel = EarningSettingModel::where('userId', $user->id)->first();
      $earninSettingStudio = EarningSettingModel::where('userId', Input::get('manager', 0))->first();     
      if($earningSettingModel->referredMember > $earninSettingStudio->referredMember) {
        $earningSettingModel->referredMember = $earninSettingStudio->referredMember;
        $earningSettingModel->save();
      }  
    }
    if (Input::has('passwordHash') && !empty($userData['passwordHash'])) {
      $user->passwordHash = md5($userData['passwordHash']);
    }
    
    if(Input::get('isRemovedAvatar')) {
      $user->avatar = null;
       $user->smallAvatar = null;
    }
    if ($user->save()) {
      $identityDocument = DocumentModel::where('ownerId', $user->id)->first();
      if(!$identityDocument){
        $identityDocument = new DocumentModel;
        $identityDocument->ownerId = $user->id;
      }
      if(Input::get('deleteImg')){
        foreach (Input::get('deleteImg') as $value){
            if (file_exists(PUBLIC_PATH . '/' . $identityDocument->$value)) {
                \File::Delete(PUBLIC_PATH . '/' . $identityDocument->$value);
            }
            $identityDocument->$value = null;
        }
      }
      $destinationPath = 'uploads/models/identity/';
      if (Input::file('idImage')) {
        if (!Input::file('idImage')->isValid()) {
          return Back()->with('msgInfo', 'uploaded file is not valid');
        }
        $image = Input::file('idImage');
        $filename = $user->username . uniqid() .'.' . $image->getClientOriginalExtension();
        $idPath = $destinationPath . 'id-images/' . $filename;
        Input::file('idImage')->move($destinationPath . 'id-images', $filename);
        $identityDocument->idImage = $idPath;
      }
      if (Input::file('faceId')) {
        if (!Input::file('faceId')->isValid()) {
          return Back()->with('msgInfo', 'uploaded file is not valid');
        }
        $image = Input::file('faceId');
        $filename = $user->username. uniqid()  . '.' . $image->getClientOriginalExtension();
        $faceId = $destinationPath . 'face-ids/' . $filename;
        Input::file('faceId')->move($destinationPath . 'face-ids', $filename);
        $identityDocument->faceId = $faceId;
      }
      if (Input::file('releaseForm')) {
        if (!Input::file('releaseForm')->isValid()) {
          return Back()->with('msgInfo', 'uploaded file is not valid');
        }
        $image = Input::file('releaseForm');
        $filename = $user->username. uniqid()  . '.' . $image->getClientOriginalExtension();
        $releaseForm = $destinationPath . 'release-forms/' . $filename;
        Input::file('releaseForm')->move($destinationPath . 'release-forms', $filename);
        $identityDocument->releaseForm = $releaseForm;
      }
      $identityDocument->save();

      $performer = PerformerModel::where('user_id', '=', $id)->first();
      if (!$performer) {
        $performer = new PerformerModel;
      }
      $user->categories()->sync(Input::get('category'));
      $performer->sex = $userData['gender'];
      $performer->sexualPreference = $userData['sexualPreference'];

      if (Input::has('ethnicity')) {
        $performer->ethnicity = $userData['ethnicity'];
      }
      if (Input::has('eyes')) {
        $performer->eyes = $userData['eyes'];
      }
      if (Input::has('hair')) {
        $performer->hair = $userData['hair'];
      }
      if (Input::has('height')) {
        $performer->height = $userData['height'];
      }
      if (Input::has('weight')) {
        $performer->weight = $userData['weight'];
      }
      $performer->bust = $userData['bust'];

      // $performer->category_id = $userData['category'];

      if (Input::has('pubic')) {
        $performer->pubic = $userData['pubic'];
      }

      if (Input::has('age')) {
        $performer->age = $userData['age'];
      }
      $performer->tags = Input::get('tags');

      if ($performer->save()) {
          PerformerTag::updateTags($performer->id, $performer->tags);
      }

      if ($performer->save()) {
        UserController::postPayeeInfo($user->id, Input::all());
        UserController::postDirectDeposit($user->id, Input::all());
        UserController::postPaxum($user->id, Input::all());
        UserController::postBitpay($user->id, Input::all());
        if(Input::file('myFiles')) {
          $uploadResponse = MediaHelper::upload($user, Input::file('myFiles'), 'profile', 0, $user->id);
          if($uploadResponse['success']){
            $setProfileResponse = MediaHelper::setProfileImage($user, $uploadResponse['file']->id);  
          }
        }
        return back()->with('msgInfo', 'Model was successfully updated!');
      }
    }
    return Back()->withInput()->with('msgError', 'System error.');
  }

  /**
   * @param object $object model field
   * @return Response
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function addModelProcess() {
    $adminData = AppSession::getLoginData();
    if (!$adminData) {
      return Redirect('admin/login')->with('msgError', 'Your session was expired.');
    }
    $validator = Validator::make(Input::all(), [
        'username' => 'unique:users|Between:3,64|required',
        'email' => 'Required|Between:3,64|Email|Unique:users',
        'firstName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'lastName' => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
        'passwordHash' => 'Required|AlphaNum|Between:6,32|Confirmed',
        'passwordHash_confirmation' => 'Required|AlphaNum|Between:6,32',
        'country' => 'Required',
        'gender'    => 'Required|In:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER,
        'manager' => 'Exists:users,id,role,studio',
        'idImage' => 'Max:2000|Mimes:jpg,jpeg,png',
        'faceId' => 'Max:2000|Mimes:jpg,jpeg,png',
        'releaseForm' => 'Max:2000|Mimes:doc,docx,pdf',
        'stateName' => 'Required|String|Max:100',
        'cityName' => 'Required|String|Max:32',
        'zip' => 'Required|String|Max:10',
        'address1' => 'Required|String',
        'address2' => 'String',
        'mobilePhone' => 'Required|Max:15|phone',
        'myFiles' => 'Max:2000|Mimes:jpg,jpeg,png'
    ]);
    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }
//    var_dump($validator->passes());
//    die();
    $userData = Input::all();

    $newMember = new UserModel ();
    $newMember->firstName = preg_replace('/\s+/', ' ',  Input::get('firstName'));
    $newMember->lastName = preg_replace('/\s+/', ' ',  Input::get('lastName'));
    $newMember->gender = (Input::has('gender')) ? $userData['gender'] : null;
    $newMember->username = $userData['username'];
    $newMember->email = $userData['email'];
    $newMember->passwordHash = md5($userData['passwordHash']);
    $newMember->countryId = (Input::has('country')) ? $userData['country'] : 0;
    $newMember->emailVerified = 1;
    $newMember->accountStatus = UserModel::ACCOUNT_ACTIVE;
    $newMember->parentId = (Input::has('manager')) ? $userData['manager'] : $adminData->id;
    $newMember->role = UserModel::ROLE_MODEL;
    $newMember->stateName = $userData['stateName'];
    $newMember->cityName = $userData['cityName'];
    $newMember->zip = $userData['zip'];
    $newMember->address1 = $userData['address1'];
    $newMember->address2 = $userData['address2'];
    $newMember->mobilePhone = $userData['mobilePhone'];
    $newMember->autoApprovePayment = (isset($userData['autoApprovePayment'])) ? $userData['autoApprovePayment'] : null;

    if ($newMember->save()) {
      \Event::fire(new AddModelPerformerChatEvent($newMember));
      \Event::fire(new AddModelScheduleEvent($newMember));
      \Event::fire(new AddEarningSettingEvent($newMember));
      \Event::fire(new AddModelPerformerEvent($newMember));
      \Event::fire(new MakeChatRoomEvent($newMember));

      // upload model's documents
      if(Input::file('idImage') || Input::file('faceId') || Input::file('releaseForm')){
        $identityDocument = new DocumentModel;
        $identityDocument->ownerId = $newMember->id;
        $destinationPath = 'uploads/models/identity/';
        if (Input::file('idImage')) {
          if (!Input::file('idImage')->isValid()) {
            return Back()->with('msgInfo', 'uploaded file is not valid');
          }
          $image = Input::file('idImage');
          $filename = $newMember->username . '.' . $image->getClientOriginalExtension();
          $idPath = $destinationPath . 'id-images/' . $filename;
          Input::file('idImage')->move($destinationPath . 'id-images', $filename);
          $identityDocument->idImage = $idPath;
        }
        if (Input::file('faceId')) {
          if (!Input::file('faceId')->isValid()) {
            return Back()->with('msgInfo', 'uploaded file is not valid');
          }
          $image = Input::file('faceId');
          $filename = $newMember->username . '.' . $image->getClientOriginalExtension();
          $faceId = $destinationPath . 'face-ids/' . $filename;
          Input::file('faceId')->move($destinationPath . 'face-ids', $filename);
          $identityDocument->faceId = $faceId;
        }
        if (Input::file('releaseForm')) {
          if (!Input::file('releaseForm')->isValid()) {
            return Back()->with('msgInfo', 'uploaded file is not valid');
          }
          $image = Input::file('releaseForm');
          $filename = $newMember->username . '.' . $image->getClientOriginalExtension();
          $releaseForm = $destinationPath . 'release-forms/' . $filename;
          Input::file('releaseForm')->move($destinationPath . 'release-forms', $filename);
          $identityDocument->releaseForm = $releaseForm;
        }
        $identityDocument->save();
      }
      UserController::postPayeeInfo($newMember->id, Input::all());
      UserController::postDirectDeposit($newMember->id, Input::all());
      UserController::postPaxum($newMember->id, Input::all());
      UserController::postBitpay($newMember->id, Input::all());
      if(Input::file('myFiles')) {
        $uploadResponse = MediaHelper::upload($newMember, Input::file('myFiles'), 'profile', 0, $newMember->id);
        if($uploadResponse['success']){
          $setProfileResponse = MediaHelper::setProfileImage($newMember, $uploadResponse['file']->id);  
        }
      }
      return redirect('admin/manager/performers')->with('msgInfo', 'Model was successfully created!');
    } else {
      return Back()->withInput()->with('msgError', 'System error.');
    }
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response models
   */
  public function getStudioUsers($role = 'studio') {
    $query = UserModel
      ::leftJoin('countries as c', 'users.countryId', '=', 'c.id')
      ->select('users.*', 'users.id as check', 'users.id as action')
      // Column alias 'country_name' used to avoid naming conflicts, suggest that customers table also has 'name' column.
      ->addSelect('c.name')

      ->where('users.role', UserModel::ROLE_STUDIO);
    $columns = [
      (new FieldConfig)
      ->setName('check')
      ->setLabel('<input type="checkbox" name="checklist[]" class="check-all">')
      ->setCallback(function ($val) {
          return '<input type="checkbox" name="checklist[]" class="case" value="' . $val . '">';
        })
      ->setSortable(false)
      ,
      (new FieldConfig)
      ->setName('id')
      ->setLabel('ID')
      ->setSortable(true)
      ->setSorting(Grid::SORT_ASC)
      ,
      (new FieldConfig)
      ->setName('username')
      ->setLabel('Username')
      ->setCallback(function ($val) {
          return "{$val}";
        })
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('email')
      ->setLabel('Email')
      ->setSortable(true)
      ->setCallback(function ($val) {
          $icon = '<span class="glyphicon glyphicon-envelope"></span>&nbsp;';
          return
            '<small>'
            . HTML::link("mailto:$val", $val)
            . '</small>';
        })
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('tokens')
      ->setLabel('Tokens')
      ->setSortable(true)

      ,

      (new FieldConfig)
      ->setName('minPayment')
      ->setLabel('Min Payment')
      ->setSortable(true)
      ->setCallback(function($val){
          return $val . '$';
      })
      ,
      (new FieldConfig)
      ->setName('gender')
      ->setLabel('Gender')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('gender')
        ->setOptions(['male'=>'Male','female'=>'Female', 'transgender' => 'Transgender'])
      )
      ,
      (new FieldConfig)
      ->setName('accountStatus')
      ->setLabel('Status')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('accountStatus')
        ->setOptions(['active'=>'Active','suspend'=>'Suspend','notConfirmed'=>'Not Confirmed','disable'=>'Disable','waiting'=>'Pending'])
      )
      ->setCallback(function($val){
          $return = '';
          switch ($val){
              case UserModel::ACCOUNT_ACTIVE: $return = 'Active';
                  break;
              case UserModel::ACCOUNT_DISABLE: $return = 'Disable';
                  break;
              case UserModel::ACCOUNT_NOTCONFIRMED: $return = 'Not Confirmed';
                  break;
              case UserModel::ACCOUNT_SUSPEND: $return = 'Suspend';
                  break;
              case UserModel::ACCOUNT_WAITING: $return = 'Pending';
                  break;
              default: $return = '';
                  break;
          }
          return $return;
      }),
      (new FieldConfig)
      ->setName('mobilePhone')
      ->setLabel('Phone')
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('name')
      ->setLabel('Country')
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ->setSortable(true)
      ,
      (new FieldConfig)
      ->setName('createdAt')
      ->setLabel('reg. Date')
      ->setSortable(true)
      ->setCallback(function($val){
        $d = new \DateTime($val);
        return $d->format('M d, Y');
      })
    ];
    $adminData = AppSession::getLoginData();
    if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) {
      $columns[] = (new FieldConfig)
      ->setName('action')
      ->setLabel('Actions')
      ->setCallback(function ($val, $row) {
           $item = $row->getSrc();
          $url = "<a title='Edit account' href='" . URL('admin/manager/studio-profile/' . $val) . "'><span class='fa fa-pencil'></span></a>&nbsp;&nbsp;<a title='Delete account' onclick=\"return confirm('Are you sure you want to delete this account?')\" href='" . URL('admin/manager/profile/delete/' . $val) . "'><span class='fa fa-trash'></span></a>";
          if($item->accountStatus != UserModel::ACCOUNT_ACTIVE){
              $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/approve/' . $val)."' title='Approve account'><i class='fa fa-check-circle-o' aria-hidden='true'></i></a>";
          }
          if($item->accountStatus != UserModel::ACCOUNT_DISABLE){
              $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/disable/' . $val) . "' title='Disable account' onclick=\"return confirm('Are you sure you want to disable this account?')\"><span class='fa fa-ban'></span></a>";
          }
          if($item->accountStatus == UserModel::ACCOUNT_ACTIVE){
              $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/suspend/' . $val)."' title='Suspend account'><i class='fa fa-exclamation-circle' aria-hidden='true'></i></a>";
          }
          return $url;
        })
      ->setSortable(false);
    }
    $grid = new Grid(
      (new GridConfig)
        ->setDataProvider(
          new EloquentDataProvider($query)
        )
        ->setName('Models')
        ->setPageSize(10)
        ->setColumns($columns)
        ->setComponents([
          (new THead)
          ->setComponents([
            (new ColumnHeadersRow),
            (new FiltersRow)
            ,
            (new OneCellRow)
            ->setRenderSection(RenderableRegistry::SECTION_END)
            ->setComponents([
              (new RecordsPerPage)
              ->setVariants([
                10,
                20,
                30,
                40,
                50,
                100,
                200,
                300,
                400,
                500
              ]),
              new ColumnsHider,
              (new CsvExport)
              ->setFileName('my_report' . date('Y-m-d'))
              ,
              (new ExcelExport())
              ->setFileName('Studio-'.  date('Y-m-d'))->setSheetName('Excel sheet'),
              (new HtmlTag)
              ->setContent('<span class="glyphicon glyphicon-refresh"></span> Filter')
              ->setTagName('button')
              ->setRenderSection(RenderableRegistry::SECTION_END)
              ->setAttributes([
                'class' => 'btn btn-success btn-sm',
                'id' => 'formFilter'
              ])
            ])
          ])
          ,
          (new TFoot)
          ->setComponents([
            (new OneCellRow)
            ->setComponents([
              new Pager,
              (new HtmlTag)
              ->setAttributes(['class' => 'pull-right'])
              ->addComponent(new ShowingRecords)
              ,
            ])
          ])
        ])
    );
        $grid = $grid->render();


    return view('Admin::studio-manager', compact('grid'));
  }

  public function getStudioPending($role = 'studio') {
    $query = UserModel
      ::leftJoin('countries as c', 'users.countryId', '=', 'c.id')
      ->select('users.*', 'users.id as check', 'users.id as action')
      // Column alias 'country_name' used to avoid naming conflicts, suggest that customers table also has 'name' column.
      ->addSelect('c.name')
      ->where('users.role', UserModel::ROLE_STUDIO)
      ->where('accountStatus', 'waiting');
    $columns = [
      (new FieldConfig)
      ->setName('check')
      ->setLabel('<input type="checkbox" name="checklist[]" class="check-all">')
      ->setCallback(function ($val) {
          return '<input type="checkbox" name="checklist[]" class="case" value="' . $val . '">';
        })
      ->setSortable(false)
      ,
      (new FieldConfig)
      ->setName('id')
      ->setLabel('ID')
      ->setSortable(true)
      ->setSorting(Grid::SORT_ASC)
      ,
      (new FieldConfig)
      ->setName('username')
      ->setLabel('Username')
      ->setCallback(function ($val) {
          return "{$val}";
        })
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('email')
      ->setLabel('Email')
      ->setSortable(true)
      ->setCallback(function ($val) {
          $icon = '<span class="glyphicon glyphicon-envelope"></span>&nbsp;';
          return
            '<small>'
            . HTML::link("mailto:$val", $val)
            . '</small>';
        })
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('tokens')
      ->setLabel('Tokens')
      ->setSortable(true)

      ,

      (new FieldConfig)
      ->setName('minPayment')
      ->setLabel('Min Payment')
      ->setSortable(true)
      ->setCallback(function($val){
          return $val . '$';
      })
      ,
      (new FieldConfig)
      ->setName('gender')
      ->setLabel('Gender')
      ->setSortable(true)
      ->addFilter(
        (new SelectFilterConfig)
        ->setName('gender')
        ->setOptions(['male'=>'Male','female'=>'Female', 'transgender' => 'Transgender'])
      )
      ,
      (new FieldConfig)
      ->setName('mobilePhone')
      ->setLabel('Phone')
      ->setSortable(true)
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ,
      (new FieldConfig)
      ->setName('name')
      ->setLabel('Country')
      ->addFilter(
        (new FilterConfig)
        ->setOperator(FilterConfig::OPERATOR_LIKE)
      )
      ->setSortable(true)
      ,
      (new FieldConfig)
      ->setName('createdAt')
      ->setLabel('reg. Date')
      ->setSortable(true)
      ->setCallback(function($val){
        $d = new \DateTime($val);
        return $d->format('M d, Y');
      })
    ];
    $adminData = AppSession::getLoginData();
    if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) {
      $columns[] = (new FieldConfig)
      ->setName('action')
      ->setLabel('Actions')
      ->setCallback(function ($val, $row) {
           $item = $row->getSrc();
          $url = "<a title='Edit account' href='" . URL('admin/manager/studio-profile/' . $val) . "'><span class='fa fa-pencil'></span></a>&nbsp;&nbsp;<a title='Delete account' onclick=\"return confirm('Are you sure you want to delete this account?')\" href='" . URL('admin/manager/profile/delete/' . $val) . "'><span class='fa fa-trash'></span></a>";
          if($item->accountStatus != UserModel::ACCOUNT_ACTIVE){
              $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/approve/' . $val)."' title='Approve account'><i class='fa fa-check-circle-o' aria-hidden='true'></i></a>";
          }
          if($item->accountStatus != UserModel::ACCOUNT_DISABLE){
              $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/disable/' . $val) . "' title='Disable account' onclick=\"return confirm('Are you sure you want to disable this account?')\"><span class='fa fa-ban'></span></a>";
          }
          if($item->accountStatus == UserModel::ACCOUNT_ACTIVE){
              $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/suspend/' . $val)."' title='Suspend account'><i class='fa fa-exclamation-circle' aria-hidden='true'></i></a>";
          }
          return $url;
        })
      ->setSortable(false);
    }
    $grid = new Grid(
      (new GridConfig)
        ->setDataProvider(
          new EloquentDataProvider($query)
        )
        ->setName('Models')
        ->setPageSize(10)
        ->setColumns([
          (new FieldConfig)
          ->setName('check')
          ->setLabel('<input type="checkbox" name="checklist[]" class="check-all">')
          ->setCallback(function ($val) {
              return '<input type="checkbox" name="checklist[]" class="case" value="' . $val . '">';
            })
          ->setSortable(false)
          ,
          (new FieldConfig)
          ->setName('id')
          ->setLabel('ID')
          ->setSortable(true)
          ->setSorting(Grid::SORT_ASC)
          ,
          (new FieldConfig)
          ->setName('username')
          ->setLabel('Username')
          ->setCallback(function ($val) {
              return "{$val}";
            })
          ->setSortable(true)
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ,
          (new FieldConfig)
          ->setName('email')
          ->setLabel('Email')
          ->setSortable(true)
          ->setCallback(function ($val) {
              $icon = '<span class="glyphicon glyphicon-envelope"></span>&nbsp;';
              return
                '<small>'
                . HTML::link("mailto:$val", $val)
                . '</small>';
            })
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ,
          (new FieldConfig)
          ->setName('tokens')
          ->setLabel('Tokens')
          ->setSortable(true)

          ,

          (new FieldConfig)
          ->setName('minPayment')
          ->setLabel('Min Payment')
          ->setSortable(true)
          ->setCallback(function($val){
              return $val . '$';
          })
          ,
          (new FieldConfig)
          ->setName('gender')
          ->setLabel('Gender')
          ->setSortable(true)
          ->addFilter(
            (new SelectFilterConfig)
            ->setName('gender')
            ->setOptions(['male'=>'Male','female'=>'Female', 'transgender' => 'Transgender'])
          )
          ,
          (new FieldConfig)
          ->setName('mobilePhone')
          ->setLabel('Phone')
          ->setSortable(true)
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ,
          (new FieldConfig)
          ->setName('name')
          ->setLabel('Country')
          ->addFilter(
            (new FilterConfig)
            ->setOperator(FilterConfig::OPERATOR_LIKE)
          )
          ->setSortable(true)
          ,
          (new FieldConfig)
          ->setName('createdAt')
          ->setLabel('reg. Date')
          ->setSortable(true)
          ->setCallback(function($val){
            $d = new \DateTime($val);
            return $d->format('M d, Y');
          })
          ,
          (new FieldConfig)
          ->setName('action')
          ->setLabel('Actions')
          ->setCallback(function ($val, $row) {
               $item = $row->getSrc();
              $url = "<a title='Edit account' href='" . URL('admin/manager/studio-profile/' . $val) . "'><span class='fa fa-pencil'></span></a>&nbsp;&nbsp;<a title='Delete account' onclick=\"return confirm('Are you sure you want to delete this account?')\" href='" . URL('admin/manager/profile/delete/' . $val) . "'><span class='fa fa-trash'></span></a>";
              if($item->accountStatus != UserModel::ACCOUNT_ACTIVE){
                  $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/approve/' . $val)."' title='Approve account'><i class='fa fa-check-circle-o' aria-hidden='true'></i></a>";
              }
              if($item->accountStatus != UserModel::ACCOUNT_DISABLE){
                  $url .= "&nbsp;&nbsp;<a href='" . URL('admin/manager/profile/disable/' . $val) . "' title='Disable account' onclick=\"return confirm('Are you sure you want to disable this account?')\"><span class='fa fa-ban'></span></a>";
              }
              if($item->accountStatus == UserModel::ACCOUNT_ACTIVE){
                  $url .= "&nbsp;&nbsp;<a href='".URL('admin/manager/profile/suspend/' . $val)."' title='Suspend account'><i class='fa fa-exclamation-circle' aria-hidden='true'></i></a>";
              }
              return $url;
            })
          ->setSortable(false)
          ,
        ])
        ->setComponents([
          (new THead)
          ->setComponents([
            (new ColumnHeadersRow),
            (new FiltersRow)
            ,
            (new OneCellRow)
            ->setRenderSection(RenderableRegistry::SECTION_END)
            ->setComponents([
              (new RecordsPerPage)
              ->setVariants([
                10,
                20,
                30,
                40,
                50,
                100,
                200,
                300,
                400,
                500
              ]),
              new ColumnsHider,
              (new CsvExport)
              ->setFileName('my_report' . date('Y-m-d'))
              ,
              (new ExcelExport())
              ->setFileName('Studio-'.  date('Y-m-d'))->setSheetName('Excel sheet'),
              (new HtmlTag)
              ->setContent('<span class="glyphicon glyphicon-refresh"></span> Filter')
              ->setTagName('button')
              ->setRenderSection(RenderableRegistry::SECTION_END)
              ->setAttributes([
                'class' => 'btn btn-success btn-sm',
                'id' => 'formFilter'
              ])
            ])
          ])
          ,
          (new TFoot)
          ->setComponents([
            (new OneCellRow)
            ->setComponents([
              new Pager,
              (new HtmlTag)
              ->setAttributes(['class' => 'pull-right'])
              ->addComponent(new ShowingRecords)
              ,
            ])
          ])
        ])
    );
        $grid = $grid->render();


    return view('Admin::studio-manager-pending', compact('grid'));
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @return Response list categories
   */
  public function getPerformerCategories() {
    return view('Admin::category-manager');
  }

  /**
   * @return view add studio view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function addStudio() {
    $bankTransferOptions = (object)[
      'withdrawCurrency' => '',
      'taxPayer' => '',
      'bankName' => '',
      'bankAddress' => '',
      'bankCity' => '',
      'bankState' => '',
      'bankZip' => '',
      'bankCountry' => '',
      'bankAcountNumber' => '',
      'bankSWIFTBICABA' => '',
      'holderOfBankAccount' => '',
      'additionalInformation' => '',
      'payPalAccount' => '',
      'checkPayable' => ''
    ];
    $directDeposit = (object)[
      'depositFirstName' => '',
      'depositLastName' => '',
      'accountingEmail' => '',
      'directBankName' => '',
      'accountType' => '',
      'accountNumber' => '',
      'routingNumber' => ''
    ];
    $paxum = (object)[
      'paxumName' => '',
      'paxumEmail' => '',
      'paxumAdditionalInformation' => ''
    ];
    $bitpay = (object)[
      'bitpayName' => '',
      'bitpayEmail' => '',
      'bitpayAdditionalInformation' => ''
    ];
    return view('Admin::add-studio', compact('bankTransferOptions', 'directDeposit', 'paxum', 'bitpay'));
  }

  /**
   * @return view add member view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function addStudioProcess() {
    $admin = AppSession::getLoginData();
    if (!$admin) {
      return Redirect('/admin/login')->with('msgError', 'Your session was expired');
    }
    $messages = array(
      'studioProff.max' => 'The file may not be greater than 2000 kilobytes',
      'studioProff.mimes' => 'The file must be a file of type: doc, docx, pdf'
    );
    $validator = Validator::make(Input::all(), [
        'username' => 'unique:users|Between:3,32|required',
        'email' => 'Required|Between:3,64|Email|Unique:users',
        'studioName' => ['Required', 'Min:2', 'Max:32'],
        'passwordHash' => 'Required|AlphaNum|Between:6,32|Confirmed',
        'passwordHash_confirmation' => 'Required|AlphaNum|Between:6,32',
        // 'gender'    => 'in:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER,
        'studioProff' => 'Max:2000|Mimes:doc,docx,pdf',
    ], $messages);

    if ($validator->fails()) {
      return back()
          ->withErrors($validator)
          ->withInput();
    }
    if (Input::file('studioProff')) {
       if (!Input::file('studioProff')->isValid()) {
           return Back()->with('msgInfo', 'uploaded file is not valid');
       }
    }
    $userData = Input::all();

    $newMember = new UserModel ();
    $newMember->studioName = preg_replace('/\s+/', ' ',  Input::get('studioName'));
    if (Input::has('gender') && !empty($userData['gender'])) {
      $newMember->gender = $userData['gender'];
    }
    $newMember->username = $userData['username'];
    $newMember->email = $userData['email'];
    $newMember->passwordHash = md5($userData['passwordHash']);

    $newMember->emailVerified = 1;
    $newMember->accountStatus = UserModel::ACCOUNT_ACTIVE;
    $newMember->role = UserModel::ROLE_STUDIO;
    $newMember->parentId = $admin->id;
    if ($newMember->save()) {
        if (Input::file('studioProff')) {
            $identityDocument = new DocumentModel;
            $identityDocument->ownerId = $newMember->id;
            $destinationPath = 'uploads/studios/proff/';
            $image = Input::file('studioProff');
            $filename = $newMember->username . '.' . $image->getClientOriginalExtension();
            $studioProff = $destinationPath . $filename;
            Input::file('studioProff')->move($destinationPath, $filename);
            $identityDocument->studioProff = $studioProff;
            $identityDocument->save();
        }
      \Event::fire(new AddEarningSettingEvent($newMember));
      UserController::postPayeeInfo($newMember->id, Input::all());
      UserController::postDirectDeposit($newMember->id, Input::all());
      UserController::postPaxum($newMember->id, Input::all());
      UserController::postBitpay($newMember->id, Input::all());
      return redirect('admin/manager/studio-profile/'.$newMember->id)->with('msgInfo', 'Studio was successfully created!');
    } else {
      return redirect()->back()->withInput()->with('msgError', 'System error.');
    }
  }

  /**
   * @author Phong Le <pt.hongphong@gmail.com>
   * @param integer $id member id
   * @return view
   */
  public function getStudioProfile($id) {
    $user = UserModel::where('id', $id)
      ->where('role', USerModel::ROLE_STUDIO)
      ->first();
    if (!$user){
      return redirect('admin/manager/performerstudios')->with('msgInfo', 'User not exist!');
    }
    $bankTransferOptions = (object)[
      'withdrawCurrency' => '',
      'taxPayer' => '',
      'bankName' => '',
      'bankAddress' => '',
      'bankCity' => '',
      'bankState' => '',
      'bankZip' => '',
      'bankCountry' => '',
      'bankAcountNumber' => '',
      'bankSWIFTBICABA' => '',
      'holderOfBankAccount' => '',
      'additionalInformation' => '',
      'payPalAccount' => '',
      'checkPayable' => ''
    ];
    $directDeposit = (object)[
      'depositFirstName' => '',
      'depositLastName' => '',
      'accountingEmail' => '',
      'directBankName' => '',
      'accountType' => '',
      'accountNumber' => '',
      'routingNumber' => ''
    ];
    $paxum = (object)[
      'paxumName' => '',
      'paxumEmail' => '',
      'paxumAdditionalInformation' => ''
    ];
    $bitpay = (object)[
      'bitpayName' => '',
      'bitpayEmail' => '',
      'bitpayAdditionalInformation' => ''
    ];
    if($user->bankTransferOptions){
      $bankTransferOptions = json_decode($user->bankTransferOptions);
    }
    if($user->directDeposit){
      $directDeposit = json_decode($user->directDeposit);
    }
    if($user->paxum){
      $paxum = json_decode($user->paxum);
    }
    if($user->bitpay){
      $bitpay = json_decode($user->bitpay);
    }
    $document = DocumentModel::where('ownerId', $id)->first();
    return view('Admin::studio-edit')->with('user', $user)->with('bankTransferOptions', $bankTransferOptions)->with('directDeposit', $directDeposit)->with('paxum', $paxum)->with('bitpay', $bitpay)->with('document', $document);
  }

  /**
   * @return view member view
   * @author Phong Le <pt.hongphong@gmail.com>
   */
  public function updateStudioProcess($id) {
    $messages = array(
      'studioProff.max' => 'The file may not be greater than 2000 kilobytes',
      'studioProff.mimes' => 'The file must be a file of type: doc, docx, pdf'
    );
    $validator = Validator::make(Input::all(), [
        'username' => 'Required|Between:3,32|alphaNum|Unique:users,username,' . $id,
        'studioName' => ['Required', 'Min:2', 'Max:32'],
        'passwordHash' => 'AlphaNum|Between:6,32',
        'tokens'    => 'Integer|Min:0',
        // 'gender'    => 'in:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER,
        'studioProff' => 'Max:2000|Mimes:doc,docx,pdf',
    ], $messages);

    if ($validator->fails()) {
      return back()
          ->withErrors($validator)
          ->withInput();
    }
    if (Input::file('studioProff')) {
      if (!Input::file('studioProff')->isValid()) {
         return Back()->with('msgInfo', 'uploaded file is not valid');
      }
    }
    $userData = Input::all();

    $member = UserModel::find($id);
    if (!$member)
      return redirect('admin/manager/performerstudios')->with('msgError', 'Studio not exist!');
    $member->studioName = preg_replace('/\s+/', ' ',  Input::get('studioName'));
    if (Input::has('gender') && !empty($userData['gender'])) {
      $member->gender = $userData['gender'];
    }
    if (Input::has('passwordHash') && !empty($userData['passwordHash'])) {
      $member->passwordHash = md5($userData['passwordHash']);
    }
    $member->username = $userData['username'];
    $member->tokens = intval(Input::get('tokens'));

    if ($member->save()) {
        if (Input::file('studioProff')) {
            $identityDocument = DocumentModel::where('ownerId', $member->id)->first();
            if (!$identityDocument) {
                $identityDocument = new DocumentModel;
                $identityDocument->ownerId = $member->id;
            }
            $destinationPath = 'uploads/studios/proff/';
            $image = Input::file('studioProff');
            $filename = $member->username . uniqid() . '.' . $image->getClientOriginalExtension();
            $studioProff = $destinationPath . $filename;
            Input::file('studioProff')->move($destinationPath, $filename);
            $identityDocument->studioProff = $studioProff;
            $identityDocument->save();
        }
      UserController::postPayeeInfo($member->id, Input::all());
      UserController::postDirectDeposit($member->id, Input::all());
      UserController::postPaxum($member->id, Input::all());
      UserController::postBitpay($member->id, Input::all());
      return back()->with('msgInfo', 'Studio was successfully updated!');
    } else {
      return redirect()->back()->withInput()->with('msgError', 'System error.');
    }
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return Response
   */
  public function create() {
//
  }

  /**
   * Store a newly created resource in storage.
   *
   * @return Response
   */
  public function store() {
//
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return Response
   */
  public function show($id) {
//
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return Response
   */
  public function edit($id) {
//
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function update($id) {
//
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return Response
   */
  public function destroy($id) {

    $user = UserModel::find($id);
    if (!$user) {
      return Back()->with('msgError', 'User not exist!');
    }
    $user->accountStatus = UserModel::ACCOUNT_DISABLE;
    if ($user->save()) {
      switch ($user->role) {
        case 'model': return Redirect('admin/manager/performers')->with('msgInfo', 'Model was successfully disabled');
          break;
        case 'studio': return Redirect('admin/manager/performerstudios')->with('msgInfo', 'Studio was successfully disabled');
          break;
        case 'member': return Redirect('admin/manager/members')->with('msgInfo', 'Member was successfully disabled');
          break;
      }
    }
    return Back()->with('msgError', 'System error.');
//check connect table and user manage
  }

  public static function postPayeeInfo($id, $options){
    $data = [
      'withdraw'  => $options['withdraw'],
      'withdrawCurrency' => $options['withdrawCurrency'],
      'taxPayer' => $options['taxPayer'],
      'bankName' => $options['bankName'],
      'bankAddress' => $options['bankAddress'],
      'bankCity' => $options['bankCity'],
      'bankState' => $options['bankState'],
      'bankZip' => $options['bankZip'],
      'bankCountry' => $options['bankCountry'],
      'bankAcountNumber' => $options['bankAcountNumber'],
      'bankSWIFTBICABA' => $options['bankSWIFTBICABA'],
      'holderOfBankAccount' => $options['holderOfBankAccount'],
      'additionalInformation' => $options['additionalInformation'],
      'payPalAccount' => $options['payPalAccount'],
      'checkPayable' => $options['checkPayable']
    ];
    $model = UserModel::find($id);
    $model->bankTransferOptions = json_encode($data);
    $model->save();
  }

  public static function postDirectDeposit($id, $options){
    $data = [
      'depositFirstName' => $options['depositFirstName'],
      'depositLastName' => $options['depositLastName'],
      'accountingEmail' => $options['accountingEmail'],
      'directBankName' => $options['directBankName'],
      'accountType' => $options['accountType'],
      'accountNumber' => $options['accountNumber'],
      'routingNumber' => $options['routingNumber']
    ];

    $model = UserModel::find($id);
    $model->directDeposit = json_encode($data);
    $model->save();
  }

  public static function postPaxum($id, $options){
    $data = [
      'paxumName' => $options['paxumName'],
      'paxumEmail' => $options['paxumEmail'],
      'paxumAdditionalInformation' => $options['paxumAdditionalInformation']
    ];
    $model = UserModel::find($id);
    $model->paxum = json_encode($data);
    $model->save();
  }
  public static function postBitpay($id, $options){
    $data = [
      'bitpayName' => $options['bitpayName'],
      'bitpayEmail' => $options['bitpayEmail'],
      'bitpayAdditionalInformation' => $options['bitpayAdditionalInformation']
    ];
    $model = UserModel::find($id);
    $model->bitpay = json_encode($data);
    $model->save();
  }

}
