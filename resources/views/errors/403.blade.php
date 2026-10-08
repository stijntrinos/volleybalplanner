@extends('errors.layout')

@section('code')
403
@endsection
@section('title', 'Geen toegang')
@section('message', 'Je hebt geen rechten om deze pagina te bekijken. Denk je dat dit niet klopt? Vraag het na bij de beheerder van je vereniging.')

@section('actions')
    <a href="{{ url()->previous() }}" class="btn btn-outline">Terug</a>
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
@endsection
