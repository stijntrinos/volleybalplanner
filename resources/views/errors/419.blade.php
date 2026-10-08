@extends('errors.layout')

@section('code')
419
@endsection
@section('title', 'Sessie verlopen')
@section('message', 'De pagina stond te lang open, waardoor je sessie is verlopen. Ga terug, herlaad de pagina en probeer het opnieuw.')

@section('actions')
    <a href="{{ url()->previous() }}" class="btn btn-outline">Terug</a>
    <a href="{{ url('/app') }}" class="btn btn-outline">Inloggen</a>
@endsection
