@extends('errors::layout')

@section('title', 'Nie ma tej strony')
@section('eyebrow', 'nie ma tej strony')
@section('heading', 'Tej strony tu nie ma')
@section('lead', 'Adres mógł się zmienić albo w linku zabrakło litery. Zajrzyj do sklepu albo zacznij od strony głównej.')

@section('actions')
    <a href="{{ url('/sklep') }}" class="button button-primary">Przejdź do sklepu</a>
    <a href="{{ url('/') }}" class="button button-secondary">Strona główna</a>
@endsection
