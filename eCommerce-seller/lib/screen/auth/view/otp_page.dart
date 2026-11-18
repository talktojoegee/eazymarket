import 'dart:async';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:sms_autofill/sms_autofill.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_bloc.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_event.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_state.dart';
import 'package:hyper_commerce_seller/service/master_api_service.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

class OtpPage extends StatefulWidget {
  final String? verificationId;
  final String? phoneNumber;

  const OtpPage({super.key, this.verificationId, this.phoneNumber});

  @override
  State<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends State<OtpPage> {
  static const int _resendSecondsTotal = 30;

  final _otpController = TextEditingController();
  int _secondsRemaining = _resendSecondsTotal;
  Timer? _timer;
  String _enteredOtp = '';
  bool _canResend = false;
  bool _submitted = false;
  String _verificationId = '';

  @override
  void initState() {
    super.initState();
    _verificationId = widget.verificationId ?? '';
    _startTimer();
    // Entered with only a phone number (e.g. from OTP-login's Firebase path):
    // send the OTP through the gateway-aware AuthBloc on open.
    if (_verificationId.isEmpty && (widget.phoneNumber?.isNotEmpty ?? false)) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          context.read<AuthBloc>().add(AuthPhoneSendOTPRequested(widget.phoneNumber!));
        }
      });
    }
  }

  void _startTimer() {
    _canResend = false;
    _secondsRemaining = _resendSecondsTotal;
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_secondsRemaining > 0) {
        setState(() => _secondsRemaining--);
      } else {
        setState(() => _canResend = true);
        timer.cancel();
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _otpController.dispose();
    super.dispose();
  }

  void _verify() {
    if (_verificationId.isEmpty || _enteredOtp.length != 6) return;
    _submitted = true;
    FocusManager.instance.primaryFocus?.unfocus();
    context.read<AuthBloc>().add(
          AuthPhoneVerifyOTPRequested(
            verificationId: _verificationId,
            otp: _enteredOtp,
            phoneNumber: widget.phoneNumber,
          ),
        );
  }

  void _resend() {
    if (!_canResend || widget.phoneNumber == null) return;
    context.read<AuthBloc>().add(AuthPhoneSendOTPRequested(widget.phoneNumber!));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final screenType = context.screenType;

    return CustomScaffold(
      showAppbar: true,
      backgroundColor: scheme.surface,
      body: BlocListener<AuthBloc, AuthState>(
        listener: (context, state) {
          if (state is AuthFailure) {
            _submitted = false;
            showCustomSnackbar(context: context, message: state.error, isError: true);
          } else if (state is AuthSuccess || state is AuthOTPVerified) {
            MasterApiService.callNeededApisOnLogin(context);
            context.go(AppRoutes.home);
            showCustomSnackbar(
              context: context,
              message: state is AuthSuccess ? state.message : "Login Successful",
            );
          } else if (state is AuthOTPSent) {
            showCustomSnackbar(context: context, message: "OTP sent successfully");
            setState(() => _verificationId = state.verificationId);
            _submitted = false;
            _otpController.clear();
            _enteredOtp = '';
            _startTimer();
          }
        },
        child: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    Colors.transparent,
                    AppColors.primaryColor.withValues(alpha: 0.15),
                  ],
                ),
              ),
            ),
            SafeArea(
              child: SingleChildScrollView(
                physics: const ClampingScrollPhysics(),
                padding: UIUtils.pagePadding(screenType),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(height: UIUtils.gapMD(screenType)),
                    Text(
                      context.tr('otpLabel', fallback: "Enter OTP"),
                      style: AppTextStyles.headline.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.5,
                        color: scheme.tertiary,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      widget.phoneNumber != null
                          ? "Enter the OTP sent to ${widget.phoneNumber}"
                          : "Enter the one-time password",
                      style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, height: 1.4),
                    ),
                    SizedBox(height: UIUtils.gapXL(screenType)),
                    Material(
                      color: Colors.transparent,
                      child: PinFieldAutoFill(
                        controller: _otpController,
                        currentCode: _otpController.text,
                        codeLength: 6,
                        cursor: Cursor(width: 2, height: 20, color: AppColors.primaryColor, enabled: true),
                        onCodeChanged: (code) {
                          final value = code ?? '';
                          _enteredOtp = value;
                          if (value.length == 6 && !_submitted && _verificationId.isNotEmpty) {
                            HapticFeedback.lightImpact();
                            _verify();
                          }
                        },
                        onCodeSubmitted: (code) {
                          _enteredOtp = code;
                          _verify();
                        },
                        decoration: BoxLooseDecoration(
                          radius: const Radius.circular(10),
                          strokeWidth: 1.5,
                          gapSpace: 8,
                          textStyle: TextStyle(fontSize: 20, color: scheme.tertiary, fontWeight: FontWeight.w600),
                          strokeColorBuilder: FixedColorBuilder(scheme.outlineVariant),
                        ),
                      ),
                    ),
                    SizedBox(height: UIUtils.gapLG(screenType)),
                    Center(
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            "Didn't receive the code? ",
                            style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600),
                          ),
                          if (_canResend)
                            GestureDetector(
                              onTap: _resend,
                              child: Text(
                                context.tr('resendOtp', fallback: "Resend OTP"),
                                style: AppTextStyles.bodySmall.copyWith(
                                  color: AppColors.primaryColor,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            )
                          else
                            Text(
                              "Resend in ${_secondsRemaining}s",
                              style: AppTextStyles.bodySmall.copyWith(
                                color: Colors.grey.shade600,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                        ],
                      ),
                    ),
                    SizedBox(height: UIUtils.gapXL(screenType)),
                    BlocBuilder<AuthBloc, AuthState>(
                      builder: (context, state) {
                        final isLoading = state is AuthLoading;
                        final enabled = _enteredOtp.length == 6 && _verificationId.isNotEmpty && !isLoading;
                        return SizedBox(
                          width: double.infinity,
                          height: screenType == ScreenType.tablet ? 56 : 50,
                          child: ElevatedButton(
                            onPressed: enabled ? _verify : null,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primaryColor,
                              foregroundColor: Colors.black,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                              elevation: 0,
                              disabledBackgroundColor: Colors.grey.shade400,
                            ),
                            child: Text(
                              context.tr('verifyOtp', fallback: "Verify OTP"),
                              style: AppTextStyles.titleMedium.copyWith(fontWeight: FontWeight.w700),
                            ),
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ),
            BlocBuilder<AuthBloc, AuthState>(
              builder: (context, state) {
                if (state is AuthLoading) {
                  return Container(
                    color: Colors.black.withValues(alpha: 0.4),
                    child: const Center(child: CustomLoadingIndicator()),
                  );
                }
                return const SizedBox.shrink();
              },
            ),
          ],
        ),
      ),
    );
  }
}
