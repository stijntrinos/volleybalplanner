@extends('errors.layout')

@section('code')
401
@endsection
@section('title', 'Inloggen vereist')
@section('message', 'Log in om deze pagina te bekijken.')

@section('actions')
    <a href="{{ url('/app') }}" class="btn btn-primary">Inloggen</a>
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
@endsection
