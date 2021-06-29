<?php

use App\Helpers\Helper as AppHelper; ?>
@extends('frontend')
@section('title','All model')
@section('content')
<div class="content">
  <div class="full-container" ng-controller="modelOnlineCtrl" ng-cloak ng-init="onlineInit('{{Request::get('q')}}')">
    <div class="banner m20">
      @if(app('settings') && app('settings')->banner != '')
      <a href="{{app('settings')->bannerLink}}"><img src="{{URL(app('settings')->banner)}}" width="100%"></a>
        @endif
    </div>
    <div class="top-model">
      <h4>All model</h4>
      <?php 
      /*
      <ul class="list-model flex-container wrap">
        <li class="col-sm-4 col-md-1-8 flex-item" ng-repeat="(key, item) in users">
          <div class="box-model">
            <div class="img-model">
              <a href="{{URL('profile')}}/<% item . username %>">
                <img ng-src="/images/rooms/<% item . lastCaptureImage %>" alt="poster" ng-hide="!item.lastCaptureImage" class="img-responsive" height="130px" fallback-src="{{URL('images/64x64.png')}}"/>

                <img ng-src="<% item . avatar | avatar %>" alt="poster" ng-hide="item.lastCaptureImage" class="img-responsive" height="130px" fallback-src="{{URL('images/64x64.png')}}" />
              </a>
              <a class="btn" title="Favorite" ng-click="setFavorite(key, item.id)"><i class="fa" ng-class="{'fa-heart': item.favorite, 'fa-heart-o': !item.favorite}"></i></a>
              <span title="Status" ><i class="fa fa-circle" ng-class="{'text-success': item.isStreaming == '1', 'text-warning': item.isStreaming == '0'}"></i> <% (item.isStreaming == '1') ? 'Online' : 'Offline' %></span>
            </div>
            <div class="text-box-model">
              <a href="{{URL('profile')}}/<% item . username %>" class="name-model"><i class="fa text-default" ng-class="{ 'fa-male': item.sex == 'male', 'fa-female': item.sex == 'female', 'fa-user': item.sex == 'fa-user'}"></i> <% item . username | elipsis: 10 %> <span class="pull-right"><% (item.age != '0' && item.age != null) ? item.age +' years' : '' %></span></a>
              <% item.status %>
              <totalMemberViews></totalMemberViews>
            </div>
          </div>
        </li>
      </ul>
      */ ?>
      <ul class="list-model flex-container wrap">
          <li ng-style="styleModelItem" class="col-sm-4 col-md-1-8 flex-item" ng-repeat="(key, item) in users">
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
            </div>
          </li>
        </ul>
      <p ng-show="users.length == 0">Model not found!</p>
    </div>
    <nav class="text-center">

      <nav class="text-center">

        <ul ng-if="totalPages > 1" class="pagination">

          <li ng-class="{disabled:currentPage === 1}">
            <a ng-click="setPage(currentPage - 1)"><i class="fa fa-angle-double-left" aria-hidden="true"></i></a>
          </li>
          <li ng-repeat="page in _.range(1, totalPages + 1)" ng-class="{active:currentPage === page}">
            <a ng-click="setPage(page)"><% page %></a>
          </li>
          <li ng-class="{disabled:currentPage === totalPages}">
            <a ng-click="setPage(currentPage + 1)"><i class="fa fa-angle-double-right" aria-hidden="true"></i></a>
          </li>

        </ul>
      </nav>
    </nav>
  </div>
</div>
@endsection
