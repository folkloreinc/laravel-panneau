<!doctype html>
<!--[if IE ]> <html class="ie" lang="{{ $locale }}"> <![endif]-->
<!--[if !(IE) ]><!--> <html lang="{{ $locale }}"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

	<title>@yield('title')</title>
	<meta name="description" content="@yield('description')">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="shortcut icon" href="/favicon.ico" type="image/x-ico">
	<link rel="icon" href="/favicon.gif" type="image/gif">

	@section('head:scripts')
		<!-- Head Javascript -->
		<script src="https://cdn.polyfill.io/v2/polyfill.min.js" type="text/javascript"></script>
		@stack('scripts:head')
	@show

	@section('head:styles')
        <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		@stack('styles:head')
	@show

</head>
<body>
	@section('body')
        <section id="header">
			@yield('header')
		</section>

		<section id="content">
			@yield('content')
		</section>

		<footer id="footer">
			@yield('footer')
		</footer>
	@show

	@section('body:scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js" type="text/javascript"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js" type="text/javascript"></script>
		@stack('scripts:footer')
	@show

    @section('body:styles')
		@stack('styles:footer')
	@show

</body>
</html>
