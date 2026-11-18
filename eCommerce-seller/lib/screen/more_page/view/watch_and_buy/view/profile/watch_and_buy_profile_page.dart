// ignore_for_file: deprecated_member_use

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:image_picker/image_picker.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import '../../bloc/profile/watch_and_buy_profile_bloc.dart';
import '../../model/watch_and_buy_profile_model.dart';

class WatchAndBuyProfilePage extends StatefulWidget {
  final WatchAndBuyProfileModel? initialProfile;

  const WatchAndBuyProfilePage({
    super.key,
    this.initialProfile,
  });

  @override
  State<WatchAndBuyProfilePage> createState() => _WatchAndBuyProfilePageState();
}

class _WatchAndBuyProfilePageState extends State<WatchAndBuyProfilePage> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _usernameController;
  late final TextEditingController _bioController;
  File? _selectedPhoto;

  @override
  void initState() {
    super.initState();
    _usernameController = TextEditingController(text: widget.initialProfile?.username ?? '');
    _bioController = TextEditingController(text: widget.initialProfile?.bio ?? '');
  }

  @override
  void dispose() {
    _usernameController.dispose();
    _bioController.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    final path = await ImagePickerUtils.pickMedia(
      context,
      mediaType: MediaType.image,
    );

    if (path != null) {
      setState(() {
        _selectedPhoto = File(path);
      });
    }
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;

    final username = _usernameController.text.trim();
    final bio = _bioController.text.trim();

    context.read<WatchAndBuyProfileBloc>().add(
          SaveProfileEvent(
            username: username,
            bio: bio,
            photo: _selectedPhoto,
          ),
        );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final isEditing = widget.initialProfile != null && widget.initialProfile!.username != null;

    return CustomScaffold(
      title: isEditing ? 'Edit Profile' : 'Set Up Profile',
      showAppbar: true,
      body: BlocConsumer<WatchAndBuyProfileBloc, WatchAndBuyProfileState>(
        listener: (context, state) {
          if (state is ProfileSaveSuccess) {
            showCustomSnackbar(context: context, message: state.message);
            Navigator.pop(context, state.profile);
          } else if (state is ProfileError) {
            showCustomSnackbar(context: context, message: state.error, isError: true);
          }
        },
        builder: (context, state) {
          final isSaving = state is ProfileSaving;

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const SizedBox(height: 12),

                  // Avatar Picker
                  Center(
                    child: Stack(
                      children: [
                        Container(
                          width: 100,
                          height: 100,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isDark ? Colors.grey.shade800 : Colors.grey.shade200,
                            border: Border.all(color: AppColors.primaryColor, width: 2),
                          ),
                          clipBehavior: Clip.antiAlias,
                          child: _selectedPhoto != null
                              ? Image.file(_selectedPhoto!, fit: BoxFit.cover)
                              : (widget.initialProfile?.photoUrl != null &&
                                      widget.initialProfile!.photoUrl!.isNotEmpty)
                                  ? CustomNetworkImage(
                                      imageUrl: widget.initialProfile!.photoUrl!,
                                      fit: BoxFit.cover,
                                    )
                                  : Icon(
                                      Icons.person,
                                      size: 54,
                                      color: isDark ? Colors.white54 : Colors.grey,
                                    ),
                        ),
                        Positioned(
                          bottom: 0,
                          right: 0,
                          child: GestureDetector(
                            onTap: _pickImage,
                            child: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: const BoxDecoration(
                                color: AppColors.primaryColor,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.camera_alt, size: 18, color: Colors.white),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 8),
                  Text(
                    'Profile Photo (Optional)',
                    style: TextStyle(fontSize: 12, color: isDark ? Colors.white60 : Colors.black54),
                  ),

                  const SizedBox(height: 28),

                  // Username Field
                  CustomTextField(
                    controller: _usernameController,
                    label: 'Username',
                    hint: 'e.g. brand_official',
                    isRequired: true,
                    prefixIcon: Icons.alternate_email,
                    maxLength: 30,
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) {
                        return 'Username is required';
                      }
                      final trimmed = val.trim();
                      if (trimmed.length < 3 || trimmed.length > 30) {
                        return 'Username must be between 3 and 30 characters';
                      }
                      final regex = RegExp(r'^[a-z0-9._]+$');
                      if (!regex.hasMatch(trimmed)) {
                        return 'Only lowercase letters, numbers, dots and underscores allowed';
                      }
                      return null;
                    },
                  ),

                  const SizedBox(height: 16),

                  // Bio Field
                  CustomTextField(
                    controller: _bioController,
                    label: 'Bio',
                    hint: 'Tell buyers about your brand and products...',
                    isRequired: true,
                    maxLines: 4,
                    maxLength: 160,
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) {
                        return 'Bio is required';
                      }
                      if (val.trim().length > 160) {
                        return 'Bio cannot exceed 160 characters';
                      }
                      return null;
                    },
                  ),

                  const SizedBox(height: 32),

                  // Submit Button
                  PrimaryButton(
                    text: isEditing ? 'Save Changes' : 'Create Profile',
                    isLoading: isSaving,
                    onPressed: isSaving ? null : _submit,
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
