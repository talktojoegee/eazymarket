// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_bloc.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_event.dart';
import 'package:hyper_commerce_seller/screen/auth/bloc/auth/auth_state.dart';
import 'package:hyper_commerce_seller/screen/more_page/model/more_menu.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/profile/bloc/profile_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/widgets/custom_section.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/user_helper.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/service/master_api_service.dart';

class MorePage extends StatefulWidget {
  const MorePage({super.key});

  @override
  State<MorePage> createState() => _MorePageState();
}

class _MorePageState extends State<MorePage> {
  @override
  void initState() {
    super.initState();
    context.read<ProfileBloc>().add(const ProfileFetchEvent());
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final pageBgColor = isDark ? theme.colorScheme.surface : const Color(0xFFF5F5F7);

    return CustomScaffold(
      backgroundColor: pageBgColor,
      title: 'My account',
      showAppbar: true,
      body: BlocListener<AuthBloc, AuthState>(
        listener: (context, state) {
          if (state is AuthInitial) {
            showCustomSnackbar(context: context, message: context.tr('logoutMessage', fallback: 'Logout successful'));
            context.go('/login');
          } else if (state is AuthFailure) {
            showCustomSnackbar(context: context, message: state.error, isError: true);
          }
        },
        child: SingleChildScrollView(
          child: Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(context).viewPadding.bottom + 70,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // White Header Container with "My account" & Profile Row
                SizedBox(
                  // color: headerBgColor,
                  width: double.infinity,
                  child: SafeArea(
                    bottom: false,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Profile Card Row
                          BlocBuilder<ProfileBloc, ProfileState>(
                            builder: (context, state) {
                              String name = UserHelper.name;
                              String email = UserHelper.email;
                              String mobile = UserHelper.mobile;
                              String profileImage = UserHelper.profileImage;

                              if (state is ProfileLoaded && state.profile.data != null) {
                                name = state.profile.data!.name ?? name;
                                email = state.profile.data!.email ?? email;
                                mobile = state.profile.data!.mobile ?? mobile;
                                profileImage = state.profile.data!.profileImage ?? profileImage;
                              }
                              else if (state is ProfileUpdateSuccess && state.updatedProfile.data != null) {
                                name = state.updatedProfile.data!.name ?? name;
                                email = state.updatedProfile.data!.email ?? email;
                                mobile = state.updatedProfile.data!.mobile ?? mobile;
                                profileImage = state.updatedProfile.data!.profileImage ?? profileImage;
                              } else if (state is ProfileUpdating && state.currentProfile?.data != null) {
                                name = state.currentProfile!.data!.name ?? name;
                                email = state.currentProfile!.data!.email ?? email;
                                mobile = state.currentProfile!.data!.mobile ?? mobile;
                                profileImage = state.currentProfile!.data!.profileImage ?? profileImage;
                              }

                              final subtitleText = email.isNotEmpty
                                  ? email
                                  : mobile.isNotEmpty
                                      ? mobile
                                      : 'Member';

                              return InkWell(
                                onTap: () {
                                  debugPrint('Edit Profile tapped');
                                  context.pushNamed(AppRoutes.profilePage);
                                },
                                borderRadius: BorderRadius.circular(12),
                                child: Row(
                                  children: [
                                    // Dark Avatar Circle with white user icon or image
                                    Container(
                                      width: 60,
                                      height: 60,
                                      decoration: BoxDecoration(
                                        shape: BoxShape.circle,
                                        color: isDark ? const Color(0xFF1E293B) : const Color(0xFF0F172A),
                                      ),
                                      clipBehavior: Clip.antiAlias,
                                      child: profileImage.isNotEmpty
                                          ? CustomNetworkImage(
                                              imageUrl: profileImage,
                                              width: 60,
                                              height: 60,
                                              isCircle: true,
                                              fit: BoxFit.cover,
                                              errorWidget: const Icon(
                                                Icons.person,
                                                size: 32,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Center(
                                              child: Icon(
                                                Icons.person,
                                                size: 32,
                                                color: Colors.white,
                                              ),
                                            ),
                                    ),
                                    const SizedBox(width: 16),

                                    // Name and Subtitle
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Text(
                                            name.isNotEmpty ? name : 'User',
                                            style: TextStyle(
                                              color: isDark ? Colors.white : const Color(0xFF111827),
                                              fontSize: 20,
                                              fontWeight: FontWeight.bold,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            subtitleText,
                                            style: TextStyle(
                                              color: isDark ? Colors.white70 : const Color(0xFF6B7280),
                                              fontSize: 14,
                                              fontWeight: FontWeight.w400,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),

                                    /*// Edit Icon
                                    Icon(
                                      TablerIcons.edit,
                                      color: isDark ? Colors.white70 : const Color(0xFF6B7280),
                                      size: 20,
                                    ),*/
                                  ],
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                  ),
                ),

                // Hub — one row per group, each opening its own page.
                SettingsSection(tiles: _buildHubTiles(context)),
                const SizedBox(height: 22),

                // Logout stays on the hub; account deletion lives in Profile.
                SettingsSection(tiles: [_logoutTile(context)]),
                const SizedBox(height: 32),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// One tile per hub destination.
  List<SettingsTileData> _buildHubTiles(BuildContext context) {
    return buildMoreGroups(context).map((group) {
      return SettingsTileData(
        img: '',
        icon: group.icon,
        title: group.title,
        subtitle: group.subtitle,
        onTap: () => context.pushNamed(AppRoutes.moreGroup, extra: group.id),
      );
    }).toList();
  }

  SettingsTileData _logoutTile(BuildContext context) {
    return SettingsTileData(
      img: ImagesPath.logoutSvg,
      title: context.tr('logout', fallback: "Logout"),
      color: AppColors.errorColor,
      onTap: () {
        showAppAlertDialog(
          context: context,
          title: context.tr('logoutConfirmationTitle', fallback: "Logout"),
          message: context.tr('logoutConfirmationMessage', fallback: "Are you sure you want to log out from your account?"),
          confirmText: context.tr('logoutConfirmButton', fallback: "Yes, Logout"),
          cancelText: context.tr('logoutCancelButton', fallback: "Cancel"),
          isDestructive: true,
          onConfirm: () {
            // Clear ALL BLoC data FIRST while context is still valid
            MasterApiService.clearAllApisData(context);
            // Then trigger the actual logout (clears token & Hive)
            context.read<AuthBloc>().add(AuthLogoutRequested());
          },
          onCancel: () {
            debugPrint("Logout cancelled");
          },
        );
      },
    );
  }
}
