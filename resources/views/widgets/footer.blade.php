<footer class="footer">
	<div class="container">
		<div class="footerTop">
			<!--@foreach( app('pages') as $page )
			<a href="{{URL('page/'.$page->alias)}}">{{$page->title}}</a>
			@endforeach
			<a href="{{URL('register?type=model')}}">Models Sign up</a>
			<a href="{{URL('register?type=member')}}">User Sign up</a>-->
			
			<div class="row">
				<div class="col-lg-6 footerLinks">
					<a href="">Terms & Conditions</a>
					<a href="">Privacy Policy</a>
					<a href="">Contact Support</a>
				</div>
				<div class="col-lg-6 footerSocial">
					<a href=""><i class="fa fa-facebook"></i></a>
					<a href=""><i class="fa fa-twitter"></i></a>
					<a href=""><i class="fa fa-instagram"></i></a>
					<a href=""><i class="fa fa-youtube"></i></a>
				</div>
			</div>
		</div>
	</div>
	<div class="copy">
		<!--&COPY; Copyright {{app('settings')['siteName']}} {{Date('Y')}}@if(!env('NOT_SHOW_BUILD_VERSION')) - Version {{VERSION}} - build {{BUILD}}@endif. All Rights Reserved.-->
		&COPY; 2020 BB2ULive. All rights reserved.
	</div>
</footer>
