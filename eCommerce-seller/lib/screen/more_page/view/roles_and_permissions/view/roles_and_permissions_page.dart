import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/bloc/roles_bloc/roles_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/roles_and_permissions/repo/roles_repo.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/debouncer.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/subscription_plans/service/subscription_limit_service.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';

class RolesAndPermissionPage extends StatefulWidget {
  const RolesAndPermissionPage({super.key});

  @override
  State<RolesAndPermissionPage> createState() => _RolesAndPermissionPage();
}

class _RolesAndPermissionPage extends State<RolesAndPermissionPage> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final SubscriptionLimitService _limitService = SubscriptionLimitService();
  final Debouncer _debouncer = Debouncer(milliseconds: 500);

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    _debouncer.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent * 0.8) {
      final state = context.read<RolesBloc>().state;
      if (state.hasMore && !state.isPaginating && !state.isInitialLoading) {
        context.read<RolesBloc>().add(LoadMoreRoles());
      }
    }
  }

  @override
  Widget build(BuildContext context) {

    return BlocProvider(
      create: (context) =>
          RolesBloc(RolesRepo())..add(const LoadRolesInitial()),
      child: BlocConsumer<RolesBloc, RolesState>(
        listenWhen: (previous, current) =>
            previous.operationSuccess != current.operationSuccess ||
            previous.error != current.error,
        listener: (context, state) {
          if (state.error != null) {
            showCustomSnackbar(
              context: context,
              message: state.error!,
              isError: true,
            );
          } else if (state.operationSuccess == true) {
            showCustomSnackbar(
              context: context,
              message: state.operationMessage ?? 'Operation successful',
            );
          } else if (state.operationSuccess == false) {
            showCustomSnackbar(
              context: context,
              message: state.operationMessage ?? 'Operation failed',
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
                isHaveSearch: true,
                centerTitle: true,
                title: context.tr('roles', fallback: 'Roles'),
                searchController: _searchController,
                onSearchChanged: (value) {
                  _debouncer.run(() {
                    context.read<RolesBloc>().add(SearchRoles(value));
                  });
                },
                body: RefreshIndicator(
                  color: AppColors.primaryColor,
                  onRefresh: () async {
                    context.read<RolesBloc>().add(RefreshRoles());
                  },
                  child: Column(
                    children: [
                      Padding(
                        padding: UIUtils.pagePadding(screenType),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              context.tr('totalRolesWithCount', positionalArgs: [state.total ?? 0], fallback: "${context.tr('totalRoles')} (${state.total ?? 0})"),
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: UIUtils.body(screenType),
                              ),
                            ),
                            SecondaryButton(
                              text: context.tr('addNewRole', fallback: "Add new role"),
                              icon: Icons.add,
                              onPressed: () async {
                                _limitService.fetchUsageCounts();
                                final canCreate = await _limitService
                                    .canCreateRole();
                                if (!context.mounted) return;

                                if (!canCreate) {
                                  if (HiveStorage.activePlanId == null) {
                                    showCustomSnackbar(
                                      context: context,
                                      message:
                                          "you don't have any active plan please buy one",
                                      isError: true,
                                      actionLabel: 'view',
                                      onAction: () {
                                        context.push(
                                          AppRoutes.subscriptionPlans,
                                        );
                                      },
                                    );
                                  } else {
                                    showCustomSnackbar(
                                      context: context,
                                      message:
                                          context.tr('limitReached', fallback: "You have reached your plan limit"),
                                      isWarning: true,
                                    );
                                  }
                                  return;
                                }
                                _showAddRoleSheet(context, screenType);
                              },
                              height: 35,
                            ),
                          ],
                        ),
                      ),
                      Expanded(child: _buildBody(state, screenType)),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  Widget _buildBody(
    RolesState state,
    ScreenType screenType,
  ) {
    if (state.isInitialLoading) {
      return ListView.separated(
        padding: UIUtils.cardsPadding(screenType),
        separatorBuilder: (context, index) =>
            SizedBox(height: UIUtils.gapMD(screenType)),
        itemCount: 10,
        itemBuilder: (context, index) =>
            CardShimmer(type: 'roles', screenType: screenType),
      );
    }

    if (state.error != null && state.items.isEmpty) {
      return EmptyStateWidget(
        svgPath: ImagesPath.noOrderFoundSvg,
        title: context.tr('somethingWentWrong', fallback: 'Seems like there is some issue'),
        subtitle:
            state.error ?? context.tr('tryAgainLater', fallback: 'Please try again later'),
        actionText: context.tr('refresh', fallback: 'Refresh'),
        onAction: () {
          context.read<RolesBloc>().add(const LoadRolesInitial());
        },
      );
    }

    if (state.items.isEmpty && !state.isInitialLoading && !state.isRefreshing) {
      return EmptyStateWidget(
        svgPath: ImagesPath.noProductFoundSvg,
        title: context.tr('noRolesFound', fallback: "No roles found"),
        subtitle: context.tr('noRolesMessage', fallback: "You have not added any roles yet."),
        actionText: context.tr('tryAgain', fallback: 'Try Again'),
        onAction: () {
          context.read<RolesBloc>().add(const LoadRolesInitial());
        },
      );
    }

    return ListView.separated(
      controller: _scrollController,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: UIUtils.cardsPadding(screenType),
      separatorBuilder: (context, index) =>
          SizedBox(height: UIUtils.gapMD(screenType)),
      itemCount: state.items.length + (state.isPaginating ? 1 : 0),
      itemBuilder: (context, index) {
        if (index == state.items.length) {
          return CardShimmer(type: 'roles', screenType: screenType);
        }
        final data = state.items[index];

        return CustomCard(
          type: CardType.roles,
          data: data.toJson(),
          screenType: screenType,
          onEdit: () => _showAddRoleSheet(
            context,
            screenType,
            roleId: data.id,
            initialName: data.name,
          ),
          onDelete: () {
            final bool demoAllowed = DemoGuard.shouldProceed(context);
            if (!demoAllowed) {
              return;
            }

            if (!PermissionChecker.hasPermission(AppPermissions.roleDelete)) {
              showCustomSnackbar(
                context: context,
                message:
                    context.tr('noPermissionDeleteRole', fallback: "You don't have permission to delete roles"),
                isWarning: true,
              );
              return;
            }

            showAppAlertDialog(
              context: context,
              title: context.tr('deleteRole', fallback: "Delete Role"),
              message: context.tr(
                'deleteRoleConfirmation',
                fallback: "Are you sure you want to delete this role?",
              ),
              icon: Icons.delete_outline_rounded,
              isDestructive: true,
              confirmText: context.tr('delete', fallback: "Delete"),
              cancelText: context.tr('cancel', fallback: "Cancel"),
              onConfirm: () {
                context.read<RolesBloc>().add(DeleteRole(data.id));
              },
            );
          },
        );
      },
    );
  }

  void _showAddRoleSheet(
    BuildContext context,
    ScreenType screenType, {
    int? roleId,
    String? initialName,
  }) {
    final bloc = context.read<RolesBloc>();
    final nameController = TextEditingController(text: initialName);
    final formKey = GlobalKey<FormState>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
          left: 20,
          right: 20,
          top: 20,
        ),
        child: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey[300],
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                roleId == null
                    ? context.tr('addNewRole', fallback: "Add New Role")
                    : context.tr('editRole', fallback: "Edit Role"),
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 20),
              CustomTextField(
                controller: nameController,
                label: context.tr('roleName', fallback: "Role Name"),
                hint: "Administrator, Editor, etc.",
                isRequired: true,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return context.tr('roleNameRequired', fallback: "Please enter role name");
                  }
                  return null;
                },
              ),
              const SizedBox(height: 30),
              PrimaryButton(
                text: roleId == null
                    ? context.tr('addNewRole', fallback: "Add Role")
                    : context.tr('editRole', fallback: "Update Role"),
                onPressed: () {
                  final bool allowed = DemoGuard.shouldProceed(context);
                  if (!allowed) {
                    Navigator.pop(context);
                    return;
                  }

                  if (!formKey.currentState!.validate()) {
                    return;
                  }

                  final String requiredPermission = roleId == null
                      ? AppPermissions.roleCreate
                      : AppPermissions.roleEdit;

                  if (!PermissionChecker.hasPermission(requiredPermission)) {
                    Navigator.pop(context);
                    showCustomSnackbar(
                      context: context,
                      message: roleId == null
                          ? context.tr('noPermissionCreateRole', fallback: "You don't have permission to create roles")
                          : context.tr('noPermissionEditRole', fallback: "You don't have permission to edit roles"),
                      // isError: true,
                      isWarning: true,
                    );
                    return;
                  }

                  bloc.add(ManageRole(name: nameController.text, id: roleId));

                  Navigator.pop(context);
                },
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }
}
