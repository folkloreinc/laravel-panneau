@extends('panneau::layout')

@section('title', 'Panneau')

@section('content')

<div class="container">
    <table class="table" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th>ID</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ $item->id }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($shouldPaginate)
    {{ $items->links() }}
    @endif
</div>

@endsection
