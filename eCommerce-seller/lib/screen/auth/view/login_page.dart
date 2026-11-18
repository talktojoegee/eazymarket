import 'package:flutter/foundation.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_bloc.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_event.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_state.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_text_field.dart';
import 'package:hyper_commerce_seller/service/master_api_service.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage>
    with SingleTickerProviderStateMixin {
  final _identifierController = TextEditingController();
  final _passwordController = TextEditingController();
  final _identifierFocusNode = FocusNode();
  final _passwordFocusNode = FocusNode();

  bool _isPasswordVisible = false;
  bool _isLoginEnabled = false;

  late final AnimationController _introController;
  late final Animation<double> _fade;

  @override
  void initState() {
    super.initState();

    _introController = AnimationController(
      duration: const Duration(milliseconds: 550),
      vsync: this,
    );
    _fade = CurvedAnimation(parent: _introController, curve: Curves.easeOut);

    _identifierController.addListener(_updateLoginState);
    _passwordController.addListener(_updateLoginState);

    if (HiveStorage.demoMode) {
      _identifierController.text = 'demoseller@gmail.com';
      _passwordController.text = '12345678';
      _updateLoginState();
    } else if (kDebugMode) {
      _identifierController.text = 'seller@gmail.com';
      _passwordController.text = '12345678';
      _updateLoginState();
    }

    WidgetsBinding.instance.addPostFrameCallback((_) => _introController.forward());
  }

  void _updateLoginState() {
    final isEnabled = _identifierController.text.trim().isNotEmpty &&
        _passwordController.text.trim().isNotEmpty;
    if (_isLoginEnabled != isEnabled) {
      setState(() => _isLoginEnabled = isEnabled);
    }
  }

  @override
  void dispose() {
    _identifierController.removeListener(_updateLoginState);
    _passwordController.removeListener(_updateLoginState);
    _identifierController.dispose();
    _passwordController.dispose();
    _identifierFocusNode.dispose();
    _passwordFocusNode.dispose();
    _introController.dispose();
    super.dispose();
  }

  String _detectInputType(String input) {
    final trimmed = input.trim();
    if (trimmed.isEmpty) return 'empty';
    if (RegExp(r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$')
        .hasMatch(trimmed)) {
      return 'email';
    }
    final cleaned = trimmed.replaceAll(RegExp(r'[\s\-\(\)\.]'), '');
    if (RegExp(r'^\+?\d{7,15}$').hasMatch(cleaned) && !trimmed.contains('@')) {
      return 'phone';
    }
    return 'invalid';
  }

  bool _isValidEmail(String s) => _detectInputType(s) == 'email';
  bool _isValidPhone(String s) => _detectInputType(s) == 'phone';
  bool _isValidIdentifier(String s) => _isValidEmail(s) || _isValidPhone(s);

  void _handleLogin(BuildContext context) {
    FocusManager.instance.primaryFocus?.unfocus();
    final identifier = _identifierController.text.trim();
    final password = _passwordController.text.trim();

    if (identifier.isEmpty) {
      showCustomSnackbar(
        context: context,
        message: "Please enter your email or phone number",
        isError: true,
      );
      return;
    }
    if (!_isValidIdentifier(identifier)) {
      showCustomSnackbar(
        context: context,
        message: "Enter a valid email or phone number",
        isError: true,
      );
      return;
    }
    final passwordError = ValidatorUtils.validatePassword(context, password);
    if (passwordError != null) {
      showCustomSnackbar(context: context, message: passwordError, isError: true);
      return;
    }

    context.read<AuthBloc>().add(
          AuthLoginRequested(
            email: _isValidEmail(identifier) ? identifier : null,
            phoneNumber: _isValidPhone(identifier) ? identifier : null,
            password: password,
          ),
        );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final screenType = context.screenType;

    return CustomScaffold(
      showAppbar: false,
      backgroundColor: scheme.surface,
      body: BlocListener<AuthBloc, AuthState>(
        listener: (context, state) async {
          if (state is AuthFailure) {
            showCustomSnackbar(context: context, message: state.error, isError: true);
          } else if (state is AuthSuccess) {
            await MasterApiService.callNeededApisOnLogin(context);
            if (!context.mounted) return;
            if (HiveStorage.isSubscriptionAvailable &&
                (HiveStorage.activePlanId == null ||
                    HiveStorage.activePlanId == 0) &&
                HiveStorage.pendingSubscriptionId == null) {
              context.go(AppRoutes.subscriptionChoosePlan);
            } else {
              context.go(AppRoutes.home);
            }
            showCustomSnackbar(context: context, message: state.message);
          } else if (state is AuthOTPSent) {
            showCustomSnackbar(context: context, message: state.message);
            context.push(
              AppRoutes.otp,
              extra: {
                'verificationId': state.verificationId,
                'phoneNumber': state.phoneNumber,
              },
            );
          }
        },
        child: Container(
          color: AppColors.primaryColor.withValues(alpha: 0.25),
          child: Stack(
            fit: StackFit.expand,
            children: [
              // Seller-specific decorative background: a diagonal amber wave
              // sweeping across the top (higher on the right, dipping to the
              // left) with a soft highlight band layered on it. Purely cosmetic —
              // this is what visually differentiates the seller login from the
              // (identical-otherwise) customer login. Kept as a plain first child
              // of the StackFit.expand Stack so it fills the screen.
              CustomPaint(
                painter: DiagonalWavePainter(
                  color: AppColors.primaryColor,
                ),
              ),
              SafeArea(
                child: FadeTransition(
                  opacity: _fade,
                  child: SingleChildScrollView(
                    physics: const ClampingScrollPhysics(),
                    padding: UIUtils.pagePadding(screenType),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(height: 120),
                        Text(
                          context.tr('welcomeBack', fallback: "Welcome Back"),
                          style: AppTextStyles.displayLarge.copyWith(
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.5,
                            color: scheme.tertiary,
                          ),
                        ),
                        SizedBox(height: UIUtils.gapSM(screenType)),
                        Text(
                          context.tr('signInToContinue', fallback: "Sign in to your account to continue"),
                          style: AppTextStyles.body.copyWith(
                            color: Colors.grey.shade600,
                          ),
                        ),
                        SizedBox(height: UIUtils.gapXL(screenType)),
                        AuthTextField(
                          controller: _identifierController,
                          focusNode: _identifierFocusNode,
                          hint: "Email or Phone Number",
                          prefixIcon: Icons.alternate_email,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                        ),
                        SizedBox(height: UIUtils.gapMD(screenType)),
                        AuthTextField(
                          controller: _passwordController,
                          focusNode: _passwordFocusNode,
                          hint: context.tr('password', fallback: "Password"),
                          prefixIcon: Icons.lock_outline,
                          obscureText: !_isPasswordVisible,
                          textInputAction: TextInputAction.done,
                          suffixIcon: IconButton(
                            icon: Icon(
                              _isPasswordVisible
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                              color: Colors.grey.shade500,
                              size: 20,
                            ),
                            onPressed: () => setState(
                              () => _isPasswordVisible = !_isPasswordVisible,
                            ),
                          ),
                        ),
                        SizedBox(height: UIUtils.gapXS(screenType)),
                        Align(
                          alignment: Alignment.centerRight,
                          child: TextButton(
                            onPressed: () => context.push(AppRoutes.forgotPassword),
                            child: Text(
                              context.tr('forgotPassword', fallback: "Forgot Password?"),
                              style: AppTextStyles.label.copyWith(
                                color: AppColors.primaryColor,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                        SizedBox(height: UIUtils.gapMD(screenType)),
                        BlocBuilder<AuthBloc, AuthState>(
                          builder: (context, state) {
                            final isLoading = state is AuthLoading;
                            return PrimaryButton(
                              text: context.tr('login', fallback: "Login"),
                              height: screenType == ScreenType.tablet ? 56 : 50,
                              backgroundColor: _isLoginEnabled
                                  ? AppColors.primaryColor
                                  : Colors.grey.shade400,
                              foregroundColor:
                                  _isLoginEnabled ? Colors.black : Colors.white,
                              onPressed: (!_isLoginEnabled || isLoading)
                                  ? null
                                  : () => _handleLogin(context),
                            );
                          },
                        ),
                        SizedBox(height: UIUtils.gapLG(screenType)),
                        _buildDivider(context.tr('or', fallback: "OR"), scheme),
                        SizedBox(height: UIUtils.gapLG(screenType)),
                        SizedBox(
                          width: double.infinity,
                          height: screenType == ScreenType.tablet ? 56 : 50,
                          child: SecondaryButton(
                            text: "Continue with OTP",
                            icon: Icons.sms_outlined,
                            onPressed: () => context.push(AppRoutes.otpLogin),
                          ),
                        ),
                        if (HiveStorage.systemVendorType != 'single') ...[
                          SizedBox(height: UIUtils.gapXL(screenType)),
                          Center(
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  context.tr('dontHaveAccount', fallback: "Don't have an account?"),
                                  style: AppTextStyles.body.copyWith(
                                    color: Colors.grey.shade600,
                                  ),
                                ),
                                TextButton(
                                  onPressed: () => context.push('/signup'),
                                  child: Text(
                                    context.tr('signUp', fallback: "Sign Up"),
                                    style: AppTextStyles.body.copyWith(
                                      color: AppColors.primaryColor,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        SizedBox(height: UIUtils.gapXL(screenType)),
                      ],
                    ),
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
      ),
    );
  }

  Widget _buildDivider(String label, ColorScheme scheme) {
    return Row(
      children: [
        Expanded(child: Divider(color: scheme.outline, thickness: 1)),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Text(
            label,
            style: AppTextStyles.label.copyWith(
              fontWeight: FontWeight.w600,
              color: Colors.grey.shade600,
            ),
          ),
        ),
        Expanded(child: Divider(color: scheme.outline, thickness: 1)),
      ],
    );
  }
}

/// Paints a diagonal amber wave across the top of the screen: a filled band
/// (light-to-base amber gradient) whose lower edge curves down from a higher
/// point on the right to a lower point on the left, plus a subtle translucent
/// highlight wave layered on top for depth. Unique to the seller login.
class DiagonalWavePainter extends CustomPainter {
  const DiagonalWavePainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;

    // Main amber wave — right edge sits lower than the diagonal curve so the
    // band reads as a gentle slope from top-right down to the left.
    final wave = Path()
      ..moveTo(0, 0)
      ..lineTo(w, 0)
      ..lineTo(w, h * 0.22)
      ..cubicTo(w * 0.74, h * 0.324, w * 0.333, h * 0.222, 0, h * 0.33)
      ..close();

    final lighter = Color.lerp(color, Colors.white, 0.16) ?? color;
    final wavePaint = Paint()
      ..isAntiAlias = true
      ..shader = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [lighter, color],
      ).createShader(Rect.fromLTWH(0, 0, w, h * 0.35));
    canvas.drawPath(wave, wavePaint);

    // Translucent highlight wave sitting slightly above the main edge, adding a
    // soft layered look.
    final highlight = Path()
      ..moveTo(0, 0)
      ..lineTo(w, 0)
      ..lineTo(w, h * 0.176)
      ..cubicTo(w * 0.76, h * 0.278, w * 0.352, h * 0.176, 0, h * 0.278)
      ..close();
    final highlightPaint = Paint()
      ..isAntiAlias = true
      ..color = Colors.white.withValues(alpha: 0.12);
    canvas.drawPath(highlight, highlightPaint);
  }

  @override
  bool shouldRepaint(covariant DiagonalWavePainter oldDelegate) =>
      oldDelegate.color != color;
}
