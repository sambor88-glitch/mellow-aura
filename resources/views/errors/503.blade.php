@extends('errors::layout')

@section('title', 'Krótka przerwa')
@section('eyebrow', 'krótka przerwa')
@section('heading', 'Robię porządki na stronie')
@section('lead', 'Za kilka minut wszystko wróci na swoje miejsce, razem z Twoim koszykiem. Zajrzyj za chwilę.')

@section('actions')
    <a href="{{ url()->full() }}" class="button button-primary">Odśwież stronę</a>
@endsection
