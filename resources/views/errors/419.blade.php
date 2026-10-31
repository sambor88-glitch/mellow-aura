@extends('errors::layout')
@use('App\Modules\Shared\Support\ErrorPage')

@section('title', ErrorPage::text('419.title'))
@section('eyebrow', ErrorPage::text('419.eyebrow'))
@section('heading', ErrorPage::text('419.heading'))
@section('lead', ErrorPage::text('419.lead'))

@section('actions')
    <a href="{{ url()->previous(ErrorPage::home()) }}" class="button button-primary">{{ ErrorPage::text('419.back') }}</a>
    <a href="{{ ErrorPage::home() }}" class="button button-secondary">{{ ErrorPage::text('home') }}</a>
@endsection
