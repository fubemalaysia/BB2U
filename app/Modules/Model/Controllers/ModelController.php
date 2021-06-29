<?php

namespace App\Modules\Model\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Controllers\AuthController;
use App\Modules\Api\Controllers\UserController;
use App\Modules\Api\Controllers\RoomController;
use App\Modules\Api\Controllers\PerformerChatController;
use App\Modules\Model\Models\PerformerTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use \Firebase\JWT\JWT;
use App\Modules\Api\Models\UserModel;
use App\Modules\Api\Models\PerformerModel;
use App\Modules\Api\Models\PerformerChatModel;
use App\Modules\Api\Models\MessageModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use App\Helpers\Session as AppSession;
use App\Helpers\Helper as AppHelper;
use App\Modules\Api\Models\AttachmentModel;
use App\Modules\Api\Models\CountryModel;
use App\Modules\Api\Models\ZoneModel;
use App\Modules\Api\Models\ScheduleModel;
use App\Modules\Api\Models\EarningSettingModel;
use App\Modules\Api\Models\EarningModel;
use App\Modules\Api\Models\MeetHerModel;
use App\Modules\Api\Models\ChatThreadModel;
use App\Modules\Api\Models\CategoryModel;
use App\Modules\Api\Models\DocumentModel;
use App\Modules\Api\Models\FavoriteModel;
use DB;
use App\Modules\Api\Models\GeoBlockingModel;

class ModelController extends Controller {

  /**
   * Display a listing of the resource.
   *
   * @return Response
   */
  public function index() {

    return view("Model::index");
  }

  /*
   * view settings page
   */

  public function chatSettings() {
    //check if is login and is model
    $userData = AppSession::getLoginData();
    if (!$userData) {
      return redirect::to('/login')->with('message', 'Please login with model role');
    }
    if ($userData->role == 'model') {
      return view("Model::chat-settings", [
        'userData' => $userData
      ]);
    } else {
      return redirect::to('/');
    }
  }

  /*
   * Chat room
   * roomId is model id
   */

  public function chatRoom($roomId) {
    //get room data
    $auth = new AuthController();
    $user = new UserController();
//    $room = new RoomController();
//    var_dump($user->findMe());
//    if ($auth->isLogin()) {
//
//      return view("Model::chat-room", [
//        'turnConfig' => $data->info(),
//        'userData' => $user->findMe(),
//        'isRoom' => $room->checkRoom($roomId)
//      ]);
//    } else {
//      return redirect::to('/login')->with('message', 'Please login with model role');
//    }
    $userData = [];
    $userD = $user->findMe($auth->isLogin());


//get turn servers info
    //check if room exit return true else false;
    $room = new RoomController();
    $performerChat = new PerformerChatController();

    if ($auth->isLogin()) {

      return view("Model::chat-room", [
        'userData' => $user->findMe(),
        'isAnonymous' => false,
        'PerformerChat' => $performerChat->getPerformerChat('model', $roomId)
      ]);
    } else {
      $tokenId = rand();
      $issuedAt = time();
      $notBefore = $issuedAt + 10;             //Adding 10 seconds
      $expire = $notBefore * 6000;            // Adding 60 seconds
      $serverName = 'localhost'; // Retrieve the server name from config file

      $data = [
        'iat' => $issuedAt, // Issued at: time when the token was generated
        'jti' => $tokenId, // Json Token Id: an unique identifier for the token
        'iss' => $serverName, // Issuer
        'nbf' => $notBefore, // Not before
        'exp' => $expire, // Expire
        'data' => [                  // Data related to the signer user
          'userId' => 0, // userid from the users table
          'userName' => 'anonymous', // User name
        ]
      ];

      $secretKey = '12345';

      /*
       * Encode the array to a JWT string.
       * Second parameter is the key to encode the token.
       *
       * The output string can be validated at http://jwt.io/
       */
      $jwt = JWT::encode(
          $data, //Data to be encoded in the JWT
          $secretKey, // The signing key
          'HS512'     // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
      );
      return view("Model::chat-room", [
        'turnConfig' => AppHelper::getTurnInfo(),
        'userData' => ['id' => 0, 'username' => 'anonymous', 'premium' => 'no', 'role' => 'guest', 'token' => $jwt],
        'roomId' => $roomId,
        'isRoom' => $room->checkRoom($roomId),
        'isAnonymous' => true
      ]);
    }
  }

  /**
   * Show Message Inbox.
   *
   * @return Response
   */
  public function getMessageBox() {
    $userData = AppSession::getLoginData();
    $memberMessage = MessageModel::select('messages.*', 'users.username', 'users.firstName', 'users.lastName', 'users.avatar')
      ->where('messages.messagetoId', '=', $userData->id)
      ->where('messages.status', MessageModel::SENT)
      ->join('users', 'users.id', '=', 'messages.messageformId')
      ->orderby('createdAt', 'desc')
      ->groupby('messages.messageformId')
      ->paginate(LIMIT_PER_PAGE);
    return view('Model::model_dashboard_messages')->with('msgInbox', $memberMessage);
  }

