// ignore_for_file: use_build_context_synchronously

import 'dart:async';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:sms_autofill/sms_autofill.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/auth/repo/auth_repo.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_phone_field.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_text_field.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/profile/bloc/profile_bloc.dart';
import 'package:hyper_commerce_seller/service/master_api_service.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

enum _OtpLoginStep { identifier, otp }

class OtpLoginPage extends StatefulWidget {
  const OtpLoginPage({super.key});

  @override
  State<OtpLoginPage> createState() => _OtpLoginPageState();
}

class _OtpLoginPageState extends State<OtpLoginPage> {
  static const int _resendSecondsTotal = 30;

  final AuthRepository _authRepository = AuthRepository();

  final _identifierController = TextEditingController();
  final _otpController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  _OtpLoginStep _step = _OtpLoginStep.identifier;
  bool _isLoading = false;
  String _identifier = '';
  String _otp = '';
  bool _otpSubmitted = false;

  // Step 0 channel: true → phone (country-code field), false → email.
  bool _phoneMode = false;
  String _completePhoneNumber = '';

  int _resendSeconds = _resendSecondsTotal;
  bool _canResend = false;
  Timer? _resendTicker;

  @override
  void initState() {
    super.initState();
    _phoneMode = _allowMobile;
  }

  @override
  void dispose() {
    _resendTicker?.cancel();
    _identifierController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  void _startResendTimer() {
    _resendTicker?.cancel();
    setState(() {
      _resendSeconds = _resendSecondsTotal;
      _canResend = false;
    });
    _resendTicker = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() {
        if (_resendSeconds > 0) _resendSeconds--;
        if (_resendSeconds == 0) {
          _canResend = true;
          timer.cancel();
        }
      });
    });
  }

  String _messageOf(dynamic response, String fallback) {
    if (response is Map && response.containsKey('message') && response['message'] != null) {
      return response['message'].toString();
    }
    return fallback;
  }

  bool get _allowEmail => HiveStorage.allowEmailRecovery;
  bool get _allowMobile => HiveStorage.allowMobileRecovery;

  bool get _effectivePhoneMode {
    if (!_allowEmail) return true;
    if (!_allowMobile) return false;
    return _phoneMode;
  }

  String _channelWord() {
    if (_allowEmail && _allowMobile) return "email or mobile number";
    return _allowEmail ? "email address" : "mobile number";
  }

  Future<void> _sendOtp() async {
    final bool phoneChannel = _effectivePhoneMode;
    final String identifier;
    if (phoneChannel) {
      final phone = _completePhoneNumber.trim();
      if (phone.length < 6) {
        showCustomSnackbar(context: context, message: "Please enter a valid mobile number", isError: true);
        return;
      }
      identifier = phone;
    } else {
      final email = _identifierController.text.trim();
      if (email.isEmpty) {
        showCustomSnackbar(
          context: context,
          message: context.tr('fieldRequired', fallback: "Field Required"),
          isError: true,
        );
        return;
      }
      final looksLikeEmail = RegExp(r'^[\w\.\-]+@([\w\-]+\.)+[\w\-]{2,}$').hasMatch(email);
      if (!looksLikeEmail) {
        showCustomSnackbar(context: context, message: "Please enter a valid email address", isError: true);
        return;
      }
      identifier = email;
    }

    // Gateway-aware: a firebase SMS gateway on the phone channel hands off to
    // the shared OTP verification screen, which sends & verifies via Firebase
    // phone auth (AuthBloc). Email and custom SMS use the server send-OTP API.
    if (phoneChannel && HiveStorage.isFirebaseSmsGateway) {
      context.push(AppRoutes.otp, extra: {'phoneNumber': identifier});
    } else {
      await _sendServerOtp(identifier);
    }
  }

  Future<void> _sendServerOtp(String identifier) async {
    setState(() => _isLoading = true);
    try {
      final response = await _authRepository.loginSendOtp(identifier: identifier);
      if (!mounted) return;
      _identifier = identifier;
      _otpSubmitted = false;
      _otp = '';
      _otpController.clear();
      showCustomSnackbar(
        context: context,
        message: _messageOf(response, context.tr('otpSentSuccess', fallback: "OTP sent successfully")),
      );
      setState(() => _step = _OtpLoginStep.otp);
      _startResendTimer();
    } catch (e) {
      if (!mounted) return;
      showCustomSnackbar(context: context, message: e.toString().replaceAll("Exception:", "").trim(), isError: true);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _resendOtp() {
    if (!_canResend || _isLoading) return;
    _sendOtp();
  }

  Future<void> _verifyOtp() async {
    if (_isLoading) return;
    if (_otp.length < 6) {
      showCustomSnackbar(
        context: context,
        message: context.tr('enterOtp', fallback: "Please enter the OTP"),
        isError: true,
      );
      return;
    }

    _otpSubmitted = true;
    setState(() => _isLoading = true);
    try {
      final response = await _authRepository.loginVerifyOtp(identifier: _identifier, otp: _otp);
      if (!mounted) return;

      final success = response is Map && (response['success'] == true || response['access_token'] != null);
      if (!success) {
        _otpSubmitted = false;
        showCustomSnackbar(context: context, message: _messageOf(response, "OTP verification failed"), isError: true);
        return;
      }

      await ProfileBloc.fetchAndStoreProfile();
      if (!mounted) return;
      await MasterApiService.callNeededApisOnLogin(context);
      if (!mounted) return;

      if (HiveStorage.isSubscriptionAvailable &&
          (HiveStorage.activePlanId == null || HiveStorage.activePlanId == 0) &&
          HiveStorage.pendingSubscriptionId == null) {
        context.go(AppRoutes.subscriptionChoosePlan);
      } else {
        context.go(AppRoutes.home);
      }

      showCustomSnackbar(
        context: context,
        message: _messageOf(response, context.tr('loginSuccessful', fallback: "Login Successful")),
      );
    } catch (e) {
      if (!mounted) return;
      _otpSubmitted = false;
      showCustomSnackbar(context: context, message: e.toString().replaceAll("Exception:", "").trim(), isError: true);
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final screenType = context.screenType;

    return CustomScaffold(
      showAppbar: true,
      backgroundColor: scheme.surface,
      body: Stack(
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
              child: Form(
                key: _formKey,
                child: _step == _OtpLoginStep.identifier
                    ? _buildIdentifierStep(context, screenType, scheme)
                    : _buildOtpStep(context, screenType, scheme),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _stepDots(int active, ColorScheme scheme) {
    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 22),
      child: Row(
        children: List.generate(2, (i) {
          return Expanded(
            child: Container(
              margin: EdgeInsets.only(right: i < 1 ? 7 : 0),
              height: 5,
              decoration: BoxDecoration(
                color: i <= active ? AppColors.primaryColor : scheme.outline,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _heading(String title, String subtitle, ColorScheme scheme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: AppTextStyles.headline.copyWith(
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
            color: scheme.tertiary,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          subtitle,
          style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, height: 1.4),
        ),
        const SizedBox(height: 24),
      ],
    );
  }

  Widget _primaryButton({required String label, required VoidCallback? onPressed, required ScreenType screenType}) {
    return SizedBox(
      width: double.infinity,
      height: screenType == ScreenType.tablet ? 56 : 50,
      child: ElevatedButton(
        onPressed: _isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primaryColor,
          foregroundColor: Colors.black,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          elevation: 0,
          disabledBackgroundColor: AppColors.primaryColor.withValues(alpha: 0.5),
        ),
        child: _isLoading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CustomLoadingIndicator(color: Colors.black, strokeWidth: 2),
              )
            : Text(label, style: AppTextStyles.titleMedium.copyWith(fontWeight: FontWeight.w700)),
      ),
    );
  }

  Widget _buildBackToLogin() {
    return Center(
      child: TextButton(
        onPressed: () => context.pop(),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.arrow_back, size: 16, color: Colors.grey.shade600),
            const SizedBox(width: 4),
            Text(
              context.tr('backToLogin', fallback: "Back to Login"),
              style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildModeToggle(ColorScheme scheme) {
    return SegmentedButton<bool>(
      showSelectedIcon: false,
      style: ButtonStyle(
        visualDensity: VisualDensity.comfortable,
        backgroundColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected) ? AppColors.primaryColor : Colors.transparent,
        ),
        foregroundColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected) ? Colors.black : scheme.tertiary,
        ),
        textStyle: WidgetStatePropertyAll(AppTextStyles.bodySmall.copyWith(fontWeight: FontWeight.w600)),
      ),
      segments: const [
        ButtonSegment<bool>(value: true, icon: Icon(Icons.phone_outlined, size: 18), label: Text('Phone')),
        ButtonSegment<bool>(value: false, icon: Icon(Icons.alternate_email, size: 18), label: Text('Email')),
      ],
      selected: {_phoneMode},
      onSelectionChanged: (selection) => setState(() => _phoneMode = selection.first),
    );
  }

  Widget _buildIdentifierStep(BuildContext context, ScreenType screenType, ColorScheme scheme) {
    final showToggle = _allowEmail && _allowMobile;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepDots(0, scheme),
        _heading(
          "Login with OTP",
          "Enter your ${_channelWord()} and we'll send you a one-time password",
          scheme,
        ),
        if (showToggle) ...[
          Center(child: _buildModeToggle(scheme)),
          SizedBox(height: UIUtils.gapLG(screenType)),
        ],
        if (_effectivePhoneMode)
          AuthPhoneField(
            hint: "Mobile number",
            onChanged: (data) => setState(() => _completePhoneNumber = data.complete),
          )
        else
          AuthTextField(
            controller: _identifierController,
            hint: "Email address",
            prefixIcon: Icons.email_outlined,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.done,
          ),
        SizedBox(height: UIUtils.gapXL(screenType)),
        _primaryButton(label: context.tr('sendOTP', fallback: "Send OTP"), onPressed: _sendOtp, screenType: screenType),
        SizedBox(height: UIUtils.gapMD(screenType)),
        _buildBackToLogin(),
      ],
    );
  }

  Widget _buildOtpStep(BuildContext context, ScreenType screenType, ColorScheme scheme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepDots(1, scheme),
        _heading(
          context.tr('otpLabel', fallback: "Enter OTP"),
          "${context.tr('enterOtp', fallback: "Enter the OTP sent to")} $_identifier",
          scheme,
        ),
        Material(
          color: Colors.transparent,
          child: PinFieldAutoFill(
            controller: _otpController,
            currentCode: _otpController.text,
            codeLength: 6,
            cursor: Cursor(width: 2, height: 20, color: AppColors.primaryColor, enabled: true),
            onCodeChanged: (code) {
              final value = code ?? '';
              _otp = value;
              if (value.length == 6 && !_otpSubmitted) {
                HapticFeedback.lightImpact();
                _verifyOtp();
              }
            },
            onCodeSubmitted: (code) {
              _otp = code;
              _verifyOtp();
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
        SizedBox(height: UIUtils.gapXL(screenType)),
        _primaryButton(label: context.tr('verifyOtp', fallback: "Verify & Login"), onPressed: _verifyOtp, screenType: screenType),
        SizedBox(height: UIUtils.gapMD(screenType)),
        Center(
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text("Didn't receive the code? ", style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600)),
              if (_canResend)
                GestureDetector(
                  onTap: _resendOtp,
                  child: Text(
                    context.tr('resendOtp', fallback: "Resend OTP"),
                    style: AppTextStyles.bodySmall.copyWith(color: AppColors.primaryColor, fontWeight: FontWeight.w700),
                  ),
                )
              else
                Text(
                  "Resend in ${_resendSeconds}s",
                  style: AppTextStyles.bodySmall.copyWith(color: Colors.grey.shade600, fontWeight: FontWeight.w600),
                ),
            ],
          ),
        ),
        SizedBox(height: UIUtils.gapSM(screenType)),
        _buildBackToLogin(),
      ],
    );
  }
}
