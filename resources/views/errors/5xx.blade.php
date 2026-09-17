@extends('errors::layout')

@section('title', 'Coś się zacięło')
@section('eyebrow', 'chwilowa usterka')
@section('heading', 'Coś się zacięło po mojej stronie')
@section('lead', 'Twój koszyk jest bezpieczny — spróbuj za chwilę. Jeśli to się powtarza, napisz do mnie przez stronę kontaktową.')

@section('actions')
    @if (request()->isMethod('get'))
        <a href="{{ url()->full() }}" class="button button-primary">Spróbuj jeszcze raz</a>
    @endif
    <a href="{{ url('/') }}" class="button button-secondary">Strona główna</a>
@endsection