  /*
   * Show model schedule
   * #return response
   *    */

  public function mySchedule() {
    $userData = AppSession::getLoginData();
    $schedule = null;

    if ($userData && $userData->role == 'model') {
      $schedule = ScheduleModel::where('modelId', $userData->id)->first();
    }
    $schedules = [
      'monday' => $schedule->monday,
      'tuesday' => $schedule->tuesday,
      'wednesday' => $schedule->wednesday,
      'thursday' => $schedule->thursday,
      'friday' => $schedule->friday,
      'saturday' => $schedule->saturday,
      'sunday' => $schedule->sunday
    ];

    $currentKey = lcfirst(date('l', strtotime('today')));
    $nextSchedule = null;

    if ($schedules[$currentKey] > date('H:i:s') && array_key_exists($currentKey, array_filter($schedules))) {

      $nextSchedule = date('Y-m-d') . ' ' . date('H:i', strtotime($schedules[$currentKey]));
    } else {
      for ($i = 1; $i < 7; $i++) {
//          echo date('l', strtotime($currentKey . " +{$i} + day"));
        $nextKey = lcfirst(date('l', strtotime($currentKey . " +{$i} day")));
        if (array_key_exists($nextKey, array_filter($schedules))) {
          $nextSchedule = date('Y-m-d', strtotime($currentKey . " +{$i} day")) . ' ' . date('H:i', strtotime($schedules[$nextKey]));
          break;
        }
      }
    }
    return view('Model::model_dashboard_schedule')->with('mySchedule', $schedule)->with('nextSchedule', $nextSchedule);
  }

  /*
   * Edit
   * @return schedule data
   */

  public function editSchedule() {
    $userData = AppSession::getLoginData();
    $schedule = null;

    if ($userData && $userData->role == 'model') {
      $schedule = ScheduleModel::where('modelId', $userData->id)->first();
    }
    return view('Model::model_dashboard_schedule_edit')->with('mySchedule', $schedule);
  }

  public function postSchedule(){
      $userData = AppSession::getLoginData();

      $inputData = Input::all();
      $schedule = (Input::has('id') && $inputData['id'] != null) ? ScheduleModel::findOrFail(Input::get('id')) : new ScheduleModel();

      $schedule->modelId = $userData->id;
//      $schedule->nextLiveShow = ($inputData['nextLiveShow'] != '') ? $inputData['nextLiveShow'] : null;
      $schedule->timezone = ($inputData['timezone'] != '') ? $inputData['timezone'] : '+00:00';
      $schedule->timezoneDetails = $inputData['timezoneDetails'];
      $schedule->monday = ($inputData['monday'] != '') ? $inputData['monday'] : null;
      $schedule->tuesday = ($inputData['tuesday'] != '') ? $inputData['tuesday'] : null;
      $schedule->wednesday = ($inputData['wednesday'] != '') ? $inputData['wednesday'] : null;
      $schedule->thursday = ($inputData['thursday'] != '') ? $inputData['thursday'] : null;
      $schedule->friday = ($inputData['friday'] != '' ) ? $inputData['friday'] : null;
      $schedule->saturday = ($inputData['saturday']) ? $inputData['saturday'] : null;
      $schedule->sunday = ($inputData['sunday'] != '') ? $inputData['sunday'] : null;
//      $schedule->fill($inputData);
      if($schedule->save()){
          return redirect('models/dashboard/schedule')->with('msgInfo', 'Schedule was successfully updated.');
      }
      return back()->withInput()->with('msgError', 'System error, cannot save.');
  }

  /*   * *
   * TODO -- filter model payments
   * * */

  public function myEarnings() {
    return view('Model::model_dashboard_earnings');
  }

  /**
    TODO: Member Dashboard profile
   * */
  public function getMyProfile() {
    $userData = AppSession::getLoginData();
   
    
    $model = UserModel::select('users.*','p.country_id', 'p.age', 'p.sex', 'p.sexualPreference', 'p.ethnicity', 'p.eyes', 'p.hair', 'p.height', 'p.weight', 'p.languages', 'p.pubic', 'p.bust', 'p.state_name', 'p.city_name', 'p.about_me', 'p.blog', DB::raw('IF(p.country_id = ct.id, ct.name, "Unknown") as countryName'))
            ->join('performer as p', 'p.user_id', '=', 'users.id')
            ->leftJoin('countries as ct', 'ct.id', '=', 'p.country_id')
//            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('users.id', $userData->id)
            ->with('categories')
            ->first();
    return view('Model::model_dashboard_profile', compact('model'));
  }

