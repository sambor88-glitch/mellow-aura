@extends('errors::layout')

@section('title', 'Za dużo prób')
@section('eyebrow', 'chwila przerwy')
@section('heading', 'Za dużo prób w krótkim czasie')
@section('lead', 'Tak strona chroni się przed automatami, które wysyłają formularze setkami. Odczekaj minutę i spróbuj jeszcze raz.')

@section('actions')
    <a href="{{ url('/') }}" class="button button-secondary">Strona główna</a>
@endsection
