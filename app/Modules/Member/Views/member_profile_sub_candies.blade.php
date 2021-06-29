@extends('Member::member_profile')
@section('title','Funds /Tokens')
@section('content_sub_member')
<?php

use App\Helpers\Helper as AppHelper;
?>
<div class="panel panel-default">
  <div class="panel-heading"><h4>Funds / Tokens</h4></div>
  <div class="panel-body">
    <div class="col-sm-12 col-md-12 col-xs-12">
      <div class="row fun-show">
       
          <div class="box-grey">
            <div class="row">
              <div class="col-xs-6">
                <strong>You have {{$getMember->tokens}} tokens</strong>
              </div>
            </div>
          </div>
      </div>

      <h4>Buy More Tokens</h4>

      <div class="row">
        <?php 
        if($paymentSetting && $packages){
          foreach ($packages as $item){
            ?>
        <div class="col-sm-6 col-md-3 col-xs-12">
          <div class="box-buy">
            <h2><?php echo $item->title;?></h2>
            <small><?php echo $item->description;?></small>
			<br>
            <small>Level +<?php echo $item->level_plus;?></small>
            <h4><?php echo $item->tokens;?> Tokens</h4>
            <p><del><small>$<?php echo $item->scratch_price;?></small></del> / <b>$<?php echo $item->price;?></b></p>
            <a class="btn btn-danger btn-block" href="<?php 
            echo AppHelper::getCCBillUrl($paymentSetting, $item);
            ?>">Buy</a>
          </div>
        </div>
            <?php
          }
        }
        ?>
      </div>
    </div>
  </div>
</div>
@endsection