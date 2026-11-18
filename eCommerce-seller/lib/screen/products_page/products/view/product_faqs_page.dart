import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/bloc/product_faq_bloc/product_faq_bloc.dart';
import 'package:hyper_commerce_seller/utils/debouncer.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/widgets/add_faq_bottom_sheet.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';

class ProductFaqsPage extends StatefulWidget {
  final int? productId;
  const ProductFaqsPage({super.key, this.productId});

  @override
  State<ProductFaqsPage> createState() => _ProductFaqsPageState();
}

class _ProductFaqsPageState extends State<ProductFaqsPage> {
  final ScrollController _scrollController = ScrollController();
  final TextEditingController _searchController = TextEditingController();
  final Debouncer _debouncer = Debouncer(milliseconds: 500);

  @override
  void initState() {
    super.initState();
    _fetchFaqs(isRefresh: true);
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debouncer.dispose();
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      context.read<ProductFaqBloc>().add(LoadMoreFaqs());
    }
  }

  void _fetchFaqs({bool isRefresh = false}) {
    if (isRefresh) {
      context.read<ProductFaqBloc>().add(
        LoadFaqsInitial(
          productId: widget.productId,
          search: _searchController.text,
        ),
      );
    }
  }

  void _handleSave(
    int? id,
    int? productId,
    String question,
    String answer,
    String status,
  ) {
    context.read<ProductFaqBloc>().add(
      SaveProductFaq(
        id: id,
        productId: productId,
        question: question,
        answer: answer,
        status: status,
      ),
    );
  }

  void _handleDelete(int id) {
    context.read<ProductFaqBloc>().add(DeleteProductFaq(id));
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;

    return CustomScaffold(
      title: context.tr('productFaqs', fallback: 'Product FAQs'),
      showAppbar: true,
      centerTitle: true,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          if (!PermissionChecker.hasPermission(
            AppPermissions.productFaqCreate,
          )) {
            showCustomSnackbar(
              context: context,
              message:
                  context.tr('noPermissionAddFaq', fallback: 'You don\'t have permission to add FAQs'),
              isWarning: true,
            );
            return;
          }
          if (!DemoGuard.shouldProceed(context)) return;

          AddFaqBottomSheet.show(
            context,
            productId: widget.productId,
            onSave: _handleSave,
          );
        },
        backgroundColor: AppColors.primaryColor,
        icon: const Icon(Icons.add, color: Colors.white),
        label: Text(
          context.tr('addFaq', fallback: 'Add FAQ'),
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      isHaveSearch: true,
      onSearchChanged: (value) {
        _searchController.text = value;
        _debouncer.run(() {
          context.read<ProductFaqBloc>().add(SearchFaqs(value));
        });
      },
      body: BlocListener<ProductFaqBloc, ProductFaqState>(
        listenWhen: (previous, current) =>
            previous.operationSuccess != current.operationSuccess &&
            current.operationSuccess != null,
        listener: (context, state) {
          if (state.operationSuccess == true) {
            showCustomSnackbar(
              context: context,
              message:
                  state.operationMessage ??
                  context.tr('operationCompletedSuccessfully', fallback: "Operation completed successfully"),
            );
            context.read<ProductFaqBloc>().add(ClearProductFaqOperation());
          } else if (state.operationSuccess == false && state.error != null) {
            showCustomSnackbar(
              context: context,
              message: state.error!,
              isError: true,
            );
            context.read<ProductFaqBloc>().add(ClearProductFaqOperation());
          }
        },
        child: BlocBuilder<ProductFaqBloc, ProductFaqState>(
          builder: (context, state) {
            final isLoading = state.isInitialLoading;
            final isRefreshing = state.isRefreshing;
            final isPaginating = state.isPaginating;
            final values = state.items;

            if (state.error != null && values.isEmpty && !isLoading) {
              return EmptyStateWidget(
                svgPath: ImagesPath.noOrderFoundSvg,
                title:
                    context.tr('somethingWentWrong', fallback: 'Seems like there is some issue'),
                subtitle: context.tr('tryAgainLater', fallback: 'Please try again later'),
                actionText: context.tr('tryAgain', fallback: 'Try Again'),
                onAction: () {
                  context.read<ProductFaqBloc>().add(RefreshFaqs());
                },
              );
            }

            if (!isLoading && !isRefreshing && values.isEmpty) {
              return EmptyStateWidget(
                svgPath: ImagesPath.noProductFoundSvg,
                title: context.tr('noFaqsFound', fallback: 'No FAQs Found'),
                subtitle:
                    context.tr('noFaqsMessage', fallback: 'You have not added any FAQs yet.'),
                // actionText: context.tr('addFaq', fallback: 'Add FAQ'),
                // onAction: () {
                //   if (!PermissionChecker.hasPermission(
                //     AppPermissions.productFaqCreate,
                //   )) {
                //     showCustomSnackbar(
                //       context: context,
                //       message:
                //           context.tr('noPermissionAddFaq') ??
                //           "You don't have permission to add product FAQs",
                //       isWarning: true,
                //     );
                //     return;
                //   }
                //   if (!DemoGuard.shouldProceed(context)) return;
                //
                //   AddFaqBottomSheet.show(
                //     context,
                //     productId: widget.productId,
                //     onSave: _handleSave,
                //   );
                // },
              );
            }

            return RefreshIndicator(
              color: AppColors.primaryColor,
              onRefresh: () async {
                context.read<ProductFaqBloc>().add(RefreshFaqs());
              },
              child: ListView.separated(
                controller: _scrollController,
                padding: UIUtils.pagePadding(screenType),
                separatorBuilder: (context, index) =>
                    SizedBox(height: UIUtils.gapMD(screenType)),
                itemCount: (isLoading || isRefreshing) && values.isEmpty
                    ? 10
                    : (values.length + (isPaginating ? 1 : 0)),
                itemBuilder: (context, index) {
                  if ((isLoading || isRefreshing) && values.isEmpty) {
                    return CardShimmer(type: 'faq', screenType: screenType);
                  }

                  if (index >= values.length) {
                    return CardShimmer(type: 'faq', screenType: screenType);
                  }

                  final faq = values[index];
                  return CustomCard(
                    type: CardType.faq,
                    screenType: screenType,
                    data: {
                      'id': faq.id,
                      'question': faq.question,
                      'answer': faq.answer,
                      'status': faq.status,
                      'product_name': faq.product?.title,
                    },
                    onEdit: () {
                      if (!PermissionChecker.hasPermission(
                        AppPermissions.productFaqEdit,
                      )) {
                        showCustomSnackbar(
                          context: context,
                          message:
                              context.tr('noPermissionEditFaq', fallback: "You don't have permission to edit product FAQs"),
                          isWarning: true,
                        );
                        return;
                      }
                      if (!DemoGuard.shouldProceed(context)) return;

                      AddFaqBottomSheet.show(
                        context,
                        faq: faq,
                        onSave: _handleSave,
                      );
                    },
                    onDelete: () {
                      if (!PermissionChecker.hasPermission(
                        AppPermissions.productFaqDelete,
                      )) {
                        showCustomSnackbar(
                          context: context,
                          message:
                              context.tr('noPermissionDeleteFaq', fallback: "You don't have permission to delete product FAQs"),
                          isWarning: true,
                        );
                        return;
                      }
                      if (!DemoGuard.shouldProceed(context)) return;

                      showAppAlertDialog(
                        context: context,
                        title: context.tr('deleteProductFaq', fallback: "Delete Product FAQ"),
                        message: context.tr(
                          'deleteProductFaqConfirmation',
                          fallback: "Are you sure you want to delete this FAQ?",
                        ),
                        icon: Icons.delete_outline_rounded,
                        isDestructive: true,
                        confirmText: context.tr('delete', fallback: "Delete"),
                        cancelText: context.tr('cancel', fallback: "Cancel"),
                        onConfirm: () {
                          _handleDelete(faq.id);
                        },
                      );
                    },
                  );
                },
              ),
            );
          },
        ),
      ),
    );
  }
}
