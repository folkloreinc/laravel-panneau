@extends('panneau::layout')

@section('content')
    <div id="app"></div>
    <script type="application/json" id="app-props">@json($props)</script>
@endsection
