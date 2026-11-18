import 'dart:convert';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:intl_phone_field/countries.dart';
import 'package:path_provider/path_provider.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_bloc.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_event.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_state.dart';
import 'package:hyper_commerce_seller/screen/auth/model/registration_field_model.dart';
import 'package:hyper_commerce_seller/screen/auth/repo/auth_repo.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/sign_up_step_header.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/steps/personal_information_step.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/steps/required_documents_step.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/steps/address_step.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/steps/bank_account_step.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/steps/review_step.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';

class SignUpPage extends StatefulWidget {
  const SignUpPage({super.key});

  static const bool testMode = false;

  @override
  State<SignUpPage> createState() => _SignUpPageState();
}

class _SignUpPageState extends State<SignUpPage> {
  int _currentStep = 1;
  static const int _totalSteps = 4;
  static const int _reviewStep = 5;

  // Form keys for steps 2-4 (Account manages its own validation; review has none).
  final List<GlobalKey<FormState>> _formKeys =
      List.generate(4, (_) => GlobalKey<FormState>());
  final GlobalKey<PersonalInformationStepState> _personalKey =
      GlobalKey<PersonalInformationStepState>();

  // Step 1 · Account
  final _nameController = TextEditingController();
  final _mobileController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  String _dialCode = '+91';

  final _nameFocusNode = FocusNode();
  final _mobileFocusNode = FocusNode();
  final _emailFocusNode = FocusNode();
  final _passwordFocusNode = FocusNode();

  // Step 2 · Address
  final _addressController = TextEditingController();
  final _landmarkController = TextEditingController();
  final _cityController = TextEditingController();
  final _stateController = TextEditingController();
  final _countryController = TextEditingController();
  final _zipCodeController = TextEditingController();

  final _addressFocusNode = FocusNode();
  final _landmarkFocusNode = FocusNode();
  final _cityFocusNode = FocusNode();
  final _stateFocusNode = FocusNode();
  final _countryFocusNode = FocusNode();
  final _zipCodeFocusNode = FocusNode();

  // Dynamic fields from /api/seller/registration-fields
  bool _isLoadingFields = false;
  String? _fetchedCountryIso2;
  RegistrationFieldsResponse? _registrationFieldsResponse;

  // Step 3 · Dynamic and static documents
  final Map<String, String> _dynamicDocPaths = {};
  String? _businessLicensePath;
  String? _articlesOfIncorporationPath;
  String? _nationalIdCardPath;
  String? _authorizedSignaturePath;
  String? _addressProofPath;

  // Step 4 · Dynamic and static bank details
  final Map<String, TextEditingController> _dynamicBankControllers = {};
  final Map<String, FocusNode> _dynamicBankFocusNodes = {};
  final Map<String, String> _dynamicSelectValues = {};
  final Map<String, String> _dynamicBankFilePaths = {};

  final _accountHolderNameController = TextEditingController();
  final _accountNumberController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _branchCodeController = TextEditingController();
  final _routingNumberController = TextEditingController();
  String _accountType = 'savings';
  String? _voidedCheckPath;