  /**
    TODO: Member Dashboard profile
   * */
  public function getProfileImages() {
    $getUserLogin = AppSession::getLoginData();
    if (!$getUserLogin) {
      return redirect('login');
    }
    $profileImages = AttachmentModel::where('media_type', 'profile')
      ->where('owner_id', $getUserLogin->id)
      ->get();

    return view('Model::model_dashboard_profile_view_images')->with('profileImages', $profileImages);
  }

  /**
    TODO: Member Dashboard profile
   * */
  public function getEditProfile() {
       $getUserLogin = AppSession::getLoginData();
    if (!$getUserLogin) {
      return redirect('login');
    }
    $user = UserModel::find($getUserLogin->id);
    $countries = CountryModel::orderBy('name')->lists('name', 'id')->all();
    $performer = PerformerModel::where('user_id', $user->id)->first();
    $cat = $user->categories->pluck('id')->toArray();
    if(empty($cat) && !empty($performer->category_id)){
        $cat = [$performer->category_id];
    }
    $heightList = UserModel::getHeightList();
    $weightList = UserModel::getWeightList();
   
    $categories = CategoryModel::orderBy('name')->lists('name', 'id')->all();
    return view('Model::model_dashboard_profile_edit', compact('countries', 'user', 'performer', 'heightList', 'weightList', 'categories', 'cat'));
  }

  /**
    TODO: Member Update Profile
   * */
  public function updateProfile() {

    $rules = [
      'firstName'           => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
      'lastName'            => ['Required', 'Min:2', 'Max:32', 'Regex:/^[A-Za-z(\s)]+$/'],
      'gender'              => 'Required|in:'.UserModel::GENDER_MALE.','.UserModel::GENDER_FEMALE.','.UserModel::GENDER_TRANSGENDER,
      'sexualPreference'    => 'Required',
      'age' => 'Required|Integer|Min:18|Max:100',
      'ethnicity'           => 'String',
      'eyes'                => 'Alpha',
      'hair'                => 'Alpha',
      'height'              => 'Required',
      'weight'              => 'Required',
      'country'             => 'Required',
      'state_name'          => 'Required|String|Max:100',
      'city_name'           => 'Required|String|Max:100',
      'about_me'            => 'String|Max:500',
      'status'              => 'String|Max:144',
      'blogname'            => 'String|Max:100',
      'blog'                => 'active_url|Max:255',
      'languages'           => 'String',
      'tags' => 'string',
      'category' => 'Required'
    ];
    $validator = Validator::make(Input::all(), $rules);
    if ($validator->fails()) {
      return back()->withErrors($validator)->withInput();
    }

    $userData = AppSession::getLoginData();

    $user = UserModel::find($userData->id);
    if (!$user){
        AppSession::getLogout();
      return redirect('login')->with('msgError', 'Your account does not found.');
    }
    $user->firstName    = preg_replace('/\s+/', ' ',  Input::get('firstName'));
    $user->lastName    = preg_replace('/\s+/', ' ',  Input::get('lastName'));
    $user->gender       = Input::get('gender');
    $user->bio          = Input::get('about_us');
    $user->status       = preg_replace('/\s+/', ' ',  Input::get('status'));

    if ($user->save()) {
        $user->categories()->sync(Input::get('category'));

        $performer = PerformerModel::where('user_id', '=', $user->id)->first();
        if (!$performer) {
          $performer = new PerformerModel;
        }

        $performer->sex               = Input::get('gender');
        $performer->sexualPreference  = Input::get('sexualPreference');
        $performer->age               = Input::get('age');
        $performer->ethnicity         = Input::get('ethnicity', null);
        $performer->eyes              = Input::get('eyes');
        $performer->hair              = Input::get('hair');
        $performer->height            = Input::get('height');
        $performer->weight            = Input::get('weight');
        $performer->category_id       = null;
        $performer->pubic             = Input::get('pubic');
        $performer->bust              = Input::get('bust');
        $performer->languages         = Input::get('languages');
        $performer->country_id        = Input::get('country');
        $performer->state_name        = Input::get('state_name');
        $performer->city_name         = Input::get('city_name');
        $performer->about_me          = Input::get('about_me');

        $performer->blogname          = preg_replace('/\s+/', ' ',  Input::get('blogname'));
        $performer->blog              = Input::get('blog');
        $performer->tags = Input::get('tags');


        if ($performer->save()) {
            PerformerTag::updateTags($performer->id, $performer->tags);
          return redirect('models/dashboard/profile')->with('msgInfo', 'Profile was successfully updated!');
        }
    }
    return back()->with('msgError', 'System error for update profile!');

  }

  /**
    TODO: Member Update Profile
   * */
  public function postUpdateProfile(Request $get) {
    $getUserLogin = AppSession::getLoginData();
    if (!$getUserLogin) {
      return redirect::to('/login');
    }

    $bio = $get->aboutMe;
    $location_id = $get->country;
    $file = $get->file('avatar');
    $userMetaPost = array(
      'visible' => $get->visible,
      'state' => $get->state,
      'city' => $get->city,
      'age' => $get->age,
      'starSign' => $get->starSign,
      'eyesColor' => $get->eyesColor,
      'hairColor' => $get->hairColor,
      'height' => $get->height,
      'ethnicity' => $get->ethnicity,
      'build' => $get->build,
      'appearance' => $get->appearance,
      'marital' => $get->marital,
      'orient' => $get->orient,
      'looking' => $get->looking,
    );
    $updateProfile = UserModel::find($getUserLogin->id);
    $updateProfile->userMeta = serialize($userMetaPost);
    $updateProfile->bio = $bio;
    $updateProfile->location_id = $location_id;
    if ($file) {
      $extension = $file->getClientOriginalExtension();
      $notAllowed = array("exe", "php", "asp", "pl", "bat", "js", "jsp", "sh", "doc", "docx", "xls", "xlsx");
      $destinationPath = $_SERVER['DOCUMENT_ROOT'] . PATH_IMAGE . "upload/member/";
      $filename = "avatar_member_" . $getUserLogin->id . "." . $extension;
      $fileNameLarge = "avatar_member_large_" . $getUserLogin->id . "." . $extension;
      $fileNameMedium = "avatar_member_medium_" . $getUserLogin->id . "." . $extension;
      $fileNameSmall = "avatar_member_small_" . $getUserLogin->id . "." . $extension;
      if (!in_array($extension, $notAllowed)) {
        $file->move($destinationPath, $filename);
        $resizeImage = new AppImage($destinationPath . $filename);
        $imageLage = $resizeImage->resize(800, 600)->save($destinationPath . $fileNameLarge);
        $imageMedium = $resizeImage->resize(400, 300)->save($destinationPath . $fileNameMedium);
        $imageSmall = $resizeImage->resize(100, 100)->save($destinationPath . $fileNameSmall);
        $profileImage = array(
          'imageLarge' => $fileNameLarge,
          'imageMedium' => $fileNameMedium,
          'imageSmall' => $fileNameSmall,
          'normal' => $filename
        );
        $updateProfile->avatar = serialize($profileImage);
        $updateProfile->smallAvatar = $fileNameSmall;
      }
    }
    if ($updateProfile->save()) {
      return redirect('models/dashboard/profile')->with('msgInfo', 'Your profile was successfully updated.');
    } else {
      return redirect('models/dashboard/profile')->with('msgError', 'System error cannot update profile.');
    }
  }

  /**
    TODO: Get Member Settings
   * */
  public function getMySettings(Request $req) {
    $zone = null;
    $userData = AppSession::getLoginData();
    $otherSettings = null;
    $contact = null;
    switch ($req->get('action')) {
      case 'others':
        $zone = ZoneModel::orderBy('zone_name')->get();
        $me = UserModel::find($userData->id);

        if (AppHelper::is_serialized($me->userSettings)) {
          $otherSettings = json_encode(unserialize($me->userSettings));
        }

        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('timezone', $zone)->with('otherSettings', $otherSettings);
        break;
      case 'contact':
        $contact = UserModel::leftJoin('countries', 'users.countryId', '=', 'countries.id')
          ->select('users.*', 'countries.name as countryName', 'stateName')
          ->where('users.id', $userData->id)
          ->first();

        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('contact', $contact);
        break;
      case 'edit-contact':
        $contact = UserModel::leftJoin('countries', 'users.countryId', '=', 'countries.id')
          ->select('users.*', 'countries.name as countryName', 'stateName')
          ->where('users.id', $userData->id)
          ->first();
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('contact', $contact);
        break;
      case 'payment':
        $paymentInfo = UserModel::find($userData->id);

        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('paymentInfo', $paymentInfo);
        break;
      case 'payee-info':
        $model = UserModel::find($userData->id);
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
        if($model->bankTransferOptions){
          $bankTransferOptions = json_decode($model->bankTransferOptions);
        }
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('bankTransferOptions', $bankTransferOptions);
        break;
      case 'direct-deposity':
        $model = UserModel::find($userData->id);
        $directDeposit = (object)[
          'depositFirstName' => '',
          'depositLastName' => '',
          'accountingEmail' => '',
          'directBankName' => '',
          'accountType' => '',
          'accountNumber' => '',
          'routingNumber' => ''
        ];
        if($model->directDeposit){
          $directDeposit = json_decode($model->directDeposit);
        }
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('directDeposit', $directDeposit);
        break;
      case 'paxum':
        $model = UserModel::find($userData->id);
        $paxum = (object)[
          'paxumName' => '',
          'paxumEmail' => '',
          'paxumAdditionalInformation' => ''
        ];
        if($model->paxum){
          $paxum = json_decode($model->paxum);
        }
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('paxum', $paxum);
        break;
      case 'bitpay':
        $model = UserModel::find($userData->id);
        $bitpay = (object)[
          'bitpayName' => '',
          'bitpayEmail' => '',
          'bitpayAdditionalInformation' => ''
        ];
        if($model->bitpay){
          $bitpay = json_decode($model->bitpay);
        }
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('bitpay', $bitpay);
        break;
      case 'edit-payment':
        $paymentInfo = UserModel::select('minPayment', 'payoneer', 'bankAccount', 'paypal')->where('id', $userData->id)->first();
          if(!$paymentInfo){
              return redirect('404')->with('msgError', 'Payment settings not found.');
          }
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('paymentInfo', $paymentInfo);
        break;
      case 'commissions':
        $commission = EarningSettingModel::where('userId', $userData->id)
          ->first();
          if(!$commission){
              $commission = new EarningSettingModel();
              $commission->userId = $userData->id;
              $commission->save();
          }
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('commission', $commission);
        break;
      case 'documents':
          $document = DocumentModel::where('ownerId', $userData->id)->first();
          return view('Model::model_dashboard_settings')->with('action', $req->get('action'))->with('document', $document);
      default:
        return view('Model::model_dashboard_settings')->with('action', $req->get('action'));
        break;
    }
  }

