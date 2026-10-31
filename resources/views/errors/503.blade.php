@extends('errors::layout')
@use('App\Modules\Shared\Support\ErrorPage')

@section('title', ErrorPage::text('503.title'))
@section('eyebrow', ErrorPage::text('503.eyebrow'))
@section('heading', ErrorPage::text('503.heading'))
@section('lead', ErrorPage::text('503.lead'))

@section('actions')
    <a href="{{ url()->full() }}" class="button button-primary">{{ ErrorPage::text('503.refresh') }}</a>
@endsection
