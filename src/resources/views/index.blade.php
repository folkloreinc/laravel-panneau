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
        <link href="/vendor/panneau/panneau.css" rel="stylesheet" type="text/css" />
        <!-- <link href="/css/panneau/main.css" rel="stylesheet" type="text/css" /> -->
		@stack('styles:head')
	@show

</head>
<body>
	@section('body')
        <div id="panneau"></div>
	@show

	@section('body:scripts')
		@stack('scripts:footer')
	@show

    @section('body:styles')
        <script type="text/javascript">
            window.panneau_locale = "{{ $locale }}";
            window.panneau_messages = {!! $messages !!};
            window.panneau_definition = {!! $definition !!};
        </script>
        <script src="/vendor/panneau/panneau.js" type="text/javascript"></script>
        <!-- <script src="/js/panneau/main.js" type="text/javascript"></script> -->
		@stack('styles:footer')
	@show

</body>
</html>
