@extends('errors::layout')

@section('title', 'Nie da się otworzyć tej strony')
@section('eyebrow', 'nie da się otworzyć')
@section('heading', 'Tej strony nie da się otworzyć')
@section('lead', 'Sprawdź adres albo zacznij od strony głównej. Jeśli trafiłaś tu z linku na mojej stronie, napisz do mnie — poprawię go.')

@section('actions')
    <a href="{{ url('/sklep') }}" class="button button-primary">Przejdź do sklepu</a>
    <a href="{{ url('/') }}" class="button button-secondary">Strona główna</a>
@endsection
