import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/add_product_header.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/add_product_process_bottom_sheet.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/category_selection_step.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/product_info_step.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/policies_features_step.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/product_variation_step.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/product_images_step.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/product_description_step.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/steps/pricing_taxes_step.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

class AddProductPage extends StatefulWidget {
  final bool isEdit;
  const AddProductPage({super.key, required this.isEdit});

  @override
  State<AddProductPage> createState() => _AddProductPageState();
}

class _AddProductPageState extends State<AddProductPage> {
  int _currentStep = 1;
  final int _totalSteps = 7;
  bool _isLoadingShowing = false;

  @override
  void initState() {
    super.initState();
  }

  @override
  void dispose() {
    super.dispose();
  }

  void _onStepChanged(int targetStep) {
    if (targetStep == _currentStep) return;

    final bloc = context.read<AddProductBloc>();
    final data = bloc.state.productData;

    // Allow going back anytime
    if (targetStep < _currentStep) {
      FocusScope.of(context).unfocus();
      setState(() => _currentStep = targetStep);
      return;
    }

    // Going forward → only allow if current step is valid
    final (bool currentOk, String? error) = _validateStep(_currentStep, data);

    if (!currentOk) {
      showCustomSnackbar(
        context: context,
        message: error ?? context.tr('pleaseCompleteCurrentStep'),
        isError: true,
      );
      return;
    }

    // Optional: also check all previous steps (more strict)
    for (int i = 1; i < targetStep; i++) {
      if (!_isStepCompleted(i, data)) {
        showCustomSnackbar(
          context: context,
          message: context.tr(
            'stepMustBeCompletedBeforeJumping',
            positionalArgs: [i],
            fallback: 'Step $i must be completed before jumping',
          ),
          isError: true,
        );
        return;
      }
    }

    FocusScope.of(context).unfocus();
    setState(() => _currentStep = targetStep);
  }

  String _getStepTitle(BuildContext context, int step) {
    switch (step) {
      case 1:
        return context.tr('selectCategory');
      case 2:
        return context.tr('productInformation');
      case 3:
        return context.tr('policiesAndFeatures');
      case 4:
        return context.tr('productVariation');
      case 5:
        return context.tr('productImages');
      case 6:
        return context.tr('productsDescription');
      case 7:
        return context.tr('pricingAndTaxes');
      default:
        return "";
    }
  }

  void _onNextStep() {
    final bloc = context.read<AddProductBloc>();
    final data = bloc.state.productData;

    final (bool ok, String? error) = _validateStep(_currentStep, data);

    if (ok) {
      if (_currentStep < _totalSteps) {
        _onStepChanged(_currentStep + 1);
      } else {
        _onAddProductPressed();
      }
    } else {
      showCustomSnackbar(
        context: context,
        message:
            error ??
            context.tr('pleaseCompleteallRequiredFieldsInPreviousSteps'),
        isError: true,
      );
    }
  }

