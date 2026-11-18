import 'package:cached_network_image/cached_network_image.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/product_model.dart';

/// Surface tokens for the product detail screen, resolved from [AppColors]
/// so both themes stay in step with the rest of the app.
class PdTone {
  final bool isDark;
  const PdTone(this.isDark);

  factory PdTone.of(BuildContext context) =>
      PdTone(Theme.of(context).brightness == Brightness.dark);

  Color get surface => isDark
      ? AppColors.mainDarkContainerBgColor
      : AppColors.mainLightBackgroundColor;

  Color get elevated => isDark
      ? AppColors.darkSubCategoryCardColor
      : AppColors.mainLightContainerBgColor;

  Color get page => isDark
      ? AppColors.mainDarkBackgroundColor
      : AppColors.mainLightBackgroundColor2;

  Color get border => isDark ? AppColors.darkOutline : AppColors.lightOutline;

  Color get divider =>
      isDark ? AppColors.darkOutlineVariant : AppColors.lightOutline;

  Color get ink => isDark ? AppColors.darkTertiary : AppColors.lightTertiary;

  Color get muted => isDark ? Colors.grey.shade500 : AppColors.sectionTitleColor;

  Color get faint => AppColors.chevronIconColor;

  Color get positive => isDark ? const Color(0xFF58C46E) : AppColors.stepCompletedColor;

  Color get negative => isDark ? const Color(0xFFFF6B60) : AppColors.errorColor;

  Color get positiveWash => positive.withValues(alpha: isDark ? 0.16 : 0.12);

  Color get negativeWash => negative.withValues(alpha: isDark ? 0.16 : 0.10);

  Color get accentWash =>
      AppColors.primaryColor.withValues(alpha: isDark ? 0.16 : 0.12);

  Color get accentInk =>
      isDark ? AppColors.primaryColor : const Color(0xFF6E4700);
}

/// Formats a money value with the seller's currency symbol, dropping the
/// decimals only when the amount is whole.
String pdMoney(num? value, String symbol) {
  if (value == null) return '—';
  final d = value.toDouble();
  final text = d == d.roundToDouble()
      ? d.toStringAsFixed(0)
      : d.toStringAsFixed(2);
  return '$symbol$text';
}

/// One row of the Price & stock section: a store, with everything the show
/// endpoint reports for it rolled up across the product's variants.
///
/// The endpoint returns commerce data per `variants[].stores[]` only — there
/// is no ship-from-location breakdown in the payload, so this groups by store.
class StoreStockSummary {
  final int storeId;
  final String storeName;
  final String? storeSlug;
  final double? lowPrice;
  final double? highPrice;
  final double? lowOriginal;
  final double? highOriginal;
  final int totalStock;
  final int rowCount;
  final String? sku;

  const StoreStockSummary({
    required this.storeId,
    required this.storeName,
    this.storeSlug,
    this.lowPrice,
    this.highPrice,
    this.lowOriginal,
    this.highOriginal,
    this.totalStock = 0,
    this.rowCount = 0,
    this.sku,
  });

  bool get hasDiscount => lowOriginal != null;
  bool get isPriceRange =>
      lowPrice != null && highPrice != null && lowPrice != highPrice;
  bool get isOutOfStock => totalStock <= 0;

  static List<StoreStockSummary> fromProduct(Product product) {
    final variants = product.variants ?? const <Variant>[];
    final order = <int>[];
    final names = <int, String>{};
    final slugs = <int, String?>{};
    final effective = <int, List<double>>{};
    final original = <int, List<double>>{};
    final stock = <int, int>{};
    final rows = <int, int>{};
    final skus = <int, Set<String>>{};

    for (final variant in variants) {
      for (final store in variant.stores ?? const <VariantStore>[]) {
        final id = store.storeId;
        if (id == null) continue;

        if (!order.contains(id)) {
          order.add(id);
          names[id] = store.storeName?.isNotEmpty == true
              ? store.storeName!
              : 'Store $id';
          slugs[id] = store.storeSlug;
        }

        final special = store.specialPrice;
        final base = store.price;
        final live = (special != null && special > 0 && special < (base ?? 0))
            ? special
            : base;

        if (live != null) (effective[id] ??= []).add(live);
        if (special != null && base != null && special > 0 && special < base) {
          (original[id] ??= []).add(base);
        }

        stock[id] = (stock[id] ?? 0) + (store.stock ?? 0);
        rows[id] = (rows[id] ?? 0) + 1;
        if (store.sku?.isNotEmpty == true) {
          (skus[id] ??= <String>{}).add(store.sku!);
        }
      }
    }

    return order.map((id) {
      final live = effective[id] ?? const <double>[];
      final was = original[id] ?? const <double>[];
      final storeSkus = skus[id] ?? const <String>{};
      return StoreStockSummary(
        storeId: id,
        storeName: names[id] ?? 'Store $id',
        storeSlug: slugs[id],
        lowPrice: live.isEmpty ? null : live.reduce((a, b) => a < b ? a : b),
        highPrice: live.isEmpty ? null : live.reduce((a, b) => a > b ? a : b),
        lowOriginal: was.isEmpty ? null : was.reduce((a, b) => a < b ? a : b),
        highOriginal: was.isEmpty ? null : was.reduce((a, b) => a > b ? a : b),
        totalStock: stock[id] ?? 0,
        rowCount: rows[id] ?? 0,
        // Only meaningful when every row in the store shares one SKU.
        sku: storeSkus.length == 1 ? storeSkus.first : null,
      );
    }).toList();
  }
}

// ═══════════════════════════════════════════════════════════════
//  PRIMITIVES
// ═══════════════════════════════════════════════════════════════

/// A collapsible card section. Used for every detail group so the page reads
/// as a stack of equal-weight blocks rather than one long list.
class PdSection extends StatefulWidget {
  final String title;
  final String? trailingLabel;
  final Widget child;
  final bool initiallyExpanded;
  final bool collapsible;
  final EdgeInsetsGeometry bodyPadding;

  const PdSection({
    super.key,
    required this.title,
    required this.child,
    this.trailingLabel,
    this.initiallyExpanded = true,
    this.collapsible = true,
    this.bodyPadding = const EdgeInsets.fromLTRB(14, 0, 14, 14),
  });

  @override
  State<PdSection> createState() => _PdSectionState();
}

class _PdSectionState extends State<PdSection> {
  late bool _open = widget.initiallyExpanded || !widget.collapsible;

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      decoration: BoxDecoration(
        color: tone.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: tone.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InkWell(
            onTap: widget.collapsible
                ? () => setState(() => _open = !_open)
                : null,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 13, 12, 13),
              child: Row(
                children: [
                  // Title + count share one flexible slot so the chevron is
                  // always pushed to the trailing edge.
                  Expanded(
                    child: Row(
                      children: [
                        Flexible(
                          child: Text(
                            widget.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              letterSpacing: -0.2,
                              color: tone.ink,
                            ),
                          ),
                        ),
                        if (widget.trailingLabel != null) ...[
                          const SizedBox(width: 8),
                          Text(
                            widget.trailingLabel!,
                            style: TextStyle(fontSize: 12, color: tone.muted),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (widget.collapsible) ...[
                    const SizedBox(width: 8),
                    AnimatedRotation(
                      turns: _open ? 0 : -0.25,
                      duration: const Duration(milliseconds: 180),
                      child: Icon(
                        Icons.keyboard_arrow_down_rounded,
                        size: 22,
                        color: tone.faint,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          AnimatedCrossFade(
            firstChild: const SizedBox(width: double.infinity, height: 0),
            secondChild: Padding(
              padding: widget.bodyPadding,
              child: SizedBox(width: double.infinity, child: widget.child),
            ),
            crossFadeState:
                _open ? CrossFadeState.showSecond : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 180),
            sizeCurve: Curves.easeOut,
          ),
        ],
      ),
    );
  }
}

/// Label/value line used inside the detail groups.
class PdInfoRow extends StatelessWidget {
  final String label;
  final String value;
  final Color? valueColor;
  final bool showDivider;

  const PdInfoRow({
    super.key,
    required this.label,
    required this.value,
    this.valueColor,
    this.showDivider = true,
  });

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 9),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 122,
                child: Text(
                  label,
                  style: TextStyle(fontSize: 12.5, color: tone.muted),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  value,
                  textAlign: TextAlign.end,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: valueColor ?? tone.ink,
                  ),
                ),
              ),
            ],
          ),
        ),
        if (showDivider) Divider(height: 1, color: tone.divider),
      ],
    );
  }
}

class PdChip extends StatelessWidget {
  final String label;
  final Color? background;
  final Color? foreground;

  const PdChip({super.key, required this.label, this.background, this.foreground});

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: background ?? tone.elevated,
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w600,
          color: foreground ?? tone.ink,
        ),
      ),
    );
  }
}

/// Live / Draft pill. Tapping it toggles the product's status.
class PdStatusPill extends StatelessWidget {
  final String status;
  final VoidCallback? onTap;

  /// While true, taps are blocked and the trailing chevron is replaced by a
  /// small inline spinner. Used to reflect an in-flight status change.
  final bool loading;

