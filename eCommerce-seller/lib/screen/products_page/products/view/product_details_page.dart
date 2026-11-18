import 'dart:developer';
import 'dart:io';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/bloc/store_switcher/store_switcher_cubit.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/screen/home_page/bloc/home_page/home_page_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart'
    as add_product;
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/selected_categories/selected_categories_cubit.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/bloc/products_bloc/products_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/product_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/repo/products_repo.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/widgets/product_detail_widgets.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

enum _ProductMenuAction { faqs, visibility, delete }

class ProductDetailsPage extends StatefulWidget {
  final int productId;
  const ProductDetailsPage({super.key, required this.productId});

  @override
  State<ProductDetailsPage> createState() => _ProductDetailsPageState();
}

class _ProductDetailsPageState extends State<ProductDetailsPage> {
  /// Null until the show endpoint answers. Every section builder runs only
  /// once this is populated, so they read it through [_product].
  Product? _data;
  bool _isLoading = true;
  String? _error;

  /// True while an update-status request is in flight. Guards
  /// [_onToggleStatus] against re-entry and drives the pill's spinner.
  bool _statusChanging = false;


  int _currentImage = 0;
  final PageController _pageController = PageController();

  /// Hero carousel height. Kept in one place so the shimmer matches.
  static const double _heroHeight = 268;

  Product get _product => _data!;

  @override
  void initState() {
    super.initState();
    _pageController.addListener(_onPageScroll);
    _loadProduct();
  }

  @override
  void dispose() {
    _pageController.removeListener(_onPageScroll);
    _pageController.dispose();
    super.dispose();
  }

  void _onPageScroll() {
    final page = _pageController.page?.round() ?? 0;
    if (page != _currentImage) setState(() => _currentImage = page);
  }

  List<String> get _allImages {
    final images = <String>[];
    if (_product.mainImage.isNotEmpty) images.add(_product.mainImage);
    images.addAll(_product.additionalImages.where((i) => i.isNotEmpty));
    return images;
  }

  List<StoreStockSummary> get _storeStock =>
      StoreStockSummary.fromProduct(_product);

  bool get _hasVariants =>
      (_product.variants?.length ?? 0) > 1 || _product.type == 'variant';