  (bool, String?) _validateStep(int step, ProductData data) {
    switch (step) {
      case 1:
        if (data.categoryId == null) {
          return (false, context.tr('selectAtLeastOneCategory'));
        }
        return (true, null);

      case 2:
        if (data.title == null || data.title!.trim().isEmpty) {
          return (false, context.tr('productTitleRequired'));
        }
        if (data.isCancelable &&
            (data.cancelableTill == null || data.cancelableTill!.trim().isEmpty)) {
          return (false, "Please select cancelable till status");
        }
        return (true, null);

      case 3:
        final min = data.minimumOrderQuantity;
        final stepSize = data.quantityStepSize;
        final total = data.totalAllowedQuantity;

        // Mandatory fields
        if (min <= 0) {
          return (false, context.tr('minOrderQtyPositive'));
        }
        if (stepSize <= 0) {
          return (false, context.tr('qtyStepSizePositive'));
        }

        // Optional total – but when provided, must follow rules
        if (total != 0) {
          // total is explicitly set → validate it
          if (total < 0) {
            return (false, context.tr('totalAllowedQtyPositive'));
          }
          if (total < min) {
            return (
            false,
            context.tr('totalAllowedQtyMinOrderConflict'),
            );
          }
          if (min % stepSize != 0) {
            return (
            false,
            context.tr('minOrderQtyDivisibleByStep'),
            );
          }
        } else {
          // total is null / 0 / empty → no upper limit
          // Still need to check that min is compatible with step
          if (min % stepSize != 0) {
            return (
            false,
            context.tr('minOrderQtyDivisibleByStep'),
            );
          }
          // No other checks needed when there's no total cap
        }

        return (true, null);

      case 4:
        if (data.type == 'simple') {
          if (data.barcode == null || data.barcode!.trim().isEmpty) {
            return (false, context.tr('barcodeRequired'));
          }
        } else if (data.type == 'kit') {
          if (data.kitStoreId == null) {
            return (false, context.tr('selectStore', fallback: 'Please select a store for the kit'));
          }
          if (data.kitComponents.isEmpty) {
            return (false, 'Please add at least one product to the kit');
          }
          if (data.barcode == null || data.barcode!.trim().isEmpty) {
            return (false, context.tr('barcodeRequired', fallback: 'Barcode is required'));
          }
          final price = double.tryParse(data.kitPrice ?? data.globalPrice ?? '');
          if (price == null || price <= 0) {
            return (false, 'Please enter a valid price for the kit');
          }
          for (final comp in data.kitComponents) {
            if (comp.quantity <= 0) {
              return (false, 'Quantity in pack must be at least 1 for ${comp.productTitle}');
            }
          }
          final hasAvailableLocation = data.locationInventory.any(
            (inv) => inv.sellFromThisLocation == true,
          );
          if (!hasAvailableLocation) {
            return (false, 'Please select at least one location where this kit is available for sale');
          }
        } else {
          if (data.variants.isEmpty) {
            return (false, context.tr('addAtLeastOneVariant'));
          }
          bool hasDefault = false;
          for (var v in data.variants) {
            if (v.isDefault) hasDefault = true;
            log('variant name: ${v.name}');
            if (v.name == null || v.name!.trim().isEmpty) {
              return (false, context.tr('allVariantsMustHaveName'));
            }
            if (v.barcode == null || v.barcode!.trim().isEmpty) {
              return (false, context.tr('barcodeRequiredForAllVariants'));
            }
          }
          if (!hasDefault) {
            return (false, context.tr('selectAtLeastOneDefaultVariant'));
          }
        }
        return (true, null);

      case 5:
        if (data.mainImage == null) {
          return (false, context.tr('mainProductImageCompulsory'));
        }
        if (data.otherImages.length > 15) {
          return (false, "Maximum limit of 15 additional images reached");
        }
        return (true, null);

      case 6:
        if (data.shortDescription == null ||
            data.shortDescription!.trim().isEmpty) {
          return (false, context.tr('shortDescriptionCompulsory'));
        }
        if (data.description == null || _isHtmlEmpty(data.description!)) {
          return (false, context.tr('fullDescriptionCompulsory'));
        }
        return (true, null);

      case 7:
        // Validate global pricing fields
        if (data.globalPrice == null || data.globalPrice!.trim().isEmpty) {
          return (false, "Price is required");
        }
        if (data.globalCost == null || data.globalCost!.trim().isEmpty) {
          return (false, "Cost is required");
        }
        if (data.globalSku == null || data.globalSku!.trim().isEmpty) {
          return (false, "SKU is required");
        }

        // Validate special price is not greater than price
        final double? price = double.tryParse(data.globalPrice!);
        final double? specialPrice =
            data.globalSpecialPrice != null && data.globalSpecialPrice!.isNotEmpty
                ? double.tryParse(data.globalSpecialPrice!)
                : null;
        if (price != null && specialPrice != null && specialPrice > price) {
          return (false, "Special price cannot be greater than regular price");
        }

        // Validate at least one location is enabled for selling
        if (data.locationInventory.isNotEmpty &&
            !data.locationInventory.any((inv) => inv.sellFromThisLocation)) {
          return (false, "Please pick at least one location to sell from");
        }

        return (true, null);

      default:
        return (true, null);
    }
  }

