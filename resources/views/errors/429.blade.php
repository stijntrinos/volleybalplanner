@extends('errors.layout')

@section('code')
429
@endsection
@section('title', 'Even rustig aan')
@section('message', 'Er kwamen te veel verzoeken achter elkaar binnen. Wacht een minuut en probeer het dan opnieuw.')

@section('actions')
    <a href="{{ url()->current() }}" class="btn btn-primary">Opnieuw proberen</a>
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
@endsection
