@extends('errors.layout')

@section('code')
503
@endsection
@section('title', 'Even onderhoud')
@section('message', 'Volleybalplanner wordt op dit moment bijgewerkt. Over een paar minuten zijn we weer bereikbaar.')

@section('actions')
    <a href="{{ url()->current() }}" class="btn btn-primary">Opnieuw proberen</a>
@endsection
