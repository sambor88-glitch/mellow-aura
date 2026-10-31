@extends('errors::layout')
@use('App\Modules\Shared\Support\ErrorPage')

@section('title', ErrorPage::text('5xx.title'))
@section('eyebrow', ErrorPage::text('5xx.eyebrow'))
@section('heading', ErrorPage::text('5xx.heading'))
@section('lead', ErrorPage::text('5xx.lead'))

@section('actions')
    @if (request()->isMethod('get'))
        <a href="{{ url()->full() }}" class="button button-primary">{{ ErrorPage::text('5xx.retry') }}</a>
    @endif
    <a href="{{ ErrorPage::home() }}" class="button button-secondary">{{ ErrorPage::text('home') }}</a>
@endsection
