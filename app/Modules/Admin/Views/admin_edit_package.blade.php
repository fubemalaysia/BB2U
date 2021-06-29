@extends('admin-back-end')
@section('title', 'Update Package')
@section('breadcrumb', '<li><a href="/admin/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li><li><a href="/admin/manager/paymentsystems"> Payment Settings</a></li><li><a href="/admin/manager/paymentpackages">Payment Packages</a></li><li><a>Update Package</a></li>')
@section('content')
<div class="row">
  <!-- left column -->
  <div class="col-md-6">
    <!-- general form elements -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Update Package</h3>
      </div><!-- /.box-header -->
      <!-- form start -->

      <form method="post" action="/admin/manager/paymentpackage/update/{{$package->id}}" role="form" >
        <div class="box-body">
         
          <div class="form-group required">
              <label for="scratch_price" class="control-label">Scratch Price</label>
            <input class="form-control" id="scratch_price" value="{{old('price', $package->scratch_price)}}" autocomplete="off" name="scratch_price" placeholder="" type="text">
            <span class="text-red">{{$errors->first('scratch_price')}}</span>
          </div>
          <div class="form-group required">
              <label for="price" class="control-label">Price</label>
            <input class="form-control" id="price"  value="{{old('price', $package->price)}}"  autocomplete="off" name="price" placeholder="" type="text">
            <span class="text-red">{{$errors->first('price')}}</span>
          </div>
          <div class="form-group required">
              <label for="level_plus" class="control-label">Level+</label>
            <input class="form-control" id="level_plus"  value="{{old('level_plus', $package->level_plus)}}"  autocomplete="off" name="level_plus" placeholder="" type="text">
            <span class="text-red">{{$errors->first('level_plus')}}</span>
          </div>
		   <div class="form-group">
            <label for="description">Title</label>
            <input class="form-control" id="Title" autocomplete="off"  value="{{old('title', $package->title)}}"  name="title" placeholder="" type="text">
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control" id="description" autocomplete="off" name="description" placeholder="" type="text">{{old('description', $package->description)}}</textarea>
          </div>
          <div class="form-group required">
              <label for="tokens" class="control-label">Tokens </label>
            <input class="form-control" id="parameters" autocomplete="off" value="{{old('tokens', $package->tokens)}}" name="tokens" type="text" placeholder="">
            <span class="text-red">{{$errors->first('tokens')}}</span>
          </div>
        </div>
        <div class="box-footer text-center">
          <button type="submit" class="btn btn-danger btn-lg">Update Package</button>
        </div>
      </form>
    </div>
  </div><!-- /.box -->
</div>
@endsection