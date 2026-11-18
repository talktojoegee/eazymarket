import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl_phone_field/countries.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/verify-user/verify_user_bloc.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_phone_field.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_text_field.dart';
import 'package:hyper_commerce_seller/utils/debouncer.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

String _initialIsoFromDialCode(String dialCode) {
  final cleaned = dialCode.replaceAll('+', '').trim();
  if (cleaned.isEmpty) return 'IN';
  final match = countries.firstWhere(
    (c) => c.dialCode == cleaned,
    orElse: () => countries.firstWhere((c) => c.code == 'IN', orElse: () => countries.first),
  );
  return match.code;
}

class PersonalInformationStep extends StatefulWidget {
  final TextEditingController nameController;
  final TextEditingController mobileController;
  final TextEditingController emailController;
  final TextEditingController passwordController;
  final FocusNode nameFocusNode;
  final FocusNode mobileFocusNode;
  final FocusNode emailFocusNode;
  final FocusNode passwordFocusNode;
  final ValueChanged<String> onDialCodeChanged;

  const PersonalInformationStep({
    super.key,
    required this.nameController,
    required this.mobileController,
    required this.emailController,
    required this.passwordController,
    required this.nameFocusNode,
    required this.mobileFocusNode,
    required this.emailFocusNode,
    required this.passwordFocusNode,
    required this.onDialCodeChanged,
  });

  @override
  State<PersonalInformationStep> createState() => PersonalInformationStepState();
}

class PersonalInformationStepState extends State<PersonalInformationStep> {
  final _formKey = GlobalKey<FormState>();
  bool _obscurePassword = true;

  final Debouncer _emailDebouncer = Debouncer(milliseconds: 500);
  final Debouncer _phoneDebouncer = Debouncer(milliseconds: 500);

  bool? _emailAvailable; // null = unknown, true = available, false = taken
  bool _emailChecking = false;
  bool? _phoneAvailable;
  bool _phoneChecking = false;

  bool _interacted = false;

  @override
  void initState() {
    super.initState();
    widget.emailController.addListener(_onEmailChanged);
    widget.mobileController.addListener(_onPhoneChanged);
  }

  @override
  void dispose() {
    _emailDebouncer.dispose();
    _phoneDebouncer.dispose();
    widget.emailController.removeListener(_onEmailChanged);
    widget.mobileController.removeListener(_onPhoneChanged);
    super.dispose();
  }

