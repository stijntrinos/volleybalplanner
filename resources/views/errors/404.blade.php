@extends('errors.layout')

@section('code')
404
@endsection
@section('title', 'Pagina niet gevonden')
@section('message', 'Deze pagina bestaat niet (meer). Misschien is de link verouderd of zit er een typfout in het adres.')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
    <a href="{{ url()->previous() }}" class="btn btn-outline">Terug</a>
@endsection
