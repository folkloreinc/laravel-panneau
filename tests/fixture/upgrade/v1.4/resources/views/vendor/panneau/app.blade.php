@extends('layout')

@section('body')
    <div id="app"></div>
    <script type="text/javascript">
        var props = @json($props);
    </script>
@endsection
