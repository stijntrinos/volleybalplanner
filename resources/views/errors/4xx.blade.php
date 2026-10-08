@extends('errors.layout')

@section('code')
{{ isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 'Fout' }}
@endsection
@section('title', 'Dat lukte niet')
@section('message', 'Deze pagina kon niet worden geopend.')

@section('actions')
    <a href="{{ url()->previous() }}" class="btn btn-outline">Terug</a>
    <a href="{{ url('/') }}" class="btn btn-primary">Naar de homepage</a>
@endsection
