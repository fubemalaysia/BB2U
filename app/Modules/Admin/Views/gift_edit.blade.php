@extends('admin-back-end')
@section('title', 'Update Gift')
@section('breadcrumb', '<li><a href="/admin/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li><li><a href="/admin/manager/paymentsystems"> Payment Settings</a></li><li><a href="/admin/manager/paymentpackages">Payment Packages</a></li><li><a>Add Package</a></li>')
@section('content')
<div class="row">
  <!-- left column -->
  <div class="col-md-6">
    <!-- general form elements -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Update Gift</h3>
      </div><!-- /.box-header -->
      <!-- form start -->

      
	  {!! Form::open(array('method'=>'post', 'role'=>'form', 'novalidate', 'files'=>true)) !!}
        <div class="box-body"> 
          <div class="form-group required">
              <label for="name" class="control-label">Gift Name</label>
            <input class="form-control" id="name"  autocomplete="off" value="{{$editdata->name}}" name="name" placeholder="" type="text" required>
            <span class="text-red">{{$errors->first('name')}}</span>
          </div>
          <div class="form-group required">
            <label for="file" class="control-label">Gift Image</label>
            <input class="form-control" id="file" autocomplete="off" name="file" placeholder="" type="file">
		   <img src="{{URL('uploads/')}}/{{$editdata->file}}" width="50">
          </div>
          <div class="form-group required">
              <label for="price" class="control-label">Price </label>
            <input class="form-control" id="price" autocomplete="off" value="{{$editdata->price}}" name="price" type="number" placeholder="" required>
            <span class="text-red">{{$errors->first('tokens')}}</span>
          </div>
        

        </div>
        <div class="box-footer text-center">

          <button type="submit" class="btn btn-danger btn-lg">Update Gift</button>
        </div>
      </form>
    </div>
  </div><!-- /.box -->
  <div class="col-md-6">
    <!-- general form elements -->
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Gifts</h3>
      </div><!-- /.box-header -->
      <!-- form start -->

      
	  <div class="table-responsive">
          <table class="table table-bordered">
            <tbody>
              <tr>
                <th>Id</th>
                <th>Name</th> 
                <th>Image</th> 
                <th>price</th>
                <th>Actions</th> 
              </tr>
              @foreach($listGift as $package)
              <tr>
                <td>{{$package->id}}</td>
                <td><img src="{{URL('uploads/')}}/{{$package->file}}" width="50"></td>
                <td>{{$package->name}}</td>
                <td>{{$package->price}}</td> 
                <?php if(!env('DISABLE_EDIT_ADMIN') || $adminData->isSuperAdmin) { ?>
                <td><a class="btn btn-warning btn-sm" href="{{URL('admin/gift/edit/'.$package->id)}}">Edit</a>&nbsp;&nbsp;<a class="btn btn-danger btn-sm" href="{{URL('admin/gift/delete/'.$package->id)}}">Delete</a></td>
                <?php } ?>
              </tr>
              @endforeach

            </tbody>
          </table>
          
          <nav class="text-center">
            <ul class="pagination"> {{ $listGift->links() }} </ul>
          </nav>
    </div>
  </div><!-- /.box -->
</div>
@endsection