  /**
   * @return Response
   * @description update setting
   */
  public function updateDocumentSetting() {

    $userData = AppSession::getLoginData();
    if (!$userData) {
      return Redirect('login')->With('msgError', 'Your session was expired.');
    }
    $validator = Validator::make(Input::all(), [
        'idImage' => 'Mimes:jpg,jpeg,png',
        'faceId' => 'Mimes:jpg,jpeg,png',
    ]);

    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }

    $identityDocument = DocumentModel::where('ownerId', $userData->id)->first();
    if (!$identityDocument) {
      $identityDocument = new DocumentModel;
    }
    $identityDocument->ownerId = $userData->id;
    $destinationPath = 'uploads/models/identity/'; // upload path
    if (Input::file('idImage')) {
      // checking file is valid.
      if (!Input::file('idImage')->isValid()) {
        return Back()->with('msgInfo', 'uploaded file is not valid');
      }

      $image = Input::file('idImage');
      $filename = $userData->username . '.' . $image->getClientOriginalExtension();

      $idPath = $destinationPath . 'id-images/' . $filename;

      Input::file('idImage')->move($destinationPath . 'id-images', $filename);
      $identityDocument->idImage = $idPath;
    }
    if (Input::file('faceId')) {
      // checking file is valid.
      if (!Input::file('faceId')->isValid()) {
        return Back()->with('msgInfo', 'uploaded file is not valid');
      }

      $image = Input::file('faceId');
      $filename = $userData->username . '.' . $image->getClientOriginalExtension();

      $facePath = $destinationPath . 'face-ids/' . $filename;

      Input::file('faceId')->move($destinationPath . 'face-ids', $filename);
      $identityDocument->faceId = $facePath;
    }
    if ($identityDocument->save()) {
      return Back()->with('msgInfo', 'Your document was successfully updated.');
    }
    return Back()->with('msgError', 'System error.');
  }

  public function postPayeeInfo(){
    $userData = AppSession::getLoginData();
    if (!$userData) {
      return Redirect('login')->With('msgError', 'Your session was expired.');
    }
    $rules = [
      'withdrawCurrency' => 'Required|String',
      'taxPayer' => 'String',
      'bankName' => 'Required|String',
      'bankAddress' => 'Required|String',
      'bankCity' => 'Required|String',
      'bankState' => 'Required|String',
      'bankZip' => 'Required|String',
      'bankCountry' => 'Required|String',
      'bankAcountNumber' => 'Required|String',
      'bankSWIFTBICABA' => 'Required|String',
      'holderOfBankAccount' => 'Required|String',
      'additionalInformation' => 'String'
    ];
    if(Input::get('withdraw') === 'paypal'){
      $rules = [
          'payPalAccount' => 'Required|String'
      ];
    }elseif(Input::get('withdraw') === 'check'){
      $rules = [
          'checkPayable' => 'Required|String'
      ];
    }
    $validator = Validator::make(Input::all(), $rules);
    
    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }
    $model = UserModel::find($userData->id);
    $model->bankTransferOptions = json_encode(Input::all());
    if ($model->save()) {
      return Back()->with('msgInfo', 'Your document was successfully updated.');
    }
    return Back()->with('msgError', 'System error.');
  }

  public function postDirectDeposity(){
    $userData = AppSession::getLoginData();
    if (!$userData) {
      return Redirect('login')->With('msgError', 'Your session was expired.');
    }
    $rules = [
      'depositFirstName' => 'Required|String',
      'depositLastName' => 'Required|String',
      'accountingEmail' => 'Email|Required|String',
      'directBankName' => 'Required|String',
      'accountType' => 'Required|String',
      'accountNumber' => 'Required|String',
      'routingNumber' => 'Required|String'
    ];

    $validator = Validator::make(Input::all(), $rules);

    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }
    $model = UserModel::find($userData->id);
    $model->directDeposit = json_encode(Input::all());
    if ($model->save()) {
      return Back()->with('msgInfo', 'Your document was successfully updated.');
    }
    return Back()->with('msgError', 'System error.');
  }

  public function postPaxum(){
    $userData = AppSession::getLoginData();
    if (!$userData) {
      return Redirect('login')->With('msgError', 'Your session was expired.');
    }
    $rules = [
      'paxumName' => 'Required|String',
      'paxumEmail' => 'Email|Required|String',
      'paxumAdditionalInformation' => 'Required|String'
    ];

    $validator = Validator::make(Input::all(), $rules);

    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }
    $model = UserModel::find($userData->id);
    $model->paxum = json_encode(Input::all());
    if ($model->save()) {
      return Back()->with('msgInfo', 'Your document was successfully updated.');
    }
    return Back()->with('msgError', 'System error.');
  }
  public function postBitpay(){
    $userData = AppSession::getLoginData();
    if (!$userData) {
      return Redirect('login')->With('msgError', 'Your session was expired.');
    }
    $rules = [
      'bitpayName' => 'Required|String',
      'bitpayEmail' => 'Email|Required|String',
      'bitpayAdditionalInformation' => 'Required|String'
    ];

    $validator = Validator::make(Input::all(), $rules);

    if ($validator->fails()) {
      return Back()
          ->withErrors($validator)
          ->withInput();
    }
    $model = UserModel::find($userData->id);
    $model->bitpay = json_encode(Input::all());
    if ($model->save()) {
      return Back()->with('msgInfo', 'Your document was successfully updated.');
    }
    return Back()->with('msgError', 'System error.');
  }

  /**
   * Display a Model community.
   *
   * @return Response
   */
  public function getCommunity() {
    return view('community.community');
  }

  /**
   * Display a Model profile.
   *
   * @return Response
   */
  public function getModelProfile(UserModel $user) {

    $loginData = AppSession::getLoginData();
    if($loginData && $loginData->role == UserModel::ROLE_MODEL){
      return redirect('/models/live');
    }
     // country code for the client's ip address
    $code = AppHelper::getCountryCodeFromClientIp(); 
    if($code){
        $geo = GeoBlockingModel::where('iso_code', '=', $code)
                ->where('userId', $user->id)
                ->where('isBlock', GeoBlockingModel::isBlock)
                ->first();
        if($geo){
            return redirect('/')->with('msgError', trans('messages.blockCountry'));
        }
    }

    $memberId = ($loginData) ? $loginData->id : 0;


//    $performer = PerformerModel::select('performer.*', 'users.username', 'users.avatar', DB::raw('(select categories.name from categories where categories.id=performer.category_id) as categoryName'), DB::raw('(select countries.name from countries where countries.id = performer.country_id) as countryName'), 'state_name', 'city_name', DB::raw("(SELECT f.status FROM favorites f WHERE f.favoriteId={$user->id} AND f.ownerId={$memberId}) as favorite"))
//      ->join('users', 'users.id', '=', 'performer.user_id')
//      ->where('user_id', $user->id)
//      ->first();
//    if (!$performer) {
//      return Back()->with('msgError', 'Performer chat does not setting.');
//    }
    //SELECT f.status FROM favorites f WHERE f.favoriteId={$user->id} AND f.ownerId={$memberId}
    $favorite = FavoriteModel::select('status')
            ->where('favoriteId', $user->id)
            ->where('ownerId', $memberId)
            ->first();


    $schedules = [
      'monday' => $user->schedule->monday,
      'tuesday' => $user->schedule->tuesday,
      'wednesday' => $user->schedule->wednesday,
      'thursday' => $user->schedule->thursday,
      'friday' => $user->schedule->friday,
      'saturday' => $user->schedule->saturday,
      'sunday' => $user->schedule->sunday
    ];

    $currentKey = lcfirst(date('l', strtotime('today')));
    $nextSchedule = null;

    if ($schedules[$currentKey] > date('H:i:s') && array_key_exists($currentKey, array_filter($schedules))) {

      $nextSchedule = date('Y-m-d') . ' ' . date('H:i', strtotime($schedules[$currentKey]));
    } else {
      for ($i = 1; $i < 7; $i++) {
//          echo date('l', strtotime($currentKey . " +{$i} + day"));
        $nextKey = lcfirst(date('l', strtotime($currentKey . " +{$i} day")));
        if (array_key_exists($nextKey, array_filter($schedules))) {
          $nextSchedule = date('Y-m-d', strtotime($currentKey . " +{$i} day")) . ' ' . date('H:i', strtotime($schedules[$nextKey]));
          break;
        }
      }
    }


    $room = ChatThreadModel::where('type', ChatThreadModel::TYPE_PUBLIC)
      ->where('ownerId', $user->id)
      ->first();
    if (!$room) {
      $room = new ChatThreadModel;
      $room->ownerId = $user->id;
      $room->type = ChatThreadModel::TYPE_PUBLIC;
      $room->virtualId = md5(time());

      if (!$room->save()) {
        return Back()->with('msgError', 'Create room error.');
      }
    }
    $chatSetting = PerformerChatModel::select('welcome_message')
      ->where('model_id', $user->id)
      ->first();

    $domain = $_SERVER['HTTP_HOST'];

    $country = CountryModel::find($user->performer->country_id);
    $countryName = '';
    if($country) {
      $countryName = $country->name;
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
    return view('Model::profile_detail')
            ->with('room', $room->id)
            ->with('virtualRoom', $room->virtualId)
            ->with('favorite', $favorite)
            ->with('modelId', $user->id)
            ->with('memberId', $memberId)
            ->with('schedule', $user->schedule)
            ->with('model', $user)
            ->with('nextSchedule', $nextSchedule)
            ->with('welcome_message', $chatSetting->welcome_message)
            ->with('title', "Chat with {$user->username} in {$domain} Live Adult Webcam Room now")
            ->with('countryName', $countryName)->with('hotdata', $hotdata)->with('categoriesdata', $categoriesdata)->with('talents', $talents);
  }

  /**
   * Display list model by category.
   *
   * @return Response
   */
  public function getModelByCategory($name) {
    $category = CategoryModel::where('slug', '=', $name)->first();
    if (!$category) {
      return Redirect('/')->with('msgError', 'Category not found.');
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
	 
  //  return view('home.index',compact('hotdata','categoriesdata','talents'));
    return view('Model::list_model_by_category')->with('category', $category)->with('hotdata', $hotdata)->with('categoriesdata', $categoriesdata)->with('talents', $talents);
  }

  /**
   * * */
  public function getMemberProfile() {
    //--TODO Check current user if member return to member page
    $userData = AppSession::getLoginData();

//
//    $feeds = Feed::select('posts.id as feedId', 'users.id as userId', 'users.username', 'users.firstname', 'users.lastname', 'users.avatar', 'posts.title', 'posts.text', 'posts.owner_id', 'posts.createdAt', 'posts.updatedAt')
//      ->join('users', 'users.id', '=', 'posts.owner_id')
//      ->orderBy('posts.createdAt', 'desc')
//      ->where('owner_id', $userData->id)
//      ->paginate(LIMIT_PER_PAGE);
    //$feeds->setPath('models/dashboard');

    return view('Model::model_profile_sub_wall')->with('userData', $userData)->with('ownerId', $userData->id);
  }

  public function modelDashboard() {
    return view('Model::model_dashboard');
  }

  /**
   * @Action paid model album image
   * @Author LongPham <long.it.stu@gmail.com>
   * */
  public function paidAllbumImage(Request $get) {
    if (\Request::ajax()) {
      $userData = AppSession::getLoginData();
      $checkItemExisting = EarningModel::where('itemId', '=', $get->galleryId)->where('item', '=', $get->paymentItem)->where('payFrom', '=', $userData->id)->first();
      $CheckMemberTokens = UserModel::find($userData->id);
      $getModel = UserModel::find($get->paidToId);
      if (!empty($checkItemExisting)) {
        return response()->json([
            'success' => true,
            'message' => 'You have already purchased',
            ], 200);
      } else {
        if ($CheckMemberTokens->tokens >= $get->paidPrice) {
          $newPaid = new EarningModel();
          $newPaid->item = $get->paymentItem;
          $newPaid->itemId = $get->galleryId;
          $newPaid->payFrom = $userData->id;
          $newPaid->payTo = $get->paidToId;
          $newPaid->tokens = $get->paidPrice;
          $newPaid->status = 'paid';
          if ($newPaid->save()) {
            AppHelper::updateMemberTokens($CheckMemberTokens->tokens, $get->paidPrice);
            AppHelper::updateModelTokens($getModel->id, $getModel->tokens, $get->paidPrice);
            return response()->json([
                'success' => true,
                'message' => 'Purchased successful',
                ], 200);
          }
        } else {
          return response()->json([
              'success' => false,
              'message' => 'Your account does not have enough tokens',
              ], 200);
        }
      }
    } else {
      return redirect()->back()->with('msgError', 'Request Not found!');
    }
  }

  /**
   * Show resource meet here
   * @return resource
   * @author LongPham <long.it.stu@gmail.com>
   * */
  public function getMeetHer() {
    return view('Model::meet_her');
  }

  /**
   * Action Search autocomplete model
   * @return resource
   * @author LongPham <long.it.stu@gmail.com>
   * */
  public function getSearchModel() {
    if (!\Request::ajax()) {
      return redirect()->back()->with('msgError', 'Method not allowed');
    }

    $action = \Request::only('modelname');
    if (empty($action['modelname'])) {
      //
    }
    if (!AppSession::isLogin()) {
      return response()->json([
          'success' => false,
          'message' => 'Your account has expired.',
          ], 404);
    }
    $userLogin = AppSession::getLoginData();
    if ($userLogin->role == UserModel::ROLE_MODEL) {
      $searchRole = UserModel::ROLE_MEMBER;
    } else {
      $searchRole = UserModel::ROLE_MODEL;
    }
    $getModel = UserModel::where('username', 'like', '%' . $action['modelname'] . '%')->where('role', '=', $searchRole)->get();
    $html = '';
    $html .= '<table class="table table-condensed" style="border-radius: 4px">';

    if (!empty($getModel)) {
      foreach ($getModel as $key => $value) {
        $html .= '<tr class="insertThis" style="cursor:pointer" modelName = "' . $value->username . '" ><td class="info" style="color:#df6026"><i class="fa fa-user"></i> ' . $value->username . '</td></tr>';
      }
    }

    $html .= '</table>';
    return $html;
  }

  /**
   * Action Created Meet Her
   * @return resource
   * @author LongPham <long.it.stu@gmail.com>
   * */
  public function postMeetHer(Request $get) {
    $rules = [
      'country' => 'required',
      'startDate' => 'required|date',
      'endDate' => 'required|date|after:startDate',
      'modelName' => 'required',
      'requestContent' => 'required',
    ];
    $validator = Validator::make(Input::all(), $rules);
    if ($validator->fails()) {
      return back()->withErrors($validator)->withInput();
    }
    $country = $get->country;
    $startDate = $get->startDate;
    $endDate = $get->endDate;
    $modelName = $get->modelName;
    $request = $get->requestContent;
    $userLogin = AppSession::getLoginData();
    $model = UserModel::where('username', '=', $modelName)->first();
    if (empty($model)) {
      return redirect()->back()->with('msgError', 'Model not found');
    }
    $checkExisting = MeetHerModel::where('himId', '=', $userLogin->id)
        ->where('herId', '=', $model->id)->where('status', '=', MeetHerModel::WAITING)->first();
    if (!empty($checkExisting)) {
      return redirect()->back()->with('msgError', 'You have sent a request to ' . $modelName . '. Please wait accept');
    }
    $addMeetHer = new MeetHerModel();
    $addMeetHer->himId = $userLogin->id;
    $addMeetHer->herId = $model->id;
    $addMeetHer->startDate = $startDate;
    $addMeetHer->endDate = $endDate;
    $addMeetHer->request = $request;
    $addMeetHer->locationId = $country;
    $addMeetHer->status = MeetHerModel::WAITING;
    if ($addMeetHer->save()) {
      return redirect()->back()->with('msgInfo', 'Your request has been sent.');
    }
    return redirect()->back()->with('msgInfo', 'System error.');
  }

  /**
    }
   * Get Offline Tip
   * @return resource
   * @author LongPham <long.it.stu@gmail.com>
   * */
  public function getOfflineTip($username = null) {
    $model = UserModel::where('username', '=', $username)->first();
    return view('Model::model_profile_sub_offline_tip')->with('model', $model);
  }

  /**
   * Get Offline Tip
   * @return resource
   * @author LongPham <long.it.stu@gmail.com>
   * */
  public function postOfflineTip(Request $get, $username = null) {
    $rules = [
      'tipmessage' => 'required',
      'tipamount' => 'numeric|min:1',
      'checkAgree' => 'required',
    ];

    $validator = Validator::make(Input::all(), $rules);
    if ($validator->fails()) {
      return back()->withErrors($validator)->withInput();
    }

    $model = UserModel::where('username', '=', $username)->first();
    if (empty($model)) {
      return redirect()->back()->withInput()->with('msgError', 'Model not found.');
    }

    $postData = $get->only('tipmessage', 'tipamount', 'checkAgree');
    return redirect()->back()->withInput()->with('msgInfo', 'Please wait for complete function.');
  }

  /**
   * logout
   */
  public function getLogOut() {
    return AppSession::getLogout();
  }

  //--TODO Move all FeedController function to here.

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
    //
  }

}
