@extends('layouts.seller.guest')

@section('title', __('labels.login_with_otp'))
@section('content')
    <div>
        <div class="page page-center">
            <div class="container container-tight py-4">
                <div class="text-center mb-4">
                    <a href="." class="navbar-brand navbar-brand-autodark">
                        @include('partials.auth-logo')
                    </a>
                </div>
                <div class="card card-md">
                    <div class="card-body">
                        <h2 class="h2 text-center mb-4">{{ __('labels.login_with_otp') }}</h2>
                        <p class="text-muted mb-4">Enter your email or mobile number and we'll send you a one-time code to sign in.</p>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        @include('partials.otp-login', [
                            'gateway' => $smsGateway ?? 'custom',
                            'sendUrl' => route('seller.login.send-otp'),
                            'verifyUrl' => route('seller.login.verify-otp'),
                        ])

                        <div class="text-center text-muted mt-3">
                            <a href="{{ route('seller.login') }}">{{ __('labels.login_with_password') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
