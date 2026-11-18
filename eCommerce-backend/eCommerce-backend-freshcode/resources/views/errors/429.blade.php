@extends('errors::minimal')

@section('title', __('labels.too_many_requests'))
@section('code', '429')
@section('message', __('labels.too_many_requests'))
