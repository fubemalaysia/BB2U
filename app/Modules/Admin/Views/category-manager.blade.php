@extends('admin-back-end')
@section('title', 'Categories')
@section('breadcrumb', '<li><a href="/admin/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li><li class="active">Categories</li>')
@section('content')
<?php 
use App\Helpers\Session as AppSession;
$adminData = AppSession::getLoginData();
?>
<div class="row" ng-controller="categoryManagerCtrl" ng-cloak>
  <div class="col-sm-12">
    <div class="box">
      <div class="box-body">
        <div class="table-responsive">
          <table class="table table-bordered">
            <tbody><tr>
                <th>ID</th>
                <th>Name</th>
                <th>image</th>
                <th>Upload Image</th>
                <?php if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
                <th>Actions</th>
                <?php } ?>
              </tr>
              <tr ng-repeat="(key, category) in categories">
                <td><% category . id %></td>
                <td><img src="https://bb2u.live/<% category . categories_image %>" width="50" ng-show="category . categories_image"></td>
                <td><input ng-model="category.name" class="form-control" ng-required="true"></td>
                <td><input type="file" type="file" id="file<% category . id %>" name="file" ng-model="category.categories_image" class="form-control" ng-required="false"></td>
                <?php if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
                <td><a class="btn btn-success" ng-click="updateCategory(key, category)">Update</a>&nbsp;|&nbsp;<button class="btn btn-danger" ng-click="deleteCategory(key, category)">Delete</button></td>
                <?php }?>
              </tr>
              <?php if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
              <tr>
                <td></td>
                <td></td>
                <td><input ng-model="category.name" class="form-control" ng-required='true'></td>
                <td><input type="file" id="file" name="file" ng-model="category.categories_image" class="form-control" ng-required='false'>
				
				</td>
                <td><a class="btn btn-success" ng-click="addCategory(category.name,category.categories_image)">Add</a></td>
              </tr>
              <?php }?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </div>

</div>
@endsection
