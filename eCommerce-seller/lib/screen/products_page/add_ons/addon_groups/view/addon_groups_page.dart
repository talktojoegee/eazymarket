import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';

import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';

import '../../../../../config/colors.dart';
import '../../../../../service/api_base_helper.dart';
import '../../../../../service/demo_guard.dart';
import '../../../../../utils/image_path.dart';
import '../../../../../widgets/custom/card_shimmers.dart';
import '../../../../../widgets/custom/custom_alert_dialog.dart';
import '../../../../../widgets/custom/custom_card.dart';
import '../../../../../widgets/custom/custom_shimmer.dart';
import '../../../../../widgets/custom/custom_snackbar.dart';
import '../../../../../widgets/ui/empty_state_widget.dart';
import '../cubit/addon_groups_cubit.dart';
import '../cubit/addon_groups_state.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

class AddonGroupsPage extends StatefulWidget {
  const AddonGroupsPage({super.key});

  @override
  State<AddonGroupsPage> createState() => _AddonGroupsPageState();
}

class _AddonGroupsPageState extends State<AddonGroupsPage> {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final cubit = context.read<AddonGroupsCubit>();
      cubit.clearSearch();
      cubit.fetchAddonGroups();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      context.read<AddonGroupsCubit>().loadMore();
    }
  }

  Future<void> _onRefresh() async {
    await context.read<AddonGroupsCubit>().fetchAddonGroups(isRefresh: true);
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;

    return CustomScaffold(
      title: context.tr('addOnGroup', fallback: "Add-on Groups"),
      showAppbar: true,
      centerTitle: true,
      isHaveSearch: true,
      searchHint: context.tr('search', fallback: "Search"),
      searchController: _searchController,
      onSearchChanged: (query) {
        context.read<AddonGroupsCubit>().searchAddonGroups(query);
      },
      body: BlocConsumer<AddonGroupsCubit, AddonGroupsState>(
        listenWhen: (prev, curr) => prev.operationSuccess != curr.operationSuccess && curr.operationSuccess != null,
        listener: (context, state) {
          if (state.operationSuccess == true) {
            showCustomSnackbar(
              context: context,
              message:
                  state.operationMessage ?? context.tr('operationCompletedSuccessfully', fallback: "Operation completed successfully"),
            );
          } else if (state.operationSuccess == false) {
            showCustomSnackbar(
              context: context,
              message: state.operationMessage ?? "Something went wrong. Please try again.",
              isError: true,
            );
          }
        },
        builder: (context, state) {
          final bool isLoadingEmpty =
              state.fetchStatus == ApiStatus.initial ||
              (state.fetchStatus == ApiStatus.loading && state.addonGroups.isEmpty);

          if (!isLoadingEmpty && state.addonGroups.isEmpty) {
            return EmptyStateWidget(
              svgPath: ImagesPath.noProductFoundSvg,
              title: context.tr('noAddonGroupFound', fallback: "No Add on group found"),
              subtitle: context.tr('noAddonGroupFoundMessage', fallback: "You haven't added any add on group yet."),
              actionText: context.tr('addNewGroup', fallback: 'Add New Group'),
              onAction: () async {
                final cubit = context.read<AddonGroupsCubit>();
                await context.pushNamed(AppRoutes.createAddOnGroup, extra: {'model': null});
                cubit.fetchAddonGroups(isRefresh: true);
              },
            );
          }

          return Column(
            children: [
              // Header section
              Padding(
                padding: UIUtils.pagePadding(screenType),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    isLoadingEmpty
                        ? CustomShimmer(width: 150, height: UIUtils.sectionTitle(screenType))
                        : Text(
                            "${context.tr('totalAddonGroups', fallback: "Total Add-on Groups")} (${state.total})",
                            style: TextStyle(fontSize: UIUtils.tileTitle(screenType), fontWeight: FontWeight.w600),
                          ),

                    isLoadingEmpty
                        ? CustomShimmer(
                            width: 80,
                            height: 36,
                            borderRadius: BorderRadius.circular(UIUtils.radiusMD(screenType)),
                          )
                        : SecondaryButton(
                            icon: Icons.add,
                            text: context.tr('add', fallback: 'Add'),
                            onPressed: () async {
                              final cubit = context.read<AddonGroupsCubit>();
                              await context.pushNamed(AppRoutes.createAddOnGroup, extra: {'model': null});
                              cubit.fetchAddonGroups(isRefresh: true);
                            },
                            height: 36,
                          ),
                  ],
                ),
              ),
              Expanded(
                child: RefreshIndicator(
                  color: AppColors.primaryColor,
                  onRefresh: _onRefresh,
                  child: ListView.builder(
                    controller: _scrollController,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: UIUtils.cardsPadding(screenType),
                    itemCount: isLoadingEmpty
                        ? 10
                        : state.addonGroups.length +
                              (state.hasMore ? (state.fetchStatus == ApiStatus.loadingMore ? 3 : 1) : 0),
                    itemBuilder: (context, index) {
                      if (isLoadingEmpty) {
                        return Padding(
                          padding: EdgeInsets.only(bottom: UIUtils.gapMD(screenType)),
                          child: CardShimmer(type: 'addons', screenType: screenType),
                        );
                      }

                      if (index >= state.addonGroups.length) {
                        if (state.fetchStatus == ApiStatus.loadingMore) {
                          return Padding(
                            padding: EdgeInsets.only(bottom: UIUtils.gapMD(screenType)),
                            child: CardShimmer(type: 'addons', screenType: screenType),
                          );
                        }
                        return const Center(
                          child: Padding(padding: EdgeInsets.all(16.0), child: CustomLoadingIndicator()),
                        );
                      }

                      final addonGroup = state.addonGroups[index];

                      return Padding(
                        padding: EdgeInsets.only(bottom: UIUtils.gapMD(screenType)),
                        child: CustomCard(
                          type: CardType.addonGroup,
                          screenType: screenType,
                          data: {
                            'id': addonGroup.id,
                            'title': addonGroup.title,
                            'is_required': addonGroup.isRequired,
                            'status': addonGroup.status,
                            'selection_type': addonGroup.selectionType,
                            'items_count': addonGroup.itemsCount,
                            'created_at': addonGroup.createdAt,
                          },
                          onEdit: () async {
                            if (!DemoGuard.shouldProceed(context)) return;
                            final cubit = context.read<AddonGroupsCubit>();
                            await context.pushNamed(AppRoutes.createAddOnGroup, extra: {'model': addonGroup});
                            cubit.fetchAddonGroups(isRefresh: true);
                          },
                          onDelete: () {
                            if (!DemoGuard.shouldProceed(context)) return;

                            showAppAlertDialog(
                              context: context,
                              title: context.tr('deleteAddonGroup', fallback: "Delete Add-on Group"),
                              message:
                                  context.tr('deleteAddonGroupConfirmation', fallback: "Are you sure you want to delete this add-on group?"),
                              onConfirm: () {
                                context.read<AddonGroupsCubit>().deleteAddonGroup(addonGroup.id);
                              },
                            );
                          },
                        ),
                      );
                    },
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
