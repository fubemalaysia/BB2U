
<?php
use App\Helpers\Session as AppSession;
use App\Helpers\Helper as AppHelper;

$userLogin = AppSession::getLoginData();
?>
<div class="header">
  <div class="full-container">
    <div class="logo">
      <a href="{{URL('/')}}">
        @if(app('settings')->logo)
        <img src="/uploads/{{app('settings')->logo}}" alt="{{app('settings')->siteName}}"></a>
        @endif 
      </a>
    </div>
	
 
    <div class="right-header">
		<a class="btn btn-danger navbar-toggle collapsed pull-left" data-toggle="collapse" data-target="#bs-nav-navbar-collapse" aria-expanded="false"><i class="fa fa-bars"></i> Menu</a>
    <div class="menu collapse navbar-collapse" id="bs-nav-navbar-collapse">
        <ul class="mainTopMenu">
          <li class="{{Request::is('*category*') ? 'active': ''}}" class="dropdown" id="category-sub">
            <a class="dropdown-toggle" data-toggle="dropdown" role="button"
              aria-haspopup="true" aria-expanded="false"><i class="fa fa-bars"></i> Categories</a>
            <ul class="dropdown-menu">
              @if(count($categories))
                @foreach($categories as $cate)
                <li><a href="{{URL('/category')}}/{{$cate->slug}}" class="btn btn-link">{{$cate->name}}</a></li>
                @endforeach
              @endif
            </ul>
        </li>
        <li class="{{Request::is('*all-model*') ? 'active': ''}}"><a href="{{URL('/all-model')}}" class="btn btn-grey" onClick="window.location.href = '{{URL('/all-model')}}';">ALL MODELS</a></li>
        @if (AppSession::isLogin())
          @if($userLogin->role == 'studio')
          <li><a href="{{URL('/studio')}}">Studios</a></li>
          @endif
          @if($userLogin->role == 'model')
          <li class="{{Request::is('*live*') ? 'active': ''}}"><a href="{{URL('models/live')}}" class="btn btn-grey">Broadcast Yourself</a></li>
          <li class="{{Request::is('*models/groupchat') ? 'active': ''}}"><a href="{{URL('/models/groupchat')}}" class="btn btn-grey">Group chat</a></li>
          @elseif($userLogin->role == 'member')
          <li class="{{Request::is('*blog*') ? 'active': ''}}"><a href="{{URL('blog')}}" class="btn btn-grey">Blog</a></li>
          @endif
		  
        @else
        <li class="{{Request::is('*live*') ? 'active': ''}}"><a href="{{URL('models/live')}}" class="btn btn-grey">Broadcast Yourself</a></li>
        <li class="{{Request::is('*blog*') ? 'active': ''}}"><a href="{{URL('blog')}}" class="btn btn-grey">Blog</a></li>
        <li class="{{Request::is('*studio*') ? 'active': ''}}">
		<a href="{{URL('studio')}}" class="btn btn-grey">Agents</a>
		</li>
		</ul>
		<ul class="headerBtns">
		<li><a href="{{URL('login')}}">Login</a></li>
		<li class="regBtn"><a href="{{URL('register')}}">Register</a></li>
		
        @endif
		
		
		</ul>
		@if (AppSession::isLogin())
			<div class="headerRight pull-right">
			<div class="profile-top dropdown">

            <a href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <img src="{{AppHelper::getMyProfileAvatar()}}">
              <?php echo $userLogin->username;?>
            </a>

          @if($userLogin->role === 'member')
            <ul class="dropdown-menu" aria-labelledby="dLabel">
              <li><a href="#"><i class="fa fa-signal"></i>Level {{DB::table('users')->where('id',$userLogin->id)->first()->level}} </a></li>
              <li><a href="{{URL('members/account-settings')}}"><i class="fa fa-wrench"></i> Account Settings</a></li>
              <li><a href="{{URL('messages')}}"><i class="fa fa-envelope-o"></i> Messages ({{AppHelper::getNotification()}})</a></li>
              <li><a href="{{URL('members/favorites')}}"><i class="fa fa-heart"></i> My Favorites</a></li>
              <li><a href="{{URL('members/funds-tokens')}}"><i class="fa fa-money"></i> Funds /Tokens</a></li>
            </ul>
          @endif
          @if($userLogin->role === 'model')
          <ul class="dropdown-menu" aria-labelledby="dLabel">
            <li><a href="{{URL('models/dashboard/profile')}}"><i class="fa fa-user" aria-hidden="true"></i> Profile</a></li>
            <li><a href="{{URL('models/dashboard/account-settings?action=commissions')}}"><i class="fa fa-wrench"></i> Account Settings</a></li>
            <li><a href="{{URL('models/dashboard/messages')}}"><i class="fa fa-envelope-o"></i> (Messages {{AppHelper::getNotification()}})</a></li>
            <li><a href="{{URL('models/dashboard/chat-settings')}}"><i class="fa fa-cogs"></i> Chat settings</a></li>
          </ul>
          @endif
          @if($userLogin->role === 'studio')
          <ul class="dropdown-menu" aria-labelledby="dLabel">
            <li><a href="{{URL('studio/account-settings')}}"><i class="fa fa-wrench"></i> Account Settings</a></li>
          </ul>
          @endif
          @if($userLogin->role === 'admin')
          <ul class="dropdown-menu" aria-labelledby="dLabel">
            <li><a href="{{URL('admin')}}"><i class="fa fa-wrench"></i> Admin Dashboard</a></li>
          </ul>
          @endif
        </div>
        @if($userLogin->role == 'studio')
        <a href="{{URL($userLogin->role.'/logout')}}" class="logout"><i class="fa fa-power-off"></i></a>
        @endif
        @if($userLogin->role == 'member')
		
        <a href="{{URL('members/logout')}}" class="logout"><i class="fa fa-power-off"></i></a>
        @endif
         @if($userLogin->role == 'model')
        <a href="{{URL('models/logout')}}" class="logout"><i class="fa fa-power-off"></i></a>
        @endif
		</div>
		 @endif
        <div class="search-top hidden-xs hidden-sm" style="display:none"> 
            <form action="" method="get" accept-charset="utf-8" class="">
                <input type="text" name="q" class="form-control" placeholder="Search" value="{{Request::get('q')}}">
                <button type="submit"><i class="fa fa-search"></i></button>
            </form>
        </div>
    </div>
      <?php if (!AppSession::isLogin()): ?>
        <!--<div class="login-top">
          {{Form::open(array('method'=>'post', 'url'=>URL('auth/login'), 'class'=>'form-horizontal', 'autocomplete' => 'off' ))}}
            {{Form::text('username', old('username'), array('class'=>'form-control', 'autocomplete'=>'off', 'placeholder'=>'Username'))}}
            {{Form::password('password', array('class'=>'form-control', 'autocomplete'=>'off', 'placeholder'=>'Password'))}}
            {{Form::submit('Login', array('class'=>'btn btn-grey'))}}
          {{Form::close()}}
        </div>
		
        <a href="{{URL('login')}}" class="btn btn-danger button-register visible-xs">Login</a>
        <a href="{{URL('register')}}" class="btn btn-danger button-register">Register</a>
		-->

    <div class="search-top hidden-md hidden-lg hidden-sm" style="display:none">
        <form action="" method="get" accept-charset="utf-8" class="">
            <input type="text" name="q" class="form-control" placeholder="Search" value="{{Request::get('q')}}">
            <button type="submit"><i class="fa fa-search"></i></button>
        </form>
    </div>
      <?php else: ?>
        
      <?php endif; ?>
    </div>
  </div>
</div>

 