@extends('admin-back-end')
@section('title', 'Add Level')
@section('breadcrumb', '<li><a href="/admin/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li><li><a href="/admin/manager/paymentsystems"> Member</a></li><li><a href="/admin/manager/paymentpackages">Member Level</a></li><li><a>Add Level</a></li>')
@section('content')
<div class="row">
  <!-- left column -->
  <div class="col-md-6">
    <!-- general form elements -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Add Level</h3>
      </div><!-- /.box-header -->
      <!-- form start -->

      <form method="post" action="" role="form">
        <div class="box-body">
           
          <div class="form-group required">
              <label for="level_name" class="control-label">Level Name</label>
            <input class="form-control" id="level_name" value="{{old('level_name')}}" autocomplete="off" name="level_name" placeholder="" type="text">
            <span class="text-red">{{$errors->first('level_name')}}</span>
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <input class="form-control" id="description" autocomplete="off" value="{{old('description')}}" name="description" placeholder="" type="text">
          </div>
          <div class="form-group required">
              <label for="point" class="control-label">Points </label>
            <input class="form-control" id="point" autocomplete="off" value="{{old('point')}}" name="point" type="number" placeholder="">
            <span class="text-red">{{$errors->first('point')}}</span>
          </div>
          <div class="form-group required">
              <label for="level_number" class="control-label">Level Number</label>
            <input class="form-control" id="level_number" autocomplete="off" value="{{old('level_number')}}" name="level_number" type="number" placeholder="">
            <span class="text-red">{{$errors->first('level_number')}}</span>
          </div>
        

        </div>
        <div class="box-footer text-center">

          <button type="submit" class="btn btn-danger btn-lg">Add Level</button>
        </div>
      </form>
    </div>
  </div><!-- /.box -->
</div>
@endsection