  @override
  void dispose() {
    for (final n in [
      _nameFocusNode, _mobileFocusNode, _emailFocusNode, _passwordFocusNode,
      _addressFocusNode, _landmarkFocusNode, _cityFocusNode, _stateFocusNode,
      _countryFocusNode, _zipCodeFocusNode,
      ..._dynamicBankFocusNodes.values,
    ]) {
      n.dispose();
    }
    for (final c in [
      _nameController, _mobileController, _emailController, _passwordController,
      _addressController, _landmarkController, _cityController, _stateController,
      _countryController, _zipCodeController,
      _accountHolderNameController, _accountNumberController, _bankNameController,
      _branchCodeController, _routingNumberController,
      ..._dynamicBankControllers.values,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    if (SignUpPage.testMode) {
      _fillTestDefaults();
      _prepareTestFiles();
    }
  }

  // ── Test-only autofill (SignUpPage.testMode) ──────────────────────────────
  static const String _testPngBase64 =
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

  void _fillTestDefaults() {
    _nameController.text = 'Test Seller';
    _passwordController.text = '12345678';
    _addressController.text = '123 Test Street';
    _cityController.text = 'Bhuj';
    _stateController.text = 'Gujarat';
    _landmarkController.text = 'Near Test Point';
    _zipCodeController.text = '370001';
    _countryController.text = 'India';
    _accountHolderNameController.text = 'Test Seller';
    _accountNumberController.text = '1234567890';
    _bankNameController.text = 'Test Bank';
    _branchCodeController.text = '000426';
    _routingNumberController.text = 'TEST0000426';
    _accountType = 'savings';
  }

  Future<void> _prepareTestFiles() async {
    try {
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/reg_test_doc.png');
      if (!await file.exists()) {
        await file.writeAsBytes(base64Decode(_testPngBase64));
      }
      if (!mounted) return;
      setState(() {
        _businessLicensePath = file.path;
        _articlesOfIncorporationPath = file.path;
        _nationalIdCardPath = file.path;
        _authorizedSignaturePath = file.path;
        _addressProofPath = file.path;
        _voidedCheckPath = file.path;

        for (final k in _dynamicDocPaths.keys) {
          _dynamicDocPaths[k] = file.path;
        }
        for (final k in _dynamicBankFilePaths.keys) {
          _dynamicBankFilePaths[k] = file.path;
        }
      });
    } catch (_) {
      // Ignore — test helper only.
    }
  }

  Future<void> _testRegister() async {
    FocusScope.of(context).unfocus();
    if (_emailController.text.trim().isEmpty || _mobileController.text.trim().isEmpty) {
      showCustomSnackbar(
        context: context,
        message: "Enter a test email and mobile number first",
        isError: true,
      );
      return;
    }
    if ((_businessLicensePath ?? '').isEmpty) {
      await _prepareTestFiles();
    }
    _submitSignUp();
  }

  void _goToStep(int step) {
    FocusScope.of(context).unfocus();
    setState(() => _currentStep = step);
  }

  String _resolveCountryIso2() {
    final countryText = _countryController.text.trim();
    if (countryText.isNotEmpty) {
      try {
        final match = countries.firstWhere(
          (c) =>
              c.name.toLowerCase() == countryText.toLowerCase() ||
              c.code.toLowerCase() == countryText.toLowerCase(),
        );
        return match.code;
      } catch (_) {}
    }

    final cleanedDial = _dialCode.replaceAll('+', '').trim();
    if (cleanedDial.isNotEmpty) {
      try {
        final match = countries.firstWhere((c) => c.dialCode == cleanedDial);
        return match.code;
      } catch (_) {}
    }
    return 'IN';
  }

  Future<bool> _fetchRegistrationFields(String iso2) async {
    if (_registrationFieldsResponse != null && _fetchedCountryIso2 == iso2) {
      return true;
    }
    setState(() => _isLoadingFields = true);
    try {
      final repo = AuthRepository();
      final res = await repo.getRegistrationFields(iso2);
      if (!mounted) return false;
      setState(() {
        _registrationFieldsResponse = res;
        _fetchedCountryIso2 = iso2;
        _initDynamicFields(res);
      });
      return true;
    } catch (e) {
      if (mounted) {
        showCustomSnackbar(
          context: context,
          message: e.toString().replaceAll("Exception:", "").trim(),
          isError: true,
        );
      }
      return false;
    } finally {
      if (mounted) setState(() => _isLoadingFields = false);
    }
  }

  void _initDynamicFields(RegistrationFieldsResponse res) {
    // 1. Init documents
    final docFields = res.businessDocuments?.fields ?? [];
    for (final f in docFields) {
      if (!_dynamicDocPaths.containsKey(f.key)) {
        _dynamicDocPaths[f.key] = '';
      }
    }

    // 2. Init bank details
    final bankFields = res.bankDetails?.fields ?? [];
    for (final f in bankFields) {
      final type = f.type.toLowerCase();
      if (type == 'select') {
        if (!_dynamicSelectValues.containsKey(f.key)) {
          _dynamicSelectValues[f.key] = f.defaultValue?.toString() ??
              (f.options.isNotEmpty ? f.options.first : '');
        }
      } else if (type == 'image' || type == 'file') {
        if (!_dynamicBankFilePaths.containsKey(f.key)) {
          _dynamicBankFilePaths[f.key] = '';
        }
      } else {
        if (!_dynamicBankControllers.containsKey(f.key)) {
          _dynamicBankControllers[f.key] =
              TextEditingController(text: f.defaultValue?.toString() ?? '');
          _dynamicBankFocusNodes[f.key] = FocusNode();
        }
      }
    }

    // Autofill dynamic fields if testMode is active
    if (SignUpPage.testMode) {
      for (final f in bankFields) {
        final type = f.type.toLowerCase();
        if (type == 'text' || type == 'number') {
          if ((_dynamicBankControllers[f.key]?.text ?? '').isEmpty) {
            _dynamicBankControllers[f.key]?.text = f.key.contains('number') || f.key.contains('code')
                ? '123456789'
                : 'Test ${f.label}';
          }
        }
      }
      _prepareTestFiles();
    }
  }

  Future<void> _next() async {
    FocusScope.of(context).unfocus();
    if (_currentStep == 1) {
      if (!(_personalKey.currentState?.validateStep() ?? false)) return;
    } else if (_currentStep <= _totalSteps) {
      final valid = _formKeys[_currentStep - 1].currentState?.validate() ?? false;
      if (!valid) return;
    }

    if (_currentStep == 2) {
      // User is moving from page 2 to page 3: fetch dynamic fields
      final iso2 = _resolveCountryIso2();
      final success = await _fetchRegistrationFields(iso2);
      if (!success) return;
    }

    if (_currentStep < _reviewStep) {
      _goToStep(_currentStep + 1);
    } else {
      _submitSignUp();
    }
  }

  List<String> get _documentLabels {
    final dynamicDocs = _registrationFieldsResponse?.businessDocuments?.fields;
    if (dynamicDocs != null && dynamicDocs.isNotEmpty) {
      final labels = <String>[];
      for (final f in dynamicDocs) {
        final p = _dynamicDocPaths[f.key];
        if (p != null && p.isNotEmpty) {
          labels.add(f.label);
        }
      }
      return labels;
    }

    final labels = <String>[];
    if ((_nationalIdCardPath ?? '').isNotEmpty) labels.add('ID card');
    if ((_businessLicensePath ?? '').isNotEmpty) labels.add('Licence');
    if ((_articlesOfIncorporationPath ?? '').isNotEmpty) labels.add('Incorporation');
    if ((_authorizedSignaturePath ?? '').isNotEmpty) labels.add('Signature');
    if ((_addressProofPath ?? '').isNotEmpty) labels.add('Address proof');
    if ((_voidedCheckPath ?? '').isNotEmpty) labels.add('Voided cheque');
    return labels;
  }

  List<String>? get _dynamicBankSummary {
    final dynamicBank = _registrationFieldsResponse?.bankDetails?.fields;
    if (dynamicBank == null || dynamicBank.isEmpty) return null;
    final lines = <String>[];
    for (final f in dynamicBank) {
      final type = f.type.toLowerCase();
      if (type == 'select') {
        final v = _dynamicSelectValues[f.key];
        if (v != null && v.isNotEmpty) {
          lines.add("${f.label}: ${v.toUpperCase()}");
        }
      } else if (type == 'image' || type == 'file') {
        final p = _dynamicBankFilePaths[f.key];
        if (p != null && p.isNotEmpty) {
          lines.add("${f.label}: Attached");
        }
      } else {
        final text = _dynamicBankControllers[f.key]?.text.trim() ?? '';
        if (text.isNotEmpty) {
          if (f.key.contains('account_number')) {
            final masked = text.length <= 4 ? text : "•••• ${text.substring(text.length - 4)}";
            lines.add("${f.label}: $masked");
          } else {
            lines.add("${f.label}: $text");
          }
        }
      }
    }
    return lines;
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final scheme = Theme.of(context).colorScheme;

    return CustomScaffold(
      title: "Create seller account",
      showAppbar: true,
      centerTitle: true,
      backgroundColor: scheme.surface,
      onBackTap: () => context.pop(),
      body: BlocListener<AuthBloc, AuthState>(
        listener: (context, state) {
          if (state is AuthFailure) {
            showCustomSnackbar(context: context, message: state.error, isError: true);
          } else if (state is AuthSuccess) {
            showCustomSnackbar(context: context, message: state.message);
            context.go(AppRoutes.awaitingApproval);
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
                  colors: [Colors.transparent, AppColors.primaryColor.withValues(alpha: 0.15)],
                ),
              ),
            ),
            Column(
              children: [
                SignUpStepHeader(
                  currentStep: _currentStep,
                  totalSteps: _totalSteps,
                  screenType: screenType,
                  labels: const ['Account', 'Address', 'Documents', 'Bank'],
                ),
                Expanded(
                  child: SingleChildScrollView(
                    physics: const ClampingScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(
                      UIUtils.gapMD(screenType),
                      UIUtils.gapSM(screenType),
                      UIUtils.gapMD(screenType),
                      UIUtils.gapLG(screenType),
                    ),
                    child: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 260),
                      switchInCurve: Curves.easeOut,
                      switchOutCurve: Curves.easeIn,
                      transitionBuilder: (child, animation) => FadeTransition(
                        opacity: animation,
                        child: SlideTransition(
                          position: Tween<Offset>(begin: const Offset(0, 0.02), end: Offset.zero).animate(animation),
                          child: child,
                        ),
                      ),
                      child: KeyedSubtree(
                        key: ValueKey<int>(_currentStep),
                        child: _buildStepContent(),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            BlocBuilder<AuthBloc, AuthState>(
              builder: (context, state) {
                final isAuthLoading = state is AuthLoading;
                if (isAuthLoading || _isLoadingFields) {
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
      bottomNavigationBar: _buildBottomButtons(screenType),
    );
  }

  Widget _buildStepContent() {
    switch (_currentStep) {
      case 1:
        return PersonalInformationStep(
          key: _personalKey,
          nameController: _nameController,
          mobileController: _mobileController,
          emailController: _emailController,
          passwordController: _passwordController,
          nameFocusNode: _nameFocusNode,
          mobileFocusNode: _mobileFocusNode,
          emailFocusNode: _emailFocusNode,
          passwordFocusNode: _passwordFocusNode,
          onDialCodeChanged: (code) => _dialCode = code,
        );
      case 2:
        return BusinessAddressStep(
          formKey: _formKeys[1],
          addressController: _addressController,
          cityController: _cityController,
          stateController: _stateController,
          landmarkController: _landmarkController,
          zipCodeController: _zipCodeController,
          countryController: _countryController,
          addressNode: _addressFocusNode,
          cityNode: _cityFocusNode,
          stateNode: _stateFocusNode,
          landmarkNode: _landmarkFocusNode,
          zipCodeNode: _zipCodeFocusNode,
          countryNode: _countryFocusNode,
        );
      case 3:
        return RequiredDocumentsStep(
          formKey: _formKeys[2],
          businessLicensePath: _businessLicensePath,
          articlesOfIncorporationPath: _articlesOfIncorporationPath,
          nationalIdCardPath: _nationalIdCardPath,
          authorizedSignaturePath: _authorizedSignaturePath,
          addressProofPath: _addressProofPath,
          onFilePicked: (field, path) {
            setState(() {
              final v = path.isEmpty ? null : path;
              switch (field) {
                case 'Business License':
                  _businessLicensePath = v;
                  break;
                case 'Articles of Incorporation':
                  _articlesOfIncorporationPath = v;
                  break;
                case 'National ID Card':
                  _nationalIdCardPath = v;
                  break;
                case 'Authorized Signature':
                  _authorizedSignaturePath = v;
                  break;
                case 'Address Proof':
                  _addressProofPath = v;
                  break;
              }
            });
          },
          dynamicFields: _registrationFieldsResponse?.businessDocuments?.fields,
          dynamicDocPaths: _dynamicDocPaths,
          onDynamicFilePicked: (key, path) {
            setState(() {
              _dynamicDocPaths[key] = path;
            });
          },
        );
      case 4:
        return BankAccountStep(
          formKey: _formKeys[3],
          accountHolderNameController: _accountHolderNameController,
          accountNumberController: _accountNumberController,
          bankNameController: _bankNameController,
          branchCodeController: _branchCodeController,
          routingNumberController: _routingNumberController,
          accountType: _accountType,
          onAccountTypeChanged: (t) => setState(() => _accountType = t),
          voidedCheckPath: _voidedCheckPath,
          onVoidedCheckPicked: (path) => setState(() => _voidedCheckPath = path.isEmpty ? null : path),
          dynamicFields: _registrationFieldsResponse?.bankDetails?.fields,
          dynamicControllers: _dynamicBankControllers,
          dynamicFocusNodes: _dynamicBankFocusNodes,
          dynamicSelectValues: _dynamicSelectValues,
          onDynamicSelectChanged: (key, value) {
            setState(() {
              _dynamicSelectValues[key] = value;
            });
          },
          dynamicFilePaths: _dynamicBankFilePaths,
          onDynamicFilePicked: (key, path) {
            setState(() {
              _dynamicBankFilePaths[key] = path;
            });
          },
        );
      case 5:
        return ReviewStep(
          name: _nameController.text,
          email: _emailController.text,
          phone: '$_dialCode${_mobileController.text}',
          address: _addressController.text,
          city: _cityController.text,
          state: _stateController.text,
          zipcode: _zipCodeController.text,
          country: _countryController.text,
          bankName: _bankNameController.text,
          accountType: _accountType,
          accountHolderName: _accountHolderNameController.text,
          accountNumber: _accountNumberController.text,
          documentLabels: _documentLabels,
          dynamicBankSummary: _dynamicBankSummary,
          onEdit: _goToStep,
        );
      default:
        return const SizedBox.shrink();
    }
  }

  Widget _buildBottomButtons(ScreenType screenType) {
    final bool isFirstStep = _currentStep == 1;
    final bool isReview = _currentStep == _reviewStep;
    final String nextLabel = isReview
        ? "Submit"
        : (_currentStep == _totalSteps ? "Review" : (context.tr('next')));

    return Container(
      padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, -5)),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (SignUpPage.testMode) ...[
              SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton.icon(
                  onPressed: _testRegister,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.black,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
                  ),
                  icon: const Icon(Icons.bolt, size: 18),
                  label: const Text("Test register (fill defaults)",
                      style: TextStyle(fontWeight: FontWeight.w700)),
                ),
              ),
              const SizedBox(height: 10),
            ],
            Row(
              children: [
                if (!isFirstStep) ...[
                  Expanded(
                    child: SizedBox(
                      height: screenType == ScreenType.tablet ? 56 : 50,
                      child: OutlinedButton(
                        onPressed: () => _goToStep(_currentStep - 1),
                        style: OutlinedButton.styleFrom(
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
                          side: const BorderSide(color: AppColors.primaryColor),
                        ),
                        child: Text(
                          context.tr('previous'),
                          style: AppTextStyles.titleMedium.copyWith(color: AppColors.primaryColor, fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                ],
                Expanded(
                  child: BlocBuilder<AuthBloc, AuthState>(
                    builder: (context, state) {
                      final isLoading = (state is AuthLoading) || _isLoadingFields;
                      return SizedBox(
                        height: screenType == ScreenType.tablet ? 56 : 50,
                        child: ElevatedButton(
                          onPressed: isLoading ? null : _next,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primaryColor,
                            foregroundColor: Colors.black,
                            elevation: 0,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
                            disabledBackgroundColor: AppColors.primaryColor.withValues(alpha: 0.5),
                          ),
                          child: Text(
                            nextLabel,
                            style: AppTextStyles.titleMedium.copyWith(fontWeight: FontWeight.w700, color: Colors.black),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _submitSignUp() {
    final Map<String, dynamic> dynamicFields = {};
    final Map<String, String> dynamicFiles = {};

    // 1. Collect dynamic bank details
    final bankFields = _registrationFieldsResponse?.bankDetails?.fields;
    if (bankFields != null && bankFields.isNotEmpty) {
      for (final f in bankFields) {
        final type = f.type.toLowerCase();
        if (type == 'select') {
          dynamicFields[f.key] = _dynamicSelectValues[f.key] ?? '';
        } else if (type == 'image' || type == 'file') {
          final path = _dynamicBankFilePaths[f.key];
          if (path != null && path.isNotEmpty) {
            dynamicFiles[f.key] = path;
          }
        } else {
          dynamicFields[f.key] = _dynamicBankControllers[f.key]?.text.trim() ?? '';
        }
      }
    }

    // 2. Collect dynamic documents
    final docFields = _registrationFieldsResponse?.businessDocuments?.fields;
    if (docFields != null && docFields.isNotEmpty) {
      for (final f in docFields) {
        final path = _dynamicDocPaths[f.key];
        if (path != null && path.isNotEmpty) {
          dynamicFiles[f.key] = path;
        }
      }
    }

    context.read<AuthBloc>().add(
          AuthSignUpRequested(
            name: _nameController.text.trim(),
            email: _emailController.text.trim(),
            phone: '$_dialCode${_mobileController.text.trim()}',
            password: _passwordController.text,
            address: _addressController.text.trim(),
            city: _cityController.text.trim(),
            state: _stateController.text.trim(),
            landmark: _landmarkController.text.trim(),
            zipcode: _zipCodeController.text.trim(),
            country: _countryController.text.trim(),
            latitude: '',
            longitude: '',
            businessLicensePath: _businessLicensePath ?? '',
            articlesOfIncorporationPath: _articlesOfIncorporationPath ?? '',
            nationalIdCardPath: _nationalIdCardPath ?? '',
            authorizedSignaturePath: _authorizedSignaturePath ?? '',
            addressProofPath: _addressProofPath ?? '',
            voidedCheckPath: _voidedCheckPath ?? '',
            bankName: _bankNameController.text.trim(),
            bankBranchCode: _branchCodeController.text.trim(),
            accountHolderName: _accountHolderNameController.text.trim(),
            accountNumber: _accountNumberController.text.trim(),
            routingNumber: _routingNumberController.text.trim(),
            bankAccountType: _accountType,
            dynamicFields: dynamicFields.isNotEmpty ? dynamicFields : null,
            dynamicFiles: dynamicFiles.isNotEmpty ? dynamicFiles : null,
          ),
        );
  }
}