  Future<void> _loadProduct() async {
    if (_data == null && !_isLoading) {
      setState(() {
        _isLoading = true;
        _error = null;
      });
    }

    try {
      final response = await ProductsRepo().getProductById(widget.productId);
      if (!mounted) return;

      final data = response is Map ? response['data'] : null;
      if (data is Map<String, dynamic>) {
        setState(() {
          _data = Product.fromJson(data);
          _error = null;
        });
      } else if (_data == null) {
        setState(() => _error = _emptyStateSubtitle);
      }
    } catch (_) {
      if (!mounted) return;
      if (_data == null) {
        setState(() => _error = _emptyStateSubtitle);
      } else {
        showCustomSnackbar(
          context: context,
          message: context.tr('failedToRefreshProduct', fallback: 'Failed to refresh product details'),
          isError: true,
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  String get _emptyStateSubtitle =>
      context.tr('productLoadFailed', fallback: "We couldn't load this product. Check your connection and try again.");

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);

    if (_data == null) {
      return CustomScaffold(
        backgroundColor: tone.page,
        showAppbar: true,
        title: context.tr('productDetails', fallback: 'Product Details'),
        body: _isLoading ? _buildShimmer(tone) : _buildErrorState(tone),
      );
    }

    return CustomScaffold(
      backgroundColor: tone.page,
      bottomNavigationBar: _buildBottomBar(tone),
      body: RefreshIndicator(
        onRefresh: _loadProduct,
        color: AppColors.primaryColor,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            _buildAppBar(tone),
            SliverToBoxAdapter(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildHero(tone),
                  _buildTitleBlock(tone),
                  _buildPriceAndStock(tone),
                  if (_hasVariants) _buildVariants(tone),
                  _buildDescription(tone),
                  _buildSpecifications(tone),
                  ..._buildCustomSections(tone),
                  _buildPolicies(tone),
                  _buildLogistics(tone),
                  _buildTagsAndOptions(tone),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Shown when the product could not be loaded at all.
  Widget _buildErrorState(PdTone tone) {
    return EmptyStateWidget(
      svgPath: ImagesPath.noProductFoundSvg,
      title: context.tr('productNotFound', fallback: 'Product not found'),
      subtitle: _error,
      actionText: context.tr('retry', fallback: 'Retry'),
      onAction: _loadProduct,
    );
  }

  // ─────────────────────── 1 · APP BAR ───────────────────────

  Widget _buildAppBar(PdTone tone) {

    return SliverAppBar(
      pinned: true,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      backgroundColor: tone.surface,
      surfaceTintColor: Colors.transparent,
      foregroundColor: tone.ink,
      titleSpacing: 0,
      leading: IconButton(
        icon: Icon(
          Platform.isAndroid
              ? Icons.arrow_back_rounded
              : Icons.arrow_back_ios_new_rounded,
          size: 20,
        ),
        onPressed: () => context.pop(),
      ),
      title: Row(
        children: [
          if (_product.mainImage.isNotEmpty)
            ClipRRect(
              borderRadius: BorderRadius.circular(7),
              child: CachedNetworkImage(
                imageUrl: _product.mainImage,
                width: 28,
                height: 28,
                fit: BoxFit.cover,
                placeholder: (_, __) =>
                    Container(width: 28, height: 28, color: tone.elevated),
                errorWidget: (_, __, ___) =>
                    Container(width: 28, height: 28, color: tone.elevated),
              ),
            ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _product.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 14.5,
                fontWeight: FontWeight.w700,
                letterSpacing: -0.2,
                color: tone.ink,
              ),
            ),
          ),
        ],
      ),
      actions: [
        PopupMenuButton<_ProductMenuAction>(
          icon: const Icon(Icons.more_vert_rounded, size: 21),
          color: tone.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(color: tone.border),
          ),
          onSelected: (action) {
            switch (action) {
              case _ProductMenuAction.faqs:
                _onViewFAQs();
              case _ProductMenuAction.visibility:
                _onVisibility();
              case _ProductMenuAction.delete:
                _onDelete();
            }
          },
          itemBuilder: (context) => [
            _menuItem(
              tone,
              _ProductMenuAction.faqs,
              Icons.help_outline_rounded,
              context.tr('faqsLabel', fallback: 'FAQs'),
            ),
            _menuItem(
              tone,
              _ProductMenuAction.visibility,
              Icons.visibility_outlined,
              context.tr('visibilityTitle', fallback: 'Visibility'),
            ),
            _menuItem(
              tone,
              _ProductMenuAction.delete,
              Icons.delete_outline_rounded,
              context.tr('delete', fallback: 'Delete'),
              danger: true,
            ),
          ],
        ),
      ],
    );
  }

  PopupMenuItem<_ProductMenuAction> _menuItem(
    PdTone tone,
    _ProductMenuAction value,
    IconData icon,
    String label, {
    bool danger = false,
  }) {
    final color = danger ? tone.negative : tone.ink;
    return PopupMenuItem<_ProductMenuAction>(
      value: value,
      height: 44,
      child: Row(
        children: [
          Icon(icon, size: 18, color: color),
          const SizedBox(width: 11),
          Text(
            label,
            style: TextStyle(
              fontSize: 13.5,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  // ─────────────────────── 2 · HERO ───────────────────────

  Widget _buildHero(PdTone tone) {
    final images = _allImages;
    if (images.isEmpty) {
      return Container(
        height: _heroHeight,
        color: tone.elevated,
        alignment: Alignment.center,
        child: Icon(Icons.image_outlined, size: 34, color: tone.faint),
      );
    }

    return Column(
      children: [
        SizedBox(
          height: _heroHeight,
          child: Stack(
            children: [
              PageView.builder(
                controller: _pageController,
                itemCount: images.length,
                itemBuilder: (context, index) => GestureDetector(
                  onTap: () => _openFullScreen(index),
                  child: _image(images[index], tone),
                ),
              ),
              if (images.length > 1)
                Positioned(
                  right: 12,
                  bottom: 12,
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.62),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '${_currentImage + 1} / ${images.length}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
        if (images.length > 1) _buildThumbStrip(tone, images),
      ],
    );
  }

  Widget _buildThumbStrip(PdTone tone, List<String> images) {
    return Container(
      height: 68,
      color: tone.surface,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: images.length,
        separatorBuilder: (_, __) => const SizedBox(width: 7),
        itemBuilder: (context, index) {
          final selected = index == _currentImage;
          return GestureDetector(
            onTap: () => _pageController.animateToPage(
              index,
              duration: const Duration(milliseconds: 260),
              curve: Curves.easeOut,
            ),
            child: Container(
              width: 48,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(9),
                border: Border.all(
                  color: selected ? AppColors.primaryColor : tone.border,
                  width: selected ? 2 : 1,
                ),
              ),
              clipBehavior: Clip.antiAlias,
              child: CachedNetworkImage(
                imageUrl: images[index],
                fit: BoxFit.cover,
                placeholder: (_, __) => Container(color: tone.elevated),
                errorWidget: (_, __, ___) => Container(color: tone.elevated),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _image(String url, PdTone tone) {
    return CachedNetworkImage(
      imageUrl: url,
      fit: _product.imageFit == 'contain' ? BoxFit.contain : BoxFit.cover,
      width: double.infinity,
      placeholder: (_, __) => Container(color: tone.elevated),
      errorWidget: (_, __, ___) => Container(
        color: tone.elevated,
        child: Icon(Icons.broken_image_outlined, size: 32, color: tone.faint),
      ),
    );
  }

  void _openFullScreen(int initialIndex) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => FullScreenImageGallery(
          images: _allImages,
          initialIndex: initialIndex,
        ),
      ),
    );
  }

  // ─────────────────────── TITLE BLOCK ───────────────────────

  Widget _buildTitleBlock(PdTone tone) {
    final crumbs = [
      if (_product.categoryName?.isNotEmpty == true) _product.categoryName!,
      if (_product.brandName?.isNotEmpty == true) _product.brandName!,
    ];

    final meta = <String>[
      (context.translateEnum(_product.type ?? 'simple'))
          .toUpperCase(),
      if (_hasVariants)
        '${_product.variants?.length} ${context.tr('variants', fallback: 'Variants').toLowerCase()}',
      '${_storeStock.length} ${context.tr('stores', fallback: 'Stores').toLowerCase()}',
    ];

    return Container(
      width: double.infinity,
      color: tone.surface,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (crumbs.isNotEmpty)
            Text(
              crumbs.join(' › '),
              style: TextStyle(fontSize: 11.5, color: tone.muted),
            ),
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  _product.title,
                  style: TextStyle(
                    fontSize: 19,
                    fontWeight: FontWeight.w700,
                    height: 1.25,
                    letterSpacing: -0.4,
                    color: tone.ink,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Padding(
                padding: const EdgeInsets.only(top: 3),
                child: PdStatusPill(
                  status: _product.status,
                  onTap: _onToggleStatus,
                  loading: _statusChanging,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            meta.join('  ·  '),
            style: TextStyle(fontSize: 11.5, color: tone.muted),
          ),
        ],
      ),
    );
  }

  // ─────────────────────── 5 · PRICE & STOCK ───────────────────────

  Widget _buildPriceAndStock(PdTone tone) {
    final stores = _storeStock;

    return PdSection(
      title: context.tr('priceAndStockLabel', fallback: 'Price & stock'),
      trailingLabel: stores.isEmpty
          ? null
          : ('${stores.length} ${context.tr('stores', fallback: 'Stores').toLowerCase()}'),
      collapsible: false,
      child: stores.isEmpty
          ? Text(
              context.tr('notStockedAnywhere', fallback: 'Not stocked at any store'),
              style: TextStyle(fontSize: 12.5, color: tone.muted),
            )
          : Column(
              children: [
                for (final store in stores)
                  PdStoreStockCard(
                    summary: store,
                    currencySymbol: HiveStorage.currencySymbol,
                  ),
              ],
            ),
    );
  }

  // ─────────────────────── 6 · VARIANTS ───────────────────────

  Widget _buildVariants(PdTone tone) {
    final variants = _product.variants ?? const <Variant>[];

    return PdSection(
      title: context.tr('variantsAndAvailability', fallback: 'Variants & availability'),
      trailingLabel:
          '${variants.length} ${context.tr('variants', fallback: 'variants').toLowerCase()}',
      child: variants.isEmpty
          ? Text(
              context.tr('noVariantsYet', fallback: 'No variants yet'),
              style: TextStyle(fontSize: 12.5, color: tone.muted),
            )
          : Column(
              children: [
                for (int i = 0; i < variants.length; i++)
                  PdVariantCard(
                    variant: variants[i],
                    currencySymbol: HiveStorage.currencySymbol,
                    initiallyExpanded: i == 0,
                  ),
              ],
            ),
    );
  }

  // ─────────────────────── 7 · DETAIL GROUPS ───────────────────────

  Widget _buildDescription(PdTone tone) {
    final short = _product.shortDescription ?? '';
    final full = _product.description ?? '';
    if (short.isEmpty && full.isEmpty) return const SizedBox.shrink();

    return PdSection(
      title: context.tr('productDescription', fallback: 'Description'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (short.isNotEmpty)
            Text(
              short,
              style: TextStyle(
                fontSize: 13,
                height: 1.6,
                fontWeight: FontWeight.w500,
                color: tone.ink,
              ),
            ),
          if (short.isNotEmpty && full.isNotEmpty) const SizedBox(height: 10),
          if (full.isNotEmpty)
            HtmlWidget(
              full,
              textStyle: TextStyle(
                fontSize: 13,
                height: 1.6,
                color: tone.muted,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildSpecifications(PdTone tone) {
    final fields = _product.customFields;
    if (fields == null || fields.isEmpty) return const SizedBox.shrink();

    final entries = fields.entries.toList();

    return PdSection(
      title: context.tr('specificationsLabel', fallback: 'Specifications'),
      trailingLabel: '${entries.length}',
      initiallyExpanded: false,
      child: Column(
        children: [
          for (int i = 0; i < entries.length; i++)
            PdInfoRow(
              label: entries[i].key,
              value: entries[i].value.toString(),
              showDivider: i < entries.length - 1,
            ),
        ],
      ),
    );
  }

  List<Widget> _buildCustomSections(PdTone tone) {
    final sections = _product.customProductSections ?? const [];
    if (sections.isEmpty) return const [];

    return sections.map((section) {
      return PdSection(
        title: section.title,
        trailingLabel: '${section.fields.length}',
        initiallyExpanded: false,
        child: Column(
          children: [
            if (section.description?.isNotEmpty == true) ...[
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  section.description!,
                  style: TextStyle(fontSize: 12, color: tone.muted),
                ),
              ),
              const SizedBox(height: 10),
            ],
            for (final field in section.fields)
              _buildCustomField(tone, field, section.fields.last == field),
          ],
        ),
      );
    }).toList();
  }

  Widget _buildCustomField(PdTone tone, CustomProductField field, bool isLast) {
    return Container(
      margin: EdgeInsets.only(bottom: isLast ? 0 : 8),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: tone.elevated,
        borderRadius: BorderRadius.circular(11),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (field.image?.isNotEmpty == true) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: CachedNetworkImage(
                imageUrl: field.image!,
                width: 46,
                height: 46,
                fit: BoxFit.cover,
                placeholder: (_, __) =>
                    Container(width: 46, height: 46, color: tone.surface),
                errorWidget: (_, __, ___) =>
                    Container(width: 46, height: 46, color: tone.surface),
              ),
            ),
            const SizedBox(width: 11),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  field.title,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: tone.ink,
                  ),
                ),
                if (field.description?.isNotEmpty == true) ...[
                  const SizedBox(height: 3),
                  Text(
                    field.description!,
                    style: TextStyle(
                      fontSize: 12,
                      height: 1.5,
                      color: tone.muted,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPolicies(PdTone tone) {
    final rows = <PdInfoRow>[
      PdInfoRow(
        label: context.tr('returnableLabel', fallback: 'Returnable'),
        value: !_product.isReturnable
            ? (context.tr('no', fallback: 'No'))
            // The window is only worth printing when the panel actually set one.
            : ((_product.returnableDays ?? 0) > 0
                ? (context.tr('yesWithDays', positionalArgs: [_product.returnableDays!], fallback: 'Yes — ${_product.returnableDays} days'))
                : (context.tr('yes', fallback: 'Yes'))),
      ),
      PdInfoRow(
        label: context.tr('cancelableLabel', fallback: 'Cancelable'),
        value: _product.isCancelable == true
            ? (_product.cancelableTill?.isNotEmpty == true
                ? (context.translateEnum(_product.cancelableTill!))
                : (context.tr('yes', fallback: 'Yes')))
            : (context.tr('no', fallback: 'No')),
      ),
      PdInfoRow(
        label: context.tr('cashOnDeliveryLabel', fallback: 'Cash on delivery'),
        value: _product.isCodAllowed == true
            ? (context.tr('allowedLabel', fallback: 'Allowed'))
            : (context.tr('notAllowedLabel', fallback: 'Not allowed')),
      ),
      PdInfoRow(
        label: context.tr('otpLabel', fallback: 'OTP on delivery'),
        value: _product.requiresOtp == true
            ? (context.tr('requiredLabel', fallback: 'Required'))
            : (context.tr('notRequiredLabel', fallback: 'Not required')),
      ),
      if (_product.isAttachmentRequired == true)
        PdInfoRow(
          label: context.tr('attachmentLabel', fallback: 'Attachment'),
          value: _product.attachmentMode?.isNotEmpty == true
              ? (context.translateEnum(_product.attachmentMode!))
              : (context.tr('requiredLabel', fallback: 'Required')),
        ),
    ];

    return PdSection(
      title: context.tr('policiesLabel', fallback: 'Policies'),
      child: _rows(rows),
    );
  }

  Widget _buildLogistics(PdTone tone) {

    // Everything except tax. Tax is now rendered by a dedicated block so it
    // can surface the tax classes + individual rate breakdown.
    final rows = <PdInfoRow>[
      PdInfoRow(
        label: context.tr('minOrderShort', fallback: 'Min order'),
        value: context.tr('qtyAmount', positionalArgs: [_product.minimumOrderQuantity ?? 1], fallback: '${_product.minimumOrderQuantity ?? 1}'),
      ),
      PdInfoRow(
        label: context.tr('quantityStepSize', fallback: 'Step size'),
        value: '${_product.quantityStepSize ?? 1}',
      ),
      if ((_product.totalAllowedQuantity ?? 0) > 0)
        PdInfoRow(
          label: context.tr('totalAllowedQuantity', fallback: 'Total allowed'),
          value: '${_product.totalAllowedQuantity}',
        ),
      if ((_product.prepTime ?? 0) > 0)
        PdInfoRow(
          label: context.tr('prepTimeShort', fallback: 'Prep time'),
          value: context.tr('minShort', positionalArgs: [_product.prepTime ?? 0], fallback: '${_product.prepTime} min'),
        ),
      // Rows below only render when the payload actually carries them.
      if (_product.hsnCode?.isNotEmpty == true)
        PdInfoRow(
          label: context.tr('hsnCode', fallback: 'HSN code'),
          value: _product.hsnCode!,
        ),
      if (_product.madeIn?.isNotEmpty == true)
        PdInfoRow(
          label: context.tr('madeInLabel', fallback: 'Made in'),
          value: _product.madeIn!,
        ),
      if (_product.warrantyPeriod?.isNotEmpty == true)
        PdInfoRow(
          label: context.tr('warrantyLabel', fallback: 'Warranty'),
          value: _product.warrantyPeriod!,
        ),
      if (_product.guaranteePeriod?.isNotEmpty == true)
        PdInfoRow(
          label: context.tr('guaranteeLabel', fallback: 'Guarantee'),
          value: _product.guaranteePeriod!,
        ),
      PdInfoRow(
        label: context.tr('featuredLabel', fallback: 'Featured'),
        value: _product.featured == '1' ? (context.tr('yes', fallback: 'Yes')) : (context.tr('no', fallback: 'No')),
      ),
    ];

    return PdSection(
      title: context.tr('logisticsAndTaxLabel', fallback: 'Logistics & tax'),
      initiallyExpanded: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _TaxBreakdownBlock(
            classes: _product.taxClasses ?? const <TaxClasses>[],
            inclusive: _product.isInclusiveTax == true,
            taxLabel: context.tr('taxLabel', fallback: 'Tax'),
            inclusiveLabel: context.tr('inclusive', fallback: 'Inclusive'),
            exclusiveLabel: context.tr('exclusive', fallback: 'Exclusive'),
          ),
          Divider(height: 1, color: tone.divider),
          _rows(rows),
        ],
      ),
    );
  }

  Widget _buildTagsAndOptions(PdTone tone) {
    final tags = _product.tags;
    final attributes = _product.productAttributes ?? const <ProductAttribute>[];
    if (tags.isEmpty && attributes.isEmpty) return const SizedBox.shrink();

    return PdSection(
      title: attributes.isEmpty
          ? (context.tr('tagsLabel', fallback: 'Tags'))
          : (context.tr('optionsLabel', fallback: 'Options')),
      initiallyExpanded: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final attribute in attributes) ...[
            Text(
              attribute.name,
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
                color: tone.muted,
              ),
            ),
            const SizedBox(height: 6),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final value in attribute.values) PdChip(label: value),
              ],
            ),
            const SizedBox(height: 12),
          ],
          if (tags.isNotEmpty) ...[
            if (attributes.isNotEmpty) ...[
              Text(
                context.tr('tagsLabel', fallback: 'Tags'),
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: tone.muted,
                ),
              ),
              const SizedBox(height: 6),
            ],
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final tag in tags)
                  PdChip(
                    label: '#$tag',
                    background: tone.accentWash,
                    foreground: tone.accentInk,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _rows(List<PdInfoRow> rows) {
    return Column(
      children: [
        for (int i = 0; i < rows.length; i++)
          PdInfoRow(
            label: rows[i].label,
            value: rows[i].value,
            valueColor: rows[i].valueColor,
            showDivider: i < rows.length - 1,
          ),
      ],
    );
  }

  // ─────────────────────── 8 · BOTTOM BAR ───────────────────────

  Widget _buildBottomBar(PdTone tone) {

    return Container(
      decoration: BoxDecoration(
        color: tone.surface,
        border: Border(top: BorderSide(color: tone.border)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          child: Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 46,
                  child: ElevatedButton.icon(
                    onPressed: _onEdit,
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: Text(
                      context.tr('editLbl', fallback: 'Edit'),
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primaryColor,
                      foregroundColor: AppColors.secondaryColor,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              SizedBox(
                height: 46,
                child: OutlinedButton.icon(
                  onPressed: _onVisibility,
                  icon: const Icon(Icons.visibility_outlined, size: 18),
                  label: Text(
                    context.tr('visibilityTitle', fallback: 'Visibility'),
                    style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: tone.ink,
                    side: BorderSide(color: tone.border),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
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

  // ─────────────────────── SHIMMER ───────────────────────

  Widget _buildShimmer(PdTone tone) {
    return SingleChildScrollView(
      child: Column(
        children: [
          const CustomShimmer(width: double.infinity, height: _heroHeight),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              children: List.generate(
                5,
                (i) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: CustomShimmer(
                    width: double.infinity,
                    height: i == 0 ? 26 : 88,
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ─────────────────────── ACTIONS ───────────────────────

  void _onEdit() {
    if (!PermissionChecker.hasPermission(AppPermissions.productEdit)) {
      showCustomSnackbar(
        context: context,
        message: context.tr('noPermissionEditProduct', fallback: 'No permission to edit'),
        isWarning: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) return;

    if (_product.categoryId != null) {
      context.read<SelectedCategoriesCubit>().select(_product.categoryId!);
    }
    context.read<add_product.AddProductBloc>().add(
          add_product.LoadProductForEdit(_product.id),
        );

    context.push<bool>(AppRoutes.addProduct, extra: {'is_edit': true}).then(
      (shouldRefresh) {
        if (shouldRefresh != true || !mounted) return;
        context.read<ProductsBloc>().add(RefreshProducts());
        _loadProduct();
        _refreshHomePage();
      },
    );
  }

  void _onVisibility() =>
      context.push(AppRoutes.productVisibility, extra: _product.id);

  void _onViewFAQs() {
    context.pushNamed(
      '/product-faqs',
      queryParameters: {'productId': _product.id.toString()},
    );
  }

  Future<void> _onToggleStatus() async {
    if (_statusChanging) return;
    if (!DemoGuard.shouldProceed(context)) return;
    final newStatus = _product.status == 'active' ? 'draft' : 'active';
    setState(() {
      _statusChanging = true;
    });

    // Call the repo directly so the outcome is applied only after the API
    // answers, and so the shared ProductsBloc listeners on the list page don't
    // also fire on our error/success emits.
    try {
      await ProductsRepo().updateProductStatus(_product.id, newStatus);
      if (!mounted) return;
      setState(() {
        _product.status = newStatus;
        _statusChanging = false;
      });
      showCustomSnackbar(
        context: context,
        message:
            context.tr('statusUpdated', fallback: 'Status updated'),
        isError: false,
      );
      // Keep the list page in sync with the new status.
      if (mounted) {
        context.read<ProductsBloc>().add(RefreshProducts());
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _statusChanging = false;
      });
      // Repo wraps failures as Exception(e.toString()) — strip the prefix so
      // the snackbar reads as the underlying API message.
      final raw = e.toString();
      final cleaned = raw.startsWith('Exception: ')
          ? raw.substring('Exception: '.length)
          : raw;
      showCustomSnackbar(
        context: context,
        message: cleaned,
        isError: true,
      );
    }
  }

  void _onDelete() {
    if (!PermissionChecker.hasPermission(AppPermissions.productDelete)) {
      showCustomSnackbar(
        context: context,
        message: context.tr('noPermissionDeleteProduct', fallback: 'No permission to delete'),
        isWarning: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) return;
    showAppAlertDialog(
      context: context,
      title: context.tr('deleteProduct', fallback: 'Delete Product'),
      message: context.tr('deleteProductConfirmation', fallback: 'Are you sure you want to delete this product?'),
      confirmColor: AppColors.errorColor,
      onConfirm: () {
        context.read<ProductsBloc>().add(DeleteProduct(_product.id));
        context.pop(true);
        _refreshHomePage();
      },
    );
  }

  void _refreshHomePage() {
    final selectedStore = context.read<StoreSwitcherCubit>().state.selectedStore;
    if (selectedStore != null) {
      context.read<HomePageBloc>().add(FetchHomePageData(storeId: selectedStore.id));
    }
  }
}

// ═══════════════════════════════════════════════════════════════
//  FULL SCREEN GALLERY
// ═══════════════════════════════════════════════════════════════

class FullScreenImageGallery extends StatefulWidget {
  final List<String> images;
  final int initialIndex;

  const FullScreenImageGallery({
    super.key,
    required this.images,
    this.initialIndex = 0,
  });

  @override
  State<FullScreenImageGallery> createState() => _FullScreenImageGalleryState();
}

class _FullScreenImageGalleryState extends State<FullScreenImageGallery> {
  late PageController _pageController;
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
    _pageController = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final backgroundColor = isDark
        ? AppColors.mainDarkBackgroundColor
        : AppColors.mainLightBackgroundColor;
    final iconColor = isDark ? Colors.white : Colors.black87;
    final indicatorBgColor = isDark
        ? AppColors.primaryColor
        : AppColors.primaryColor.withValues(alpha: 0.05);

    return Scaffold(
      backgroundColor: backgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.close, color: iconColor, size: 28),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      extendBodyBehindAppBar: true,
      body: Stack(
        fit: StackFit.expand,
        children: [
          PageView.builder(
            controller: _pageController,
            itemCount: widget.images.length,
            onPageChanged: (i) => setState(() => _currentIndex = i),
            itemBuilder: (context, index) {
              return InteractiveViewer(
                minScale: 1.0,
                maxScale: 4.0,
                child: Center(
                  child: CachedNetworkImage(
                    imageUrl: widget.images[index],
                    fit: BoxFit.contain,
                    placeholder: (_, __) => CustomLoadingIndicator(
                      color: AppColors.primaryColor,
                      strokeWidth: 2,
                    ),
                    errorWidget: (_, __, ___) => Icon(
                      Icons.broken_image,
                      color: isDark ? Colors.white54 : Colors.grey,
                      size: 50,
                    ),
                  ),
                ),
              );
            },
          ),
          Positioned(
            bottom: MediaQuery.of(context).padding.bottom + 20,
            left: 0,
            right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(
                    color: indicatorBgColor,
                    borderRadius: BorderRadius.circular(20),
                    border: isDark ? null : Border.all(color: Colors.black12),
                  ),
                  child: Text(
                    '${_currentIndex + 1} / ${widget.images.length}',
                    style: TextStyle(
                      color: AppColors.primaryColor,
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}


class _TaxBreakdownBlock extends StatelessWidget {
  final List<TaxClasses> classes;
  final bool inclusive;
  final String taxLabel;
  final String inclusiveLabel;
  final String exclusiveLabel;

  const _TaxBreakdownBlock({
    required this.classes,
    required this.inclusive,
    required this.taxLabel,
    required this.inclusiveLabel,
    required this.exclusiveLabel,
  });

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);
    final children = <Widget>[_headerRow(tone)];

    for (int i = 0; i < classes.length; i++) {
      children.add(Divider(height: 1, color: tone.divider));
      children.add(_classBlock(tone, classes[i]));
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: children,
    );
  }

  Widget _headerRow(PdTone tone) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          SizedBox(
            width: 122,
            child: Text(
              taxLabel,
              style: TextStyle(fontSize: 12.5, color: tone.muted),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Align(
              alignment: Alignment.centerRight,
              child: _pill(tone, inclusive ? inclusiveLabel : exclusiveLabel),
            ),
          ),
        ],
      ),
    );
  }

  Widget _pill(PdTone tone, String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: tone.ink.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text.toUpperCase(),
        style: TextStyle(
          fontSize: 10.5,
          fontWeight: FontWeight.w800,
          color: tone.ink,
          letterSpacing: 0.6,
        ),
      ),
    );
  }

  Widget _classBlock(PdTone tone, TaxClasses tc) {
    final rates = tc.taxRates ?? const <TaxRates>[];
    final total = rates.fold<double>(
      0,
      (sum, r) => sum + (double.tryParse(r.rate ?? '') ?? 0),
    );
    final title = (tc.title ?? '').trim();

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 9),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 122,
                child: Text(
                  title.isEmpty ? '—' : title.toUpperCase(),
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                    color: tone.ink,
                    letterSpacing: 0.4,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  '${total.toStringAsFixed(2)}%',
                  textAlign: TextAlign.end,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: tone.ink,
                  ),
                ),
              ),
            ],
          ),
          if (rates.isNotEmpty) const SizedBox(height: 6),
          for (int i = 0; i < rates.length; i++)
            Padding(
              padding: EdgeInsets.only(left: 12, top: i == 0 ? 0 : 4),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 4,
                    height: 4,
                    margin: const EdgeInsets.only(top: 6, right: 8),
                    decoration: BoxDecoration(
                      color: tone.muted,
                      shape: BoxShape.circle,
                    ),
                  ),
                  Expanded(
                    child: Text(
                      (rates[i].title ?? '').trim().isEmpty
                          ? '—'
                          : rates[i].title!,
                      style: TextStyle(fontSize: 12, color: tone.muted),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    _fmtRate(rates[i].rate),
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: tone.muted,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  String _fmtRate(String? raw) {
    final v = double.tryParse(raw ?? '');
    if (v == null) return '—';
    return '${v.toStringAsFixed(2)}%';
  }
}
