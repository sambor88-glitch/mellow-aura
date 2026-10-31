@extends('errors::layout')
@use('App\Modules\Shared\Support\ErrorPage')

@section('title', ErrorPage::text('429.title'))
@section('eyebrow', ErrorPage::text('429.eyebrow'))
@section('heading', ErrorPage::text('429.heading'))
@section('lead', ErrorPage::text('429.lead'))

@section('actions')
    <a href="{{ ErrorPage::home() }}" class="button button-secondary">{{ ErrorPage::text('home') }}</a>
@endsection
