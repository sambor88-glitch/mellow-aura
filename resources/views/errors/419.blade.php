@extends('errors::layout')

@section('title', 'Strona wygasła')
@section('eyebrow', 'strona wygasła')
@section('heading', 'Ta strona była otwarta zbyt długo')
@section('lead', 'Dla bezpieczeństwa formularz wygasa po pewnym czasie. Wróć, odśwież stronę i wyślij go jeszcze raz.')

@section('actions')
    <a href="{{ url()->previous(url('/')) }}" class="button button-primary">Wróć do formularza</a>
    <a href="{{ url('/') }}" class="button button-secondary">Strona główna</a>
@endsection
