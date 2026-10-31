@extends('errors::layout')
@use('App\Modules\Shared\Support\ErrorPage')

@section('title', ErrorPage::text('404.title'))
@section('eyebrow', ErrorPage::text('404.eyebrow'))
@section('heading', ErrorPage::text('404.heading'))
@section('lead', ErrorPage::text('404.lead'))

@section('actions')
    <a href="{{ ErrorPage::shop() }}" class="button button-primary">{{ ErrorPage::text('shop') }}</a>
    <a href="{{ ErrorPage::home() }}" class="button button-secondary">{{ ErrorPage::text('home') }}</a>
@endsection
