@extends('admin-back-end')
@section('title', 'Member Level')
@section('breadcrumb', '<li><a href="/admin/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li><li><a href="/admin/manager/paymentsystems"> Member</a></li><li class="active">Member Level</li>')
@section('content')
<?php 
use App\Helpers\Session as AppSession;
$adminData = AppSession::getLoginData();
?>
<div class="row">
  <div class="col-sm-12">
    <div class="box">


      <div class="box-body">
        <div class="box-header"> 
          <h3 class="box-title">
            <?php if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
              <a href="{{URL('admin/manager/level/add')}}" class="btn btn-success">Add Level</a>
            <?php } ?>
          </h3>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered">
            <tbody>
              <tr>
                <th>Id</th>
                <th>Name</th>
                <th>Level Number</th>
                <th>Points</th>
                <th>Description</th>
                <?php if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
                <th>Actions</th>
                <?php } ?>
              </tr> 
              @foreach($listLevel as $level)
              <tr>
                <td>{{$level->id}}</td>
                <td>{{$level->level_name}}</td>
                <td>{{$level->level_number}}</td>
                <td>{{$level->point}}</td>
                <td>{{$level->description}}</td>
                <?php  if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
                <td><a class="btn btn-warning btn-sm">Edit</a>&nbsp;&nbsp;<a class="btn btn-danger btn-sm"  href="{{URL('admin/manager/level/delete/'.$level->id)}}">Delete</a></td>
                <?php } ?>
              </tr>
              @endforeach

            </tbody>
          </table>
          
          <nav class="text-center">
            <ul class="pagination"> {{ $listLevel->links() }} </ul>
          </nav>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection