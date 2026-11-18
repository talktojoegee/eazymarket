import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/repo/roles_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/system_users/bloc/system_user_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/system_users/model/system_users_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/system_users/repo/system_users_repo.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_phone_field.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

class ManageSystemUserBottomSheet extends StatefulWidget {
  final SystemUser? user;
  final SystemUserBloc bloc;

  const ManageSystemUserBottomSheet({super.key, this.user, required this.bloc});

  @override
  State<ManageSystemUserBottomSheet> createState() =>
      _ManageSystemUserBottomSheetState();
}

class _ManageSystemUserBottomSheetState
    extends State<ManageSystemUserBottomSheet> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _mobileController = TextEditingController();
  final _passwordController = TextEditingController();

  final List<String> _selectedRoles = [];
  List<dynamic> _availableRoles = [];
  bool _isLoadingRoles = true;
  bool _isLoadingUserDetails = false;
  String? _rolesError;

  @override
  void initState() {
    super.initState();
    if (widget.user != null) {
      _nameController.text = widget.user!.name ?? '';
      _emailController.text = widget.user!.email ?? '';
      _mobileController.text = widget.user!.mobile;
      _isLoadingUserDetails = true;
      _fetchUserDetails(widget.user!.id);
    }
    _fetchRoles();
  }

  Future<void> _fetchUserDetails(int id) async {
    try {
      final repo = SystemUsersRepo();
      final response = await repo.getSystemUserById(id);
      // Null-safe walk of `response.data.user.roles` — the previous version
      // chained `response['data']['user']` and pushed `role['name']` (which
      // may be null) into a List<String>, both of which threw at runtime if
      // the API returned an unexpected shape.
      if (response is Map && response['success'] == true && mounted) {
        final data = response['data'];
        final userMap = data is Map ? data['user'] : null;
        final rolesRaw = userMap is Map ? userMap['roles'] : null;
        final roles = rolesRaw is List ? rolesRaw : const <dynamic>[];
        setState(() {
          for (final role in roles) {
            if (role is Map && role['name'] != null) {
              _selectedRoles.add(role['name'].toString());
            }
          }
          _isLoadingUserDetails = false;
        });
      } else if (mounted) {
        setState(() {
          _isLoadingUserDetails = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoadingUserDetails = false;
        });
      }
    }
  }

  Future<void> _fetchRoles() async {
    try {
      final repo = RolesRepo();
      final response = await repo.getRolesList();
      if (!mounted) return;
      if (response is Map && response['success'] == true) {
        final list = response['data'];
        setState(() {
          _availableRoles = list is List ? list : const [];
          _isLoadingRoles = false;
          _rolesError = null;
        });
      } else {
        setState(() {
          _isLoadingRoles = false;
          _rolesError = (response is Map ? response['message'] : null)
                  ?.toString() ??
              'Failed to load roles';
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoadingRoles = false;
        _rolesError = 'Failed to load roles';
      });
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _mobileController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _onSave(BuildContext context) {
    setState(() {
      _rolesError = null;
    });

    if (_formKey.currentState!.validate()) {
      if (_selectedRoles.isEmpty) {
        setState(() {
          _rolesError =
              context.tr('pleaseSelectOneRole', fallback: 'Please select at least one role');
        });
        return;
      }

      final bool isEdit = widget.user != null;
      final String requiredPerm = isEdit
          ? AppPermissions.systemUserEdit
          : AppPermissions.systemUserCreate;

      if (!PermissionChecker.hasPermission(requiredPerm)) {
        Navigator.pop(context);
        showCustomSnackbar(
          context: context,
          message: isEdit
              ? context.tr('noPermissionEditSystemUser', fallback: "You don't have permission to edit system users")
              : context.tr('noPermissionCreateSystemUser', fallback: "You don't have permission to create system users"),
          isWarning: true,
        );
        return;
      }

      final data = {
        if (widget.user != null) 'id': widget.user!.id,
        'name': _nameController.text.trim(),
        'email': _emailController.text.trim(),
        'mobile': _mobileController.text.trim(),
        if (_passwordController.text.isNotEmpty)
          'password': _passwordController.text,
        'roles': _selectedRoles,
      };

      if (!DemoGuard.shouldProceed(context)) {
        return;
      }

      widget.bloc.add(ManageSystemUserEvent(data: data));
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final bottomPadding = MediaQuery.of(context).viewInsets.bottom;

    return BlocListener<SystemUserBloc, SystemUserState>(
      bloc: widget.bloc,
      listenWhen: (previous, current) =>
          previous.actionMessage != current.actionMessage &&
          current.actionMessage != null &&
          current.actionMessage!.isNotEmpty,
      listener: (context, state) {
        if (state.actionMessage != null && state.actionMessage!.isNotEmpty) {
          Navigator.pop(context);
        }
      },
      child: Container(
        padding: EdgeInsets.fromLTRB(
          UIUtils.gapMD(screenType),
          UIUtils.gapMD(screenType),
          UIUtils.gapMD(screenType),
          UIUtils.gapMD(screenType) + bottomPadding,
        ),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                SizedBox(height: UIUtils.gapMD(screenType)),
                Text(
                  widget.user != null
                      ? context.tr('editUser', fallback: 'Edit User')
                      : context.tr('addNewUser', fallback: 'Add New User'),
                  style: TextStyle(
                    fontSize: UIUtils.tileTitle(screenType),
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: UIUtils.gapMD(screenType)),
                CustomTextField(
                  controller: _nameController,
                  label: context.tr('name', fallback: 'Name'),
                  hint: context.tr('enterName', fallback: 'Enter name'),
                  isRequired: true,
                  validator: (v) => ValidatorUtils.validateEmpty(context, v),
                ),
                SizedBox(height: UIUtils.gapSM(screenType)),
                CustomTextField(
                  controller: _emailController,
                  label: context.tr('email', fallback: 'Email'),
                  hint: context.tr('enterEmail', fallback: 'Enter email'),
                  isRequired: true,
                  keyboardType: TextInputType.emailAddress,
                  validator: (v) => ValidatorUtils.validateEmail(context, v),
                ),
                SizedBox(height: UIUtils.gapSM(screenType)),
                CustomTextField(
                  controller: _mobileController,
                  label: context.tr('mobileNumber', fallback: 'Mobile Number'),
                  hint: context.tr('enterYourMobileNumber', fallback: 'Enter mobile number'),
                  isRequired: true,
                  keyboardType: TextInputType.phone,
                  validator: (v) => ValidatorUtils.validatePhone(context, v),
                ),
                SizedBox(height: UIUtils.gapSM(screenType)),
                CustomTextField(
                  controller: _passwordController,
                  label: context.tr('password', fallback: 'Password'),
                  hint: context.tr('enterPassword', fallback: 'Enter password'),
                  isRequired: widget.user == null,
                  obscureText: true,
                  validator: (v) {
                    if (widget.user == null && (v == null || v.isEmpty)) {
                      return context.tr('passwordRequired', fallback: 'Password is required');
                    }
                    if (v != null && v.isNotEmpty && v.length < 8) {
                      return context.tr('passwordTooShort', fallback: 'Password must be at least 8 characters');
                    }
                    return null;
                  },
                ),
                SizedBox(height: UIUtils.gapMD(screenType)),
                Text(
                  context.tr('roles', fallback: 'Roles'),
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: Theme.of(context).colorScheme.tertiary,
                  ),
                ),
                SizedBox(height: UIUtils.gapSM(screenType)),
                if (_isLoadingRoles || _isLoadingUserDetails)
                  const Center(child: CustomLoadingIndicator())
                else if (_availableRoles.isEmpty)
                  Text(context.tr('noRolesFound', fallback: 'No roles available'))
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _availableRoles
                        .whereType<Map>()
                        .where((r) => r['name'] != null)
                        .map((role) {
                      final roleName = role['name'].toString();
                      final isSelected = _selectedRoles.contains(roleName);
                      return FilterChip(
                        label: Text(roleName),
                        selected: isSelected,
                        onSelected: (selected) {
                          setState(() {
                            if (selected) {
                              _selectedRoles.add(roleName);
                            } else {
                              _selectedRoles.remove(roleName);
                            }
                          });
                        },
                      );
                    }).toList(),
                  ),
                if (_rolesError != null)
                  Padding(
                    padding: EdgeInsets.only(top: UIUtils.gapSM(screenType)),
                    child: Text(
                      _rolesError!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontSize: 12,
                      ),
                    ),
                  ),
                SizedBox(height: UIUtils.gapLG(screenType)),
                BlocBuilder<SystemUserBloc, SystemUserState>(
                  bloc: widget.bloc,
                  builder: (context, state) {
                    return PrimaryButton(
                        onPressed: state.isActionLoading
                            ? null
                            : () => _onSave(context),
                        text: state.isActionLoading ? 'Saving...' : (context.tr('saveUser', fallback: 'Save User')),
                      );
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
