@extends('errors.layout')

@section('code')
{{ isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 'Fout' }}
@endsection
@section('title', 'Er ging iets mis')
@section('message', 'Er is een onverwachte fout opgetreden. Probeer het over een paar minuten opnieuw.')

@section('actions')
    <a href="{{ url()->current() }}" class="btn btn-primary">Opnieuw proberen</a>
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
@endsection