  const PdStatusPill({
    super.key,
    required this.status,
    this.onTap,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);
    final isActive = status == 'active';
    final color = isActive ? tone.positive : tone.muted;

    return InkWell(
      onTap: loading ? null : onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: isActive ? tone.positiveWash : tone.elevated,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 6),
            Text(
              isActive ? (context.tr('active', fallback: 'Active')) : (context.tr('draft', fallback: 'Draft')),
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: color,
              ),
            ),
            if (onTap != null || loading) ...[
              const SizedBox(width: 4),
              if (loading)
                SizedBox(
                  width: 12,
                  height: 12,
                  child: CircularProgressIndicator(
                    strokeWidth: 1.6,
                    valueColor: AlwaysStoppedAnimation<Color>(color),
                  ),
                )
              else
                Icon(Icons.unfold_more_rounded, size: 12, color: color),
            ],
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  PRICE & STOCK  (panel 5)
// ═══════════════════════════════════════════════════════════════

class PdStoreStockCard extends StatelessWidget {
  final StoreStockSummary summary;
  final String currencySymbol;

  const PdStoreStockCard({
    super.key,
    required this.summary,
    required this.currencySymbol,
  });

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: tone.elevated,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.storefront_rounded, size: 16, color: tone.muted),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  summary.storeName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -0.1,
                    color: tone.ink,
                  ),
                ),
              ),
              if (summary.sku != null)
                Text(
                  summary.sku!,
                  style: TextStyle(
                    fontSize: 11,
                    color: tone.muted,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _figure(
                context,
                label: context.tr('priceLabel', fallback: 'Price'),
                child: _priceText(context, tone),
              ),
              const SizedBox(width: 22),
              _figure(
                context,
                label: context.tr('stockLabel', fallback: 'Stock'),
                child: Text(
                  '${summary.totalStock}',
                  style: TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -0.3,
                    color: summary.isOutOfStock ? tone.negative : tone.ink,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: summary.isOutOfStock
                      ? tone.negativeWash
                      : tone.positiveWash,
                  borderRadius: BorderRadius.circular(7),
                ),
                child: Text(
                  summary.isOutOfStock
                      ? (context.tr('outOfStockLabel', fallback: 'Out of stock'))
                      : (context.tr('inStockLabel', fallback: 'In stock')),
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: summary.isOutOfStock ? tone.negative : tone.positive,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _figure(BuildContext context, {required String label, required Widget child}) {
    final tone = PdTone.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: TextStyle(
            fontSize: 9.5,
            letterSpacing: 0.6,
            fontWeight: FontWeight.w600,
            color: tone.faint,
          ),
        ),
        const SizedBox(height: 2),
        child,
      ],
    );
  }

  Widget _priceText(BuildContext context, PdTone tone) {
    final live = summary.isPriceRange
        ? '${pdMoney(summary.lowPrice, currencySymbol)} – ${pdMoney(summary.highPrice, currencySymbol)}'
        : pdMoney(summary.lowPrice, currencySymbol);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Text(
          live,
          style: TextStyle(
            fontSize: 14.5,
            fontWeight: FontWeight.w700,
            letterSpacing: -0.3,
            color: tone.ink,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
        if (summary.hasDiscount) ...[
          const SizedBox(width: 6),
          Text(
            pdMoney(summary.highOriginal, currencySymbol),
            style: TextStyle(
              fontSize: 11.5,
              color: tone.muted,
              decoration: TextDecoration.lineThrough,
              fontFeatures: const [FontFeature.tabularFigures()],
            ),
          ),
        ],
      ],
    );
  }
}

// ═══════════════════════════════════════════════════════════════
//  VARIANTS  (panel 6)
// ═══════════════════════════════════════════════════════════════

class PdVariantCard extends StatefulWidget {
  final Variant variant;
  final String currencySymbol;
  final bool initiallyExpanded;

  const PdVariantCard({
    super.key,
    required this.variant,
    required this.currencySymbol,
    this.initiallyExpanded = false,
  });

  @override
  State<PdVariantCard> createState() => _PdVariantCardState();
}

class _PdVariantCardState extends State<PdVariantCard> {
  late bool _open = widget.initiallyExpanded;

  int get _stock => (widget.variant.stores ?? const <VariantStore>[])
      .fold(0, (sum, s) => sum + (s.stock ?? 0));

