@extends('errors::layout')
@use('App\Modules\Shared\Support\ErrorPage')

@section('title', ErrorPage::text('4xx.title'))
@section('eyebrow', ErrorPage::text('4xx.eyebrow'))
@section('heading', ErrorPage::text('4xx.heading'))
@section('lead', ErrorPage::text('4xx.lead'))

@section('actions')
    <a href="{{ ErrorPage::shop() }}" class="button button-primary">{{ ErrorPage::text('shop') }}</a>
    <a href="{{ ErrorPage::home() }}" class="button button-secondary">{{ ErrorPage::text('home') }}</a>
@endsection
