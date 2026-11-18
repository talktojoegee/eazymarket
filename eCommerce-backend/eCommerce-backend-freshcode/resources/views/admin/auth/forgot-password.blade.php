@extends('layouts.admin.guest')

@section('title', __('labels.forgot_password'))
@section('content')
    <div>
        <div class="page page-center">
            <div class="container container-tight py-4">
                <div class="text-center mb-4">
                    <!-- BEGIN NAVBAR LOGO -->
                    <a href="." class="navbar-brand navbar-brand-autodark">
                        @include('partials.auth-logo')
                    </a>
                    <!-- END NAVBAR LOGO -->
                </div>
                <div class="card card-md">
                    <div class="card-body">
                        <h2 class="h2 text-center mb-4">Forgot your password?</h2>

                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            </div>
                        @endif

                        @include('partials.password-recovery', [
                            'channels' => $recoveryChannels ?? ['email'],
                            'gateway' => $smsGateway ?? 'custom',
                            'defaultCountry' => $defaultCountry ?? null,
                            'emailAction' => route('admin.password.email'),
                            'sendOtpUrl' => route('admin.password.send-otp'),
                            'verifyOtpUrl' => route('admin.password.verify-otp'),
                        ])

                        <div class="text-center text-muted mt-3">
                            Remember your password? <a href="{{route('admin.login')}}">Sign in</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
