@extends('Studio::studioDashboard')
@section('title','Agent Earnings')
@section('contentDashboard')
{{ Widget::run('EarningWidget', array('performerId' => '')) }}
@endsection