  bool _isHtmlEmpty(String html) {
    if (html.trim().isEmpty) return true;
    // Remove HTML tags and check if text remains
    final String plainText = html.replaceAll(RegExp(r'<[^>]*>'), '').trim();
    return plainText.isEmpty;
  }

  bool _isStepCompleted(int step, ProductData data) {
    return _validateStep(step, data).$1;
  }

  bool _canSubmit(ProductData data) {

    bool allGood = true;

    for (int i = 1; i <= _totalSteps; i++) {
      final ok = _isStepCompleted(i, data);
      if (!ok) {
        allGood = false;
      }
    }


    return allGood;
  }

  void _onAddProductPressed() {
    final bloc = context.read<AddProductBloc>();
    if (_canSubmit(bloc.state.productData)) {
      if (!DemoGuard.shouldProceed(context)) return;
      bloc.add(SubmitProduct());
    } else {
      showCustomSnackbar(
        context: context,
        message: context.tr('pleaseCompleteallRequiredFieldsInPreviousSteps'),
        isError: true,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    return BlocListener<AddProductBloc, AddProductState>(
      listener: (context, state) {
        if (state is AddProductSubmitting || state is AddProductLoadingEdit) {
          if (!_isLoadingShowing) {
            _isLoadingShowing = true;
            UIUtilsDialogs.showLoadingDialog(context);
          }
        } else if (state is AddProductSuccess) {
          if (_isLoadingShowing) {
            Navigator.pop(context, true); // Close loading dialog
            _isLoadingShowing = false;
          }
          showCustomSnackbar(
            context: context,
            message: context.tr('productAddedSuccessfully'),
          );
          Navigator.pop(context, true);
        } else if (state is AddProductFailure ||
            state is AddProductUpdating ||
            state is AddProductInitial) {
          if (_isLoadingShowing) {
            Navigator.pop(context); // Close loading dialog
            _isLoadingShowing = false;
          }
          if (state is AddProductFailure) {
            log('AddProductFailure: ${state.error}');
            showCustomSnackbar(
              context: context,
              message: state.error,
              isError: true,
            );
          }
        }
      },
      child: CustomScaffold(
        showAppbar: false,
        body: Column(
          children: [
            Expanded(
              child: CustomScrollView(
                slivers: [
                  SliverAppBar(
                    pinned: true,
                    floating: false,
                    snap: false,
                    elevation: 0,
                    expandedHeight: 170,
                    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
                    leading: IconButton(
                      icon: Icon(
                        Platform.isAndroid
                            ? Icons.arrow_back
                            : Icons.arrow_back_ios_new_rounded,
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                      onPressed: () => Navigator.pop(context),
                    ),
                    title: Text(
                      widget.isEdit
                          ? context.tr('editProduct')
                          : context.tr('addProduct'),
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                    ),
                    centerTitle: true,
                    flexibleSpace: FlexibleSpaceBar(
                      collapseMode: CollapseMode.parallax,
                      background: SafeArea(
                        child: Padding(
                          padding: const EdgeInsets.only(top: 50, bottom: 10), // push below appbar
                          child: AddProductHeader(
                            currentStep: _currentStep,
                            totalSteps: _totalSteps,
                            stepTitle: _getStepTitle(context, _currentStep),
                            onStepTap: () => _showStepBottomSheet(context),
                            screenType: screenType,
                          ),
                        ),
                      ),
                    ),

                    // 👇 THIS IS IMPORTANT (your header goes here)
                    /*bottom: PreferredSize(
                      preferredSize: const Size.fromHeight(85),
                      child: AddProductHeader(
                        currentStep: _currentStep,
                        totalSteps: _totalSteps,
                        stepTitle: _getStepTitle(context, _currentStep),
                        onStepTap: () => _showStepBottomSheet(context),
                        screenType: screenType,
                      ),
                    ),*/
                  ),

                  /// 👇 Your scrollable content
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.symmetric(
                        horizontal: UIUtils.gapMD(screenType),
                      ),
                      child: _buildStepContent(_currentStep, screenType),
                    ),
                  ),

                  /// 👇 Bottom spacing (so it doesn't hide behind bottom bar)
                  const SliverToBoxAdapter(
                    child: SizedBox(height: 100),
                  ),
                ],

              ),
            ),
            _buildBottomBar(context, screenType),
          ],
        ),
      ),
    );
  }

  /// Sticky bottom bar. Ghost-amber "Previous" (from step 2 onward) + solid
  /// amber "Next / Submit" with black label. Radius 14, height 52 — matches
  /// the auth screens' primary CTA style.
  Widget _buildBottomBar(BuildContext context, ScreenType screenType) {
    final theme = Theme.of(context);

    final BorderRadius radius = BorderRadius.circular(14);
    const double buttonHeight = 52;

    final bool showPrev = _currentStep > 1;
    final bool isLastStep = _currentStep == _totalSteps;

    return Container(
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        minimum: const EdgeInsets.only(bottom: 6),
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            UIUtils.gapMD(screenType),
            10,
            UIUtils.gapMD(screenType),
            6,
          ),
          child: Row(
            children: [
              if (showPrev)
                Expanded(
                  child: SizedBox(
                    height: buttonHeight,
                    child: OutlinedButton(
                      onPressed: () => _onStepChanged(_currentStep - 1),
                      style: OutlinedButton.styleFrom(
                        backgroundColor: theme.cardColor,
                        side: const BorderSide(
                          color: AppColors.primaryColor,
                          width: 1.5,
                        ),
                        foregroundColor: AppColors.primaryColor,
                        shape: RoundedRectangleBorder(borderRadius: radius),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(
                            Icons.arrow_back_rounded,
                            size: 18,
                            color: AppColors.primaryColor,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            context.tr('previous'),
                            style: const TextStyle(
                              color: AppColors.primaryColor,
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              if (showPrev) SizedBox(width: UIUtils.gapMD(screenType)),
              Expanded(
                child: SizedBox(
                  height: buttonHeight,
                  child: ElevatedButton(
                    onPressed: _onNextStep,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primaryColor,
                      foregroundColor: Colors.black,
                      shape: RoundedRectangleBorder(borderRadius: radius),
                      elevation: 0,
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          isLastStep ? context.tr('submit') : context.tr('next'),
                          style: const TextStyle(
                            color: Colors.black,
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Icon(
                          isLastStep
                              ? Icons.check_rounded
                              : Icons.arrow_forward_rounded,
                          size: 18,
                          color: Colors.black,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStepContent(int step, ScreenType screenType) {
    switch (step) {
      case 1:
        return const CategorySelectionStep();
      case 2:
        return const ProductInfoStep();
      case 3:
        return const PoliciesFeaturesStep();
      case 4:
        return const ProductVariationStep();
      case 5:
        return const ProductImagesStep();
      case 6:
        return const ProductDescriptionStep();
      case 7:
        return const PricingTaxesStep();
      default:
        return const SizedBox.shrink();
    }
  }

  void _showStepBottomSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => AddProductProcessBottomSheet(
        currentStep: _currentStep,
        totalSteps: _totalSteps,
        onStepSelected: (step) {
          _onStepChanged(step);
          Navigator.pop(context);
        },
      ),
    );
  }
}
