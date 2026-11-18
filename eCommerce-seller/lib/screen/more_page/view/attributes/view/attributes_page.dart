import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/bloc/attributes_bloc/attributes_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/model/attributes_model.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/widgets/add_attribute_dialog.dart';
import 'package:hyper_commerce_seller/utils/debouncer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';

class AttributesPage extends StatefulWidget {
  const AttributesPage({super.key});

  @override
  State<AttributesPage> createState() => _AttributesPageState();
}

class _AttributesPageState extends State<AttributesPage> {
  final ScrollController _scrollController = ScrollController();
  final Debouncer _debouncer = Debouncer(milliseconds: 500);

  @override
  void initState() {
    super.initState();
    context.read<AttributesBloc>().add(LoadAttributesInitial(search: null));
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    _debouncer.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_isBottom) {
      context.read<AttributesBloc>().add(LoadMoreAttributes());
    }
  }

  bool get _isBottom {
    if (!_scrollController.hasClients) return false;
    final maxScroll = _scrollController.position.maxScrollExtent;
    final currentScroll = _scrollController.offset;
    return currentScroll >= (maxScroll * 0.9);
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ScreenSizeBloc, ScreenSizeState>(
      builder: (context, screenSizeState) {
        final screenType = screenSizeState.screenType;

        return CustomScaffold(
          centerTitle: true,
          showAppbar: true,
          title: context.tr('attributes', fallback: "Attributes"),
          isHaveSearch: true,
          onSearchChanged: (value) {
            _debouncer.run(() {
              context.read<AttributesBloc>().add(SearchAttributes(value));
            });
          },
          body: BlocListener<AttributesBloc, AttributesState>(
            listenWhen: (previous, current) =>
                previous.operationSuccess != current.operationSuccess &&
                current.operationSuccess != null,
            listener: (context, state) {
              if (state.operationSuccess == true) {
                showCustomSnackbar(
                  context: context,
                  message: state.operationMessage ?? "Operation successful",
                );
                context.read<AttributesBloc>().add(
                  ClearAttributeOperationState(),
                );
              } else if (state.operationSuccess == false) {
                showCustomSnackbar(
                  context: context,
                  message: state.error ?? state.operationMessage ?? 'Operation failed',
                  isError: true,
                );
                context.read<AttributesBloc>().add(
                  ClearAttributeOperationState(),
                );
              } else if (state.error != null && state.items.isNotEmpty) {
                // Global error (background refresh failed)
                showCustomSnackbar(
                  context: context,
                  message: state.error!,
                  isError: true,
                );
                context.read<AttributesBloc>().add(
                  ClearAttributeOperationState(),
                );
              }
            },
            child: BlocBuilder<AttributesBloc, AttributesState>(
              builder: (context, state) {
                // Removed early return for initial loading to show shimmers in the layout

                final items = state.items;
                final hasMore = state.hasMore;
                final total = state.total ?? 0;
                final isPaginating = state.isPaginating;

                if (state.error != null && items.isEmpty) {
                  return EmptyStateWidget(
                    svgPath: ImagesPath.noOrderFoundSvg,
                    title:
                        context.tr('somethingWentWrong', fallback: 'Seems like there is some issue'),
                    subtitle: context.tr('tryAgainLater', fallback: 'Please try again later'),
                    actionText: context.tr('tryAgain', fallback: 'Try Again'),
                    onAction: () {
                      context.read<AttributesBloc>().add(RefreshAttributes());
                    },
                  );
                }

                if (!state.isInitialLoading && !state.isRefreshing && items.isEmpty) {
                  return EmptyStateWidget(
                    svgPath: ImagesPath.noProductFoundSvg,
                    title: context.tr('noAttributesFound', fallback: 'No attribute found'),
                    subtitle:
                        context.tr('noAttributesAddedYet', fallback: 'You have not added any attribute yet'),
                    actionText: context.tr('addAttribute', fallback: 'Add Attribute'),
                    onAction: () {
                      if (!PermissionChecker.hasPermission(
                        AppPermissions.attributeCreate,
                      )) {
                        showCustomSnackbar(
                          context: context,
                          message:
                              context.tr('noPermissionCreateAttribute', fallback: "You don't have permission to add attributes"),
                          isWarning: true,
                        );
                        return;
                      }
                      if (!DemoGuard.shouldProceed(context)) return;

                      _showAddDialog(context);
                    },
                  );
                }

                return Column(
                  children: [
                    Padding(
                      padding: UIUtils.pagePadding(screenType),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          (state.isInitialLoading || state.isRefreshing) && state.items.isEmpty
                              ? CustomShimmer(
                                  width: 150,
                                  height: UIUtils.body(screenType),
                                )
                              : Text(
                                  context.tr('totalAttributesWithCount', positionalArgs: [total], fallback: "Total Attributes ($total)"),
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: UIUtils.body(screenType),
                                  ),
                                ),
                          Row(
                            children: [
                              if (state.isRefreshing)
                                const Padding(
                                  padding: EdgeInsets.only(right: 8.0),
                                  child: CustomLoadingIndicator(
                                    size: 16,
                                    strokeWidth: 2,
                                  ),
                                ),

                              (state.isInitialLoading || state.isRefreshing) && state.items.isEmpty
                                  ? CustomShimmer(
                                      width: 60,
                                      height: 35,
                                      borderRadius: BorderRadius.circular(
                                        UIUtils.radiusMD(screenType),
                                      ),
                                    )
                                  : SecondaryButton(
                                      text:
                                          context.tr('addAttribute', fallback: 'Add Attribute'),
                                      icon: Icons.add,
                                      onPressed: () {
                                        if (!PermissionChecker.hasPermission(
                                          AppPermissions.attributeCreate,
                                        )) {
                                          showCustomSnackbar(
                                            context: context,
                                            message:
                                                context.tr('noPermissionCreateAttribute', fallback: "You don't have permission to add attributes"),
                                            isWarning: true,
                                          );
                                          return;
                                        }
                                        if (!DemoGuard.shouldProceed(context)) {
                                          return;
                                        }

                                        _showAddDialog(context);
                                      },
                                      height: 35,
                                    ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: RefreshIndicator(
                        color: AppColors.primaryColor,
                        onRefresh: () async {
                          context.read<AttributesBloc>().add(
                            RefreshAttributes(),
                          );
                        },
                        child: ListView.separated(
                          controller: _scrollController,
                          padding: UIUtils.cardsPadding(screenType),
                          separatorBuilder: (context, index) =>
                              SizedBox(height: UIUtils.gapMD(screenType)),
                          itemCount:
                              (state.isInitialLoading || state.isRefreshing) && state.items.isEmpty
                              ? 10
                              : items.length +
                                    (hasMore ? (isPaginating ? 10 : 1) : 0),
                          itemBuilder: (context, index) {
                            if ((state.isInitialLoading || state.isRefreshing) && state.items.isEmpty) {
                              return CardShimmer(
                                type: 'attribute',
                                screenType: screenType,
                              );
                            }
                            if (index >= items.length) {
                              return CardShimmer(
                                type: 'attribute',
                                screenType: screenType,
                              );
                            }

                            final attribute = items[index];
                            return CustomCard(
                              type: CardType.attribute,
                              screenType: screenType,
                              data: {
                                'id': attribute.id,
                                'name': attribute.title,
                                'swatch_type': attribute.swatcheType,
                                'values_count': attribute.valuesCount,
                                'date': attribute.createdAt,
                              },
                              onTap: () {
                                context.pushNamed(
                                  AppRoutes.attributeValues,
                                  extra: attribute,
                                );
                              },
                              onEdit: () {
                                if (!PermissionChecker.hasPermission(
                                  AppPermissions.attributeEdit,
                                )) {
                                  showCustomSnackbar(
                                    context: context,
                                    message:
                                        context.tr('noPermissionEditAttribute', fallback: "You don't have permission to edit attributes"),
                                    isWarning: true,
                                  );
                                  return;
                                }
                                if (!DemoGuard.shouldProceed(context)) return;

                                _showAddDialog(context, attribute: attribute);
                              },
                              onDelete: () {
                                if (!PermissionChecker.hasPermission(
                                  AppPermissions.attributeDelete,
                                )) {
                                  showCustomSnackbar(
                                    context: context,
                                    message:
                                        context.tr('noPermissionDeleteAttribute', fallback: "You don't have permission to delete attributes"),
                                    isWarning: true,
                                  );
                                  return;
                                }
                                if (!DemoGuard.shouldProceed(context)) return;

                                showAppAlertDialog(
                                  context: context,
                                  title: context.tr('deleteAttribute', fallback: "Delete Attribute"),
                                  message: context.tr(
                                    'deleteAttributeConfirmation',
                                    fallback: "Are you sure you want to delete this attribute?",
                                  ),
                                  icon: Icons.delete_outline_rounded,
                                  isDestructive: true,
                                  confirmText: context.tr('delete', fallback: "Delete"),
                                  cancelText: context.tr('cancel', fallback: "Cancel"),
                                  onConfirm: () {
                                    context.read<AttributesBloc>().add(
                                      DeleteAttribute(attribute.id),
                                    );
                                  },
                                );
                              },
                            );
                          },
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        );
      },
    );
  }

  void _showAddDialog(BuildContext context, {Attribute? attribute}) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => AddAttributeDialog(attribute: attribute),
    );
  }
}
