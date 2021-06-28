@extends('frontend')
@section('title','Home')
@section('content')
<div class="content">
  <div class="full-container" ng-controller="modelOnlineCtrl" ng-cloak ng-init="onlineInit('{{Request::get('q')}}')" ng-cloak>
    <div class="banner m20">
        
    </div>

    <!-- Nav tabs -->
    <?php
    /*
    <ul class="nav nav-tabs tabs-home" role="tablist">
      <li role="presentation"><a aria-controls="females" role="tab" data-toggle="tab" ng-click="setFilter('female')">Females</a></li>
      <li role="presentation"><a aria-controls="couples" role="tab" data-toggle="tab" ng-click="setFilter('couple')">Couples</a></li>
      <li role="presentation"><a aria-controls="males" role="tab" data-toggle="tab" ng-click="setFilter('male')">Males</a></li>
      <li role="presentation"><a aria-controls="lesbian" role="tab" data-toggle="tab" ng-click="setFilter('lesbian')">Lesbian</a></li>
      <li role="presentation"><a aria-controls="transsexuals" role="tab" data-toggle="tab" ng-click="setFilter('transsexual')">Transsexuals</a></li>
    </ul>
    */ ?>
    <!-- Tab panes -->
    <div class="tab-content">
      <div role="tabpanel" class="tab-pane active">
		
			<div class="row">
				<div class="col-lg-3">
					<div class="sideFilterBox">
						<h4><img src="{{asset('images/hot.png')}}" alt=""> Hot</h4> 
						<ul>
							@foreach($hotdata as $key=>$hotdata)
							<li>{{$key+1}}. {{$hotdata->username }} <span>{{ $hotdata->totla_percent}}<em>point</em></span></li>
							@endforeach
						</ul>
					</div>
					<div class="sideFilterBox">
						<h4>CATEGORY</h4>
						<ul>
						@foreach($categoriesdata as $categories)
							<li>
							@if($categories->categories_image)
								<img src="{{ $categories->categories_image}}" alt="">
							@else
								<img src="{{asset('images/chat.png')}}" alt="">
							@endif	
							<a href="{{url('/category/')}}/{{ $categories->slug}}" style="color:#fff">{{$categories->name}}</a></li>
							
						@endforeach
						<!--<li><img src="{{asset('images/pk.png')}}" alt=""> Chat</li>
							<li><img src="{{asset('images/funny.png')}}" alt=""> Chat</li>
							<li><img src="{{asset('images/friendly.png')}}" alt=""> Chat</li> -->
						</ul>
					</div>
					<div class="sideFilterBox">
						<h4>TALENT</h4>
						<ul class="filterTalentList">
						@foreach($talents as $talent)
							<li>
								<div class="FTimg">
								<?php
								$data = unserialize($talent->avatar); $mimg = $data['imageSmall']; 
								if($data == ''){
									$mimg = '/images/model.png';
								}
								?>
									<img src="{{$mimg}}" alt=""> 
								</div>
								<div class="FTdata">
									<p>{{$talent->username}}</p>
									<div class="FTage"><img style=" width: auto; " src="{{asset('images/age.png')}}" alt="">  Age {{$talent->age}}</div>
								</div>
							</li>
							@endforeach
							 
						</ul>
					</div>
					@if(app('settings') && app('settings')->side_banner != '')
					<div class="sideFilterBox SFpromotionBox">
						<h4>PROMOTION STUFF</h4>
							<a href="{{app('settings')->sidebannerLink}}"><img src="{{URL(app('settings')->side_banner)}}" width="100%"></a>
					</div>
					@endif
				</div>
				<div class="col-lg-9">
					<div class="modelLisingWrap">
						<div class="row homeTitle">
							<div class="col-lg-6" align="left">
								<h1>Welcome to BB2U</h1>
							</div>
							<div class="col-lg-6" align="right">
								<div class="search-top hidden-xs hidden-sm"> 
									<form action="" method="get" accept-charset="utf-8" class="">
										<input type="text" name="q" class="form-control" placeholder="Search" value="{{Request::get('q')}}">
										<button type="submit"><i class="fa fa-search"></i></button>
									</form>
								</div>
							</div>
						</div>
						<div class="homeBanner">
							@if(app('settings') && app('settings')->banner != '')
							<a href="{{app('settings')->bannerLink}}"><img src="{{URL(app('settings')->banner)}}" width="100%"></a>
							@endif
						</div>
						<ul class="list-model flex-container wrap">
						  <li ng-style="styleModelItem" class="col-sm-4 col-md-1-8 flex-item" ng-repeat="(key, item) in users">
						<!--<div class="box-model" style="background-image:url(/images/rooms/<% item . lastCaptureImage %>)" ng-hide="!item.lastCaptureImage" >-->
							<div class="box-model" style="background:url(<% item.avatar | avatar %>) no-repeat center" ng-hide="item.lastCaptureImage">
							 <?php  /* background-image:url({{asset('images/m1.jpg')}}) <div class="img-model">
								<a href="{{URL('profile')}}/<% item.username %>">
								  <img ng-src="/images/rooms/<% item . lastCaptureImage %>" alt="poster" ng-hide="!item.lastCaptureImage" class="img-responsive" height="130px" ng-mouseover="modelRotates(item)" fallback-src="{{URL('images/64x64.png')}}"/>
								  <img ng-src="<% item.avatar | avatar %>" alt="poster" ng-hide="item.lastCaptureImage" class="img-responsive" height="130px" fallback-src="{{URL('images/64x64.png')}}" />
								</a>
							  </div> */ ?>
							  <div class="text-box-model">
									<span class="modelStatus"><i class="fa fa-circle" ng-class="{'text-success': item.isStreaming == 1 && item.chatType === 'public', 'text-danger': item.isStreaming == 1 && item.chatType !== 'public','text-warning': item.isStreaming == 0}"> </i> </span>
									<a class="a-favoured" title="Favorite" ng-click="setFavorite(key, item.id)"><i class="fa fa-heart" ng-class="{'fa-red': item.favorite, 'fa-white': !item.favorite}"></i></a>
									<a href="{{URL('profile')}}/<% item.username %>" class="name-model">
									<% item.username | elipsis: 7 %> 
									
									<span class="pull-right modelage">
										<i class="fa" ng-class="{'text-default': item.isStreaming, 'text-warning': !item.isStreaming, 'fa-male': item.sex == 'male', 'fa-female': item.sex == 'female', 'fa-user': item.sex == 'fa-user'}"></i>
										Age <% (item.age != '0' && item.age != null) ? item.age : '' %>
									</span>
									</a>
									<?php /*
								<div ng-show="item.isStreaming == 1">
								  <span><i class="fa fa-eye"></i> <% item.totalViewer %></span>
								</div>
								
								<div class="tag list-model__tag">
								  <% item.status %>
								  <a href="?q=<% elem %>" class="tag" ng-repeat="(key, elem) in customSplitStringTags(item)">#<% elem %> </a>
								</div>*/ ?>
							  </div>
							</div>
						  </li>
						</ul>
						<p ng-show="users.length == 0">Model not found!</p>
						<nav class="text-center">
						  <ul class="pagination">  </ul>
						</nav>
					</div>
				</div>
			</div>        
      </div>



    </div>

  </div>
</div>
@endsection
