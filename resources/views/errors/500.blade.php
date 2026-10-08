@extends('errors.layout')

@section('code')
500
@endsection
@section('title', 'Er ging iets mis')
@section('message', 'Er is een onverwachte fout opgetreden. Probeer het over een paar minuten opnieuw. Blijft het misgaan, neem dan contact met ons op.')

@section('actions')
    <a href="{{ url()->current() }}" class="btn btn-primary">Opnieuw proberen</a>
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
@endsection
