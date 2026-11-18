import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/bloc/permissions_bloc/permissions_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/model/permission_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/repo/permissions_repo.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';

class PermissionPage extends StatelessWidget {
  final String role;
  final int roleId;

  const PermissionPage({super.key, required this.role, required this.roleId});

  @override
  Widget build(BuildContext context) {

    return BlocProvider(
      create: (context) =>
          PermissionsBloc(PermissionsRepo())..add(GetPermissions(role)),
      child: BlocConsumer<PermissionsBloc, PermissionsState>(
        listener: (context, state) {
          log('Permission State $state');
          if (state is PermissionsSyncSuccess) {
            showCustomSnackbar(context: context, message: state.message);
            Navigator.pop(context);
          } else if (state is PermissionsError) {
            showCustomSnackbar(
              context: context,
              message: state.message,
              isError: true,
            );
          }
        },
        builder: (context, state) {
          return BlocBuilder<ScreenSizeBloc, ScreenSizeState>(
            builder: (context, screenSizeState) {
              final screenType = screenSizeState.screenType;

              return CustomScaffold(
                showAppbar: true,
                centerTitle: true,
                title: context.tr('permission', fallback: 'Permission'),
                body: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: UIUtils.pagePadding(screenType),
                      child: Text(
                        "Permission for: $role",
                        style: TextStyle(
                          fontSize: UIUtils.body(screenType),
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    Expanded(child: _buildBody(state, screenType, context)),
                    if (state is PermissionsLoaded)
                      Padding(
                        padding: UIUtils.pagePadding(screenType),
                        child: PrimaryButton(
                          text: state.isSyncing
                              ? 'Saving...'
                              : (context.tr('addPermission', fallback: 'Save Permissions')),
                          // Disable while a sync is in flight so double-taps
                          // don't fire two requests.
                          onPressed: state.isSyncing
                              ? null
                              : () {
                                  if (!DemoGuard.shouldProceed(context)) {
                                    return;
                                  }

                                  if (!PermissionChecker.hasPermission(
                                    AppPermissions.rolePermissionEdit,
                                  )) {
                                    showCustomSnackbar(
                                      context: context,
                                      message:
                                          context.tr('noPermissionEditRolePermissions', fallback: "You don't have permission to modify role permissions"),
                                      isWarning: true,
                                    );
                                    return;
                                  }

                                  context.read<PermissionsBloc>().add(
                                        SyncPermissions(role),
                                      );
                                },
                        ),
                      ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  Widget _buildBody(
    PermissionsState state,
    ScreenType screenType,
      BuildContext ctx,
  ) {
    if (state is PermissionsLoading) {
      return ListView.separated(
        padding: UIUtils.cardsPadding(screenType),
        separatorBuilder: (context, index) =>
            SizedBox(height: UIUtils.gapMD(screenType)),
        itemCount: 5,
        itemBuilder: (context, index) =>
            CardShimmer(type: 'permission', screenType: screenType),
      );
    }

    if (state is PermissionsLoaded) {
      final grouped = state.data.groupedPermissions;
      if (grouped == null || grouped.groups.isEmpty) {
        // Builder gives us a context under the PermissionsBloc provider so
        // the Refresh button can actually dispatch. Previously the callback
        // was `() {}` and did nothing at all.
        return Builder(
          builder: (ctx) => EmptyStateWidget(
            svgPath: ImagesPath.noProductFoundSvg,
            title: ctx.tr('noPermissionsFound', fallback: 'No permissions found'),
            subtitle:
                ctx.tr('noPermissionsAddedYet', fallback: 'No permissions are available for this role'),
            actionText: ctx.tr('refresh', fallback: 'Refresh'),
            onAction: () =>
                ctx.read<PermissionsBloc>().add(GetPermissions(role)),
          ),
        );
      }

      final groups = grouped.groups;

      return ListView.separated(
        padding: UIUtils.cardsPadding(screenType),
        itemCount: groups.length,
        separatorBuilder: (context, index) =>
            SizedBox(height: UIUtils.gapMD(screenType)),
        itemBuilder: (context, index) {
          return _PermissionGroupCard(
            group: groups[index],
            assigned: state.currentAssigned,
            screenType: screenType,
          );
        },
      );
    }

    if(state is PermissionsError) {
      return errorState(ctx);
    }

    return const SizedBox.shrink();
  }

  Widget errorState (BuildContext ctx) {
    return Expanded(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: AppColors.primaryColor.withValues(alpha: 0.10),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.admin_panel_settings_outlined,
                  size: 40,
                  color: AppColors.primaryColor,
                ),
              ),

              const SizedBox(height: 20),

              Text(
                'No Permissions Found',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 8),

              Text(
                'No permissions are available for this account at the moment.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  height: 1.5,
                  color: Colors.grey.shade600,
                ),
              ),

              const SizedBox(height: 24),

              OutlinedButton.icon(
                onPressed: () {
                  ctx.read<PermissionsBloc>().add(GetPermissions(role));
                },
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Refresh'),
                style: OutlinedButton.styleFrom(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PermissionGroupCard extends StatelessWidget {
  final PermissionGroup group;
  final List<String> assigned;
  final ScreenType screenType;

  const _PermissionGroupCard({
    required this.group,
    required this.assigned,
    required this.screenType,
  });

  @override
  Widget build(BuildContext context) {
    final isAllSelected = group.permissions.every((p) => assigned.contains(p));

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(UIUtils.radiusLG(screenType)),
        border: Border.all(
          color: Theme.of(context).colorScheme.outlineVariant,
          width: 0.5,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Padding(
                padding: UIUtils.cardPadding(screenType),
                child: Text(
                  group.name,
                  style: TextStyle(
                    fontSize: UIUtils.body(screenType),
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsetsGeometry.directional(end: 10.0),
                child: GestureDetector(
                  onTap: () {
                    context.read<PermissionsBloc>().add(
                      ToggleMultiplePermissions(
                        group.permissions,
                        !isAllSelected,
                      ),
                    );
                  },
                  child: Container(
                    width: 24,
                    height: 24,
                    decoration: BoxDecoration(
                      color: isAllSelected
                          ? AppColors.primaryColor
                          : Colors.transparent,
                      border: Border.all(
                        color: isAllSelected
                            ? AppColors.primaryColor
                            : Colors.grey,
                        width: 0.5,
                      ),
                      // shape: BoxShape.circle
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: isAllSelected
                        ? const Center(
                      child: Icon(
                        Icons.check,
                        size: 15,
                        color: Colors.black,
                      ),
                    )
                        : null,
                  ),
                ),
              )
            ],
          ),
          const Divider(height: 1),
          ...group.permissions.map((permission) {
            final isSelected = assigned.contains(permission);
            return ListTile(
              contentPadding: EdgeInsetsDirectional.only(start: 10.0),
              dense: true,
              leading: GestureDetector(
                onTap: () {
                  context.read<PermissionsBloc>().add(
                    TogglePermission(permission),
                  );
                },
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: isSelected
                        ? AppColors.primaryColor
                        : Colors.transparent,
                    border: Border.all(
                      color: isSelected
                          ? AppColors.primaryColor
                          : Colors.grey,
                      width: 0.5,
                    ),
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: isSelected
                      ? const Center(
                    child: Icon(
                      Icons.check,
                      size: 15,
                      color: Colors.black,
                    ),
                  )
                      : null,
                ),
              ),

              title: Text(
                _formatPermissionName(permission),
                style: TextStyle(
                  fontSize: UIUtils.tileSubtitle(screenType),
                ),
              ),

              onTap: () {
                context.read<PermissionsBloc>().add(
                  TogglePermission(permission),
                );
              },
            );
          }),

          /*CheckboxListTile(
            title: Text(
              "Select All",
              style: TextStyle(
                fontSize: UIUtils.tileSubtitle(screenType),
                fontWeight: FontWeight.bold,
              ),
            ),
            value: isAllSelected,
            onChanged: (val) {
              context.read<PermissionsBloc>().add(
                ToggleMultiplePermissions(group.permissions, val == true),
              );
            },
            controlAffinity: ListTileControlAffinity.leading,
            dense: true,
            activeColor: AppColors.primaryColor,
            checkColor: Colors.black,
          ),*/
        ],
      ),
    );
  }

  String _formatPermissionName(String permission) {
    final parts = permission.split('.');
    if (parts.length > 1) {
      final name = parts.last;
      return name[0].toUpperCase() + name.substring(1);
    }
    return permission;
  }
}