  void _onEmailChanged() {
    final email = widget.emailController.text.trim();
    final valid = RegExp(r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$').hasMatch(email);
    setState(() {
      _emailAvailable = null;
      _emailChecking = valid;
    });
    if (valid) {
      _emailDebouncer.run(() {
        if (mounted) context.read<VerifyUserBloc>().add(VerifyUserEmail('email', email));
      });
    }
  }

  void _onPhoneChanged() {
    final phone = widget.mobileController.text.trim();
    final valid = phone.length >= 5 && phone.length <= 16 && RegExp(r'^\d+$').hasMatch(phone);
    setState(() {
      _phoneAvailable = null;
      _phoneChecking = valid;
    });
    if (valid) {
      _phoneDebouncer.run(() {
        if (mounted) context.read<VerifyUserBloc>().add(VerifyUserPhone('mobile', phone));
      });
    }
  }

  /// Called by the coordinator on Next — validates name/password (form) plus
  /// email/mobile format and availability, surfacing messages inline.
  bool validateStep() {
    FocusScope.of(context).unfocus();
    setState(() => _interacted = true);
    final formOk = _formKey.currentState?.validate() ?? false;
    final emailOk = ValidatorUtils.validateEmail(context, widget.emailController.text.trim()) == null &&
        _emailAvailable != false;
    final phoneOk = ValidatorUtils.validatePhone(context, widget.mobileController.text.trim()) == null &&
        _phoneAvailable != false;
    return formOk && emailOk && phoneOk;
  }

  @override
  Widget build(BuildContext context) {

    return BlocListener<VerifyUserBloc, VerifyUserState>(
      listener: (context, state) {
        if (state is VerifyUserAlreadyExists) {
          setState(() {
            if (state.type == 'email') {
              _emailAvailable = false;
              _emailChecking = false;
            } else if (state.type == 'mobile') {
              _phoneAvailable = false;
              _phoneChecking = false;
            }
          });
        } else if (state is VerifyUserValid) {
          setState(() {
            if (state.type == 'email') {
              _emailAvailable = true;
              _emailChecking = false;
            } else if (state.type == 'mobile') {
              _phoneAvailable = true;
              _phoneChecking = false;
            }
          });
        } else if (state is VerifyUserError) {
          setState(() {
            _emailChecking = false;
            _phoneChecking = false;
          });
        }
      },
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            AuthTextField(
              label: context.tr('sellerName', fallback: 'Full name'),
              isRequired: true,
              hint: context.tr('enterName', fallback: 'Enter your name'),
              controller: widget.nameController,
              focusNode: widget.nameFocusNode,
              textCapitalization: TextCapitalization.words,
              textInputAction: TextInputAction.next,
              validator: (value) => ValidatorUtils.validateEmpty(context, value),
            ),
            const SizedBox(height: 20),
            AuthPhoneField(
              label: context.tr('mobileNumber', fallback: "Mobile number"),
              isRequired: true,
              hint: context.tr('mobileNumber', fallback: "Mobile number"),
              controller: widget.mobileController,
              focusNode: widget.mobileFocusNode,
              initialIso: _initialIsoFromDialCode(HiveStorage.countryDialCode),
              onChanged: (data) => widget.onDialCodeChanged(data.dialCode),
            ),
            _verifyMessage(
              checking: _phoneChecking,
              available: _phoneAvailable,
              formatError: _interacted ? ValidatorUtils.validatePhone(context, widget.mobileController.text.trim()) : null,
              availableText: "Mobile number is available",
              takenText: context.tr('thisPhoneNumberIsAlreadyTaken', fallback: "This mobile number is already registered"),
            ),
            const SizedBox(height: 20),
            AuthTextField(
              label: context.tr('emailAddress', fallback: 'Email address'),
              isRequired: true,
              hint: context.tr('enterYourEmailAddress', fallback: 'you@example.com'),
              controller: widget.emailController,
              focusNode: widget.emailFocusNode,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
            ),
            _verifyMessage(
              checking: _emailChecking,
              available: _emailAvailable,
              formatError: _interacted ? ValidatorUtils.validateEmail(context, widget.emailController.text.trim()) : null,
              availableText: "Email is available",
              takenText: context.tr('thisEmailIsAlreadyTaken', fallback: "This email is already registered"),
            ),
            const SizedBox(height: 20),
            AuthTextField(
              label: context.tr('password', fallback: 'Password'),
              isRequired: true,
              hint: context.tr('enterPassword', fallback: 'Create a password'),
              controller: widget.passwordController,
              focusNode: widget.passwordFocusNode,
              obscureText: _obscurePassword,
              suffixIcon: IconButton(
                icon: Icon(
                  _obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                  color: Colors.grey.shade500,
                  size: 20,
                ),
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
              ),
              validator: (value) => ValidatorUtils.validatePassword(context, value),
            ),
          ],
        ),
      ),
    );
  }

  Widget _verifyMessage({
    required bool checking,
    required bool? available,
    required String? formatError,
    required String availableText,
    required String takenText,
  }) {
    IconData? icon;
    String text;
    Color color;

    if (checking) {
      text = "Checking availability…";
      color = Colors.grey.shade600;
      icon = null;
    } else if (available == false) {
      text = takenText;
      color = AppColors.errorColor;
      icon = Icons.cancel_outlined;
    } else if (available == true) {
      text = availableText;
      color = AppColors.successColor;
      icon = Icons.check_circle_outline;
    } else if (formatError != null) {
      text = formatError;
      color = AppColors.errorColor;
      icon = Icons.error_outline;
    } else {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.only(top: 7, left: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (checking)
            SizedBox(
              width: 13,
              height: 13,
              child: CustomLoadingIndicator(strokeWidth: 2, color: Colors.grey.shade500),
            )
          else if (icon != null)
            Icon(icon, size: 15, color: color),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: AppTextStyles.label.copyWith(color: color, fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}