  @override
  Widget build(BuildContext context) {
    final tone = PdTone.of(context);
    final variant = widget.variant;
    final stores = variant.stores ?? const <VariantStore>[];
    final attributes = variant.attributesMap ?? const <String, String>{};

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: tone.elevated,
        borderRadius: BorderRadius.circular(12),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          InkWell(
            onTap: stores.isEmpty ? null : () => setState(() => _open = !_open),
            child: Padding(
              padding: const EdgeInsets.all(11),
              child: Row(
                children: [
                  _thumb(tone),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Flexible(
                              child: Text(
                                variant.title ?? '—',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: -0.1,
                                  color: tone.ink,
                                ),
                              ),
                            ),
                            if (variant.isDefault == true) ...[
                              const SizedBox(width: 6),
                              PdChip(
                                label: context.tr('defaultLabel', fallback: 'Default'),
                                background: tone.accentWash,
                                foreground: tone.accentInk,
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 3),
                        Text(
                          _subtitle(attributes),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            fontSize: 11,
                            color: tone.muted,
                            fontFeatures: const [FontFeature.tabularFigures()],
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (variant.availability == false)
                    PdChip(
                      label: context.tr('unavailableLabel', fallback: 'Unavailable'),
                      background: tone.negativeWash,
                      foreground: tone.negative,
                    )
                  else if (stores.isNotEmpty)
                    AnimatedRotation(
                      turns: _open ? 0 : -0.25,
                      duration: const Duration(milliseconds: 180),
                      child: Icon(
                        Icons.keyboard_arrow_down_rounded,
                        size: 20,
                        color: tone.faint,
                      ),
                    ),
                ],
              ),
            ),
          ),
          AnimatedCrossFade(
            firstChild: const SizedBox(width: double.infinity, height: 0),
            secondChild: Padding(
              padding: const EdgeInsets.fromLTRB(11, 0, 11, 6),
              child: Column(
                children: [
                  Divider(height: 1, color: tone.divider),
                  for (final store in stores) _storeRow(tone, store),
                  if (variant.barcode?.isNotEmpty == true)
                    Padding(
                      padding: const EdgeInsets.only(top: 8, bottom: 4),
                      child: Row(
                        children: [
                          Icon(Icons.qr_code_2_rounded, size: 13, color: tone.faint),
                          const SizedBox(width: 5),
                          Text(
                            '${context.tr('barcodeLabel', fallback: 'Barcode')}  ${variant.barcode}',
                            style: TextStyle(
                              fontSize: 11,
                              color: tone.muted,
                              fontFeatures: const [FontFeature.tabularFigures()],
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
            crossFadeState:
                _open ? CrossFadeState.showSecond : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 180),
            sizeCurve: Curves.easeOut,
          ),
        ],
      ),
    );
  }

  String _subtitle(Map<String, String> attributes) {
    final parts = <String>[];
    if (attributes.isNotEmpty) parts.add(attributes.values.join(' · '));
    parts.add('$_stock ${context.tr('units', fallback: 'units').toLowerCase()}');
    return parts.join('  ·  ');
  }

  Widget _thumb(PdTone tone) {
    final image = widget.variant.image;
    if (image == null || image.isEmpty) {
      return Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: tone.surface,
          borderRadius: BorderRadius.circular(9),
        ),
        child: Icon(Icons.inventory_2_outlined, size: 17, color: tone.faint),
      );
    }
    return ClipRRect(
      borderRadius: BorderRadius.circular(9),
      child: CachedNetworkImage(
        imageUrl: image,
        width: 38,
        height: 38,
        fit: BoxFit.cover,
        placeholder: (_, __) => Container(width: 38, height: 38, color: tone.surface),
        errorWidget: (_, __, ___) => Container(
          width: 38,
          height: 38,
          color: tone.surface,
          child: Icon(Icons.broken_image_outlined, size: 16, color: tone.faint),
        ),
      ),
    );
  }

  Widget _storeRow(PdTone tone, VariantStore store) {
    final special = store.specialPrice;
    final base = store.price;
    final hasDiscount = special != null && base != null && special > 0 && special < base;
    final live = hasDiscount ? special : base;
    final outOfStock = (store.stock ?? 0) <= 0;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  store.storeName ?? '—',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 12, color: tone.ink),
                ),
                if (store.sku?.isNotEmpty == true)
                  Text(
                    store.sku!,
                    style: TextStyle(
                      fontSize: 10.5,
                      color: tone.faint,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                pdMoney(live, widget.currencySymbol),
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: tone.ink,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
              if (hasDiscount)
                Text(
                  pdMoney(base, widget.currencySymbol),
                  style: TextStyle(
                    fontSize: 10.5,
                    color: tone.muted,
                    decoration: TextDecoration.lineThrough,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  ),
                ),
            ],
          ),
          SizedBox(
            width: 58,
            child: Text(
              '${store.stock ?? 0}',
              textAlign: TextAlign.end,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: outOfStock ? FontWeight.w700 : FontWeight.w500,
                color: outOfStock ? tone.negative : tone.muted,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
