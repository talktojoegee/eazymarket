@extends('errors::minimal')

@section('title', __('labels.forbidden'))
@section('code', '403')
@section('message', __($exception->getMessage() ?: 'Forbidden'))
