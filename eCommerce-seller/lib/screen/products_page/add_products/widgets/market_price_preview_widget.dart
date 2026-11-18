import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/tax_group/model/tax_group_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/model/currency_conversion_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/products/model/tax_breakdown_model.dart';
import 'package:hyper_commerce_seller/utils/money_formatter.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

/// Market price preview widget.
///
/// Displays real-time converted prices, tax amounts, and customer prices
/// across available market currencies, matching the admin panel interface.
class MarketPricePreviewWidget extends StatefulWidget {
  final CurrencyConversionData? conversionData;
  final bool isLoading;
  final List<TaxGroup> availableTaxGroups;
  final List<int> selectedTaxGroupIds;
  final bool isInclusiveTax;
  final double basePrice;
  final double? baseSpecialPrice;
  final ProductData productData;
  final bool hasUserEdited;

  const MarketPricePreviewWidget({
    super.key,
    required this.conversionData,
    this.isLoading = false,
    required this.availableTaxGroups,
    required this.selectedTaxGroupIds,
    required this.isInclusiveTax,
    required this.basePrice,
    this.baseSpecialPrice,
    required this.productData,
    required this.hasUserEdited,
  });

  @override
  State<MarketPricePreviewWidget> createState() =>
      _MarketPricePreviewWidgetState();
}

class _MarketPricePreviewWidgetState extends State<MarketPricePreviewWidget> {
  int _selectedVariantIndex = 0;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isVariantProduct = widget.productData.type == 'variant' &&
        widget.productData.variants.isNotEmpty;

    // Determine current active variant if variant product
    final currentVariant = isVariantProduct &&
            _selectedVariantIndex < widget.productData.variants.length
        ? widget.productData.variants[_selectedVariantIndex]
        : null;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Header Row: Title & Inclusive/Exclusive Badge ──────────
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('marketPricePreview', fallback: 'Market price preview'),
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: theme.colorScheme.tertiary,
                      ),
                    ),
                    if (isVariantProduct) ...[
                      const SizedBox(height: 6),
                      _buildVariantSelector(theme),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _buildTaxModeBadge(theme),
            ],
          ),
          const SizedBox(height: 16),

          // ── Body: Loading or Market Cards ─────────────────────────
          if (widget.isLoading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(child: CustomLoadingIndicator()),
            )
          else if (widget.conversionData == null ||
              widget.conversionData!.markets.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Center(
                child: Text(
                  context.tr('noMarketData', fallback: 'No market conversion data available.'),
                  style: TextStyle(
                    fontSize: 13,
                    color: theme.colorScheme.tertiary.withValues(alpha: 0.6),
                  ),
                ),
              ),
            )
          else
            _buildMarketsList(theme, currentVariant),
        ],
      ),
    );
  }

  // ── Variant Selector Dropdown ──────────────────────────────────────────────

  Widget _buildVariantSelector(ThemeData theme) {
    final variants = widget.productData.variants;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: _selectedVariantIndex.clamp(0, variants.length - 1),
          isDense: true,
          icon: Icon(
            Icons.keyboard_arrow_down_rounded,
            size: 18,
            color: theme.colorScheme.tertiary.withValues(alpha: 0.7),
          ),
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
            color: theme.colorScheme.tertiary,
          ),
          dropdownColor: theme.cardColor,
          items: variants.asMap().entries.map((entry) {
            final idx = entry.key;
            final v = entry.value;
            final title = v.title.isNotEmpty ? v.title : 'Variant #${idx + 1}';
            return DropdownMenuItem<int>(
              value: idx,
              child: Text(
                'Variant: $title',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            );
          }).toList(),
          onChanged: (val) {
            if (val != null) {
              setState(() => _selectedVariantIndex = val);
            }
          },
        ),
      ),
    );
  }

  // ── Tax Mode Badge (Tax added at checkout vs Tax included in price) ─────────

  Widget _buildTaxModeBadge(ThemeData theme) {
    final isInclusive = widget.isInclusiveTax;
    final bgColor = isInclusive
        ? const Color(0xFFE8F5E9) // soft green
        : const Color(0xFFE3F2FD); // soft blue
    final textColor = isInclusive
        ? const Color(0xFF2E7D32)
        : const Color(0xFF1565C0);
    final text = isInclusive
        ? context.tr('taxIncludedInPrice', fallback: 'Tax included in price')
        : context.tr('taxAddedAtCheckout', fallback: 'Tax added at checkout');

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: textColor,
        ),
      ),
    );
  }

  // ── Market Cards Grid/List ─────────────────────────────────────────────────

  Widget _buildMarketsList(ThemeData theme, VariantData? currentVariant) {
    final markets = widget.conversionData!.markets;
    final baseCurrency = widget.conversionData!.baseCurrency ??
        const BaseCurrency(code: 'USD', symbol: '\$');

    // Identify initial server breakdowns if available and user hasn't edited
    final List<TaxBreakdown>? initialBreakdowns = !widget.hasUserEdited
        ? (currentVariant?.taxBreakdown ?? widget.productData.taxBreakdown)
        : null;

    return LayoutBuilder(
      builder: (context, constraints) {
        final isWide = constraints.maxWidth >= 600;
        final count = markets.length;

        if (isWide) {
          return Wrap(
            spacing: 12,
            runSpacing: 12,
            children: markets.map((market) {
              return SizedBox(
                width: (constraints.maxWidth - 12) / 2,
                child: _buildMarketCard(
                  theme,
                  market,
                  baseCurrency,
                  initialBreakdowns,
                  currentVariant,
                ),
              );
            }).toList(),
          );
        }

        return Column(
          children: List.generate(count, (index) {
            final market = markets[index];
            return Padding(
              padding: EdgeInsets.only(bottom: index == count - 1 ? 0 : 12),
              child: _buildMarketCard(
                theme,
                market,
                baseCurrency,
                initialBreakdowns,
                currentVariant,
              ),
            );
          }),
        );
      },
    );
  }

  // ── Individual Market Card ─────────────────────────────────────────────────

  Widget _buildMarketCard(
    ThemeData theme,
    MarketConversion market,
    BaseCurrency baseCurrency,
    List<TaxBreakdown>? initialBreakdowns,
    VariantData? currentVariant,
  ) {
    // 1. Tax Classes for this market
    final marketTaxGroups = widget.availableTaxGroups.where((tg) {
      if (!widget.selectedTaxGroupIds.contains(tg.id)) return false;
      if (tg.marketId != null && tg.marketId == market.id) return true;
      if (tg.market != null) {
        if (tg.market!.id == market.id) return true;
        if (tg.market!.code.toUpperCase() == market.code.toUpperCase()) {
          return true;
        }
      }
      return false;
    }).toList();

    final double totalTaxRate = marketTaxGroups.fold<double>(
      0.0,
      (sum, tg) => sum + tg.totalRate,
    );

    final taxClassesTitles = marketTaxGroups.isNotEmpty
        ? marketTaxGroups.map((tg) => tg.title).join(", ")
        : context.tr('noTaxClasses', fallback: 'None');

    // 2. Base Prices
    double effectiveBasePrice = widget.basePrice;
    double? effectiveBaseSpecialPrice = widget.baseSpecialPrice;

    // Check if initial server tax breakdown is present
    TaxBreakdown? initialBreakdown;
    if (initialBreakdowns != null && initialBreakdowns.isNotEmpty) {
      initialBreakdown = initialBreakdowns.firstWhere(
        (b) =>
            (b.market?.id != null && b.market!.id == market.id) ||
            (b.market?.code.toUpperCase() == market.code.toUpperCase()),
        orElse: () => const TaxBreakdown(),
      );
      if (initialBreakdown.market == null) {
        initialBreakdown = null;
      }
    }

    String regularPriceFormatted = '';
    String? specialPriceFormatted;
    String taxAmountFormatted = '';
    String customerPriceFormatted = '';

    if (initialBreakdown != null) {
      // ── Use Initial Server Data from tax_breakdown ──
      final mult = market.conversionRate > 0 ? market.conversionRate : 1.0;

      final regEx = (initialBreakdown.regular?.priceExcludingTax ?? 0.0) * mult;
      final regTax = (initialBreakdown.regular?.taxAmount ?? 0.0) * mult;
      final regInc = (initialBreakdown.regular?.priceIncludingTax ?? 0.0) * mult;

      final spEx = initialBreakdown.special != null
          ? initialBreakdown.special!.priceExcludingTax * mult
          : null;
      final spTax = initialBreakdown.special != null
          ? initialBreakdown.special!.taxAmount * mult
          : null;
      final spInc = initialBreakdown.special != null
          ? initialBreakdown.special!.priceIncludingTax * mult
          : null;

      final hasSpecial = spEx != null && spEx > 0 && spEx < regEx;

      if (initialBreakdown.isInclusive) {
        regularPriceFormatted =
            MoneyFormatter.format(regInc, market.currencySymbol, market.format);
        if (hasSpecial) {
          specialPriceFormatted = MoneyFormatter.format(
              spInc!, market.currencySymbol, market.format);
          taxAmountFormatted = MoneyFormatter.format(
              spTax!, market.currencySymbol, market.format);
          customerPriceFormatted = MoneyFormatter.format(
              spInc, market.currencySymbol, market.format);
        } else {
          taxAmountFormatted = MoneyFormatter.format(
              regTax, market.currencySymbol, market.format);
          customerPriceFormatted = MoneyFormatter.format(
              regInc, market.currencySymbol, market.format);
        }
      } else {
        regularPriceFormatted =
            MoneyFormatter.format(regEx, market.currencySymbol, market.format);
        if (hasSpecial) {
          specialPriceFormatted = MoneyFormatter.format(
              spEx, market.currencySymbol, market.format);
          taxAmountFormatted = MoneyFormatter.format(
              spTax!, market.currencySymbol, market.format);
          customerPriceFormatted = MoneyFormatter.format(
              spInc!, market.currencySymbol, market.format);
        } else {
          taxAmountFormatted = MoneyFormatter.format(
              regTax, market.currencySymbol, market.format);
          customerPriceFormatted = MoneyFormatter.format(
              regInc, market.currencySymbol, market.format);
        }
      }
    } else {
      // ── Local Dynamic Calculation ──
      final mult = market.conversionRate;
      final double convertedRegPrice = effectiveBasePrice * mult;
      final double? convertedSpPrice = (effectiveBaseSpecialPrice != null &&
              effectiveBaseSpecialPrice > 0 &&
              effectiveBaseSpecialPrice < effectiveBasePrice)
          ? (effectiveBaseSpecialPrice * mult)
          : null;

      final hasSpecial = convertedSpPrice != null;
      final double activeSellingPrice = convertedSpPrice ?? convertedRegPrice;

      double taxAmount = 0.0;
      double customerPrice = 0.0;

      if (widget.isInclusiveTax) {
        // Price already includes tax
        if (totalTaxRate > 0) {
          taxAmount = activeSellingPrice -
              (activeSellingPrice / (1.0 + (totalTaxRate / 100.0)));
        }
        customerPrice = activeSellingPrice;
      } else {
        // Tax added on top at checkout
        if (totalTaxRate > 0) {
          taxAmount = activeSellingPrice * (totalTaxRate / 100.0);
        }
        customerPrice = activeSellingPrice + taxAmount;
      }

      regularPriceFormatted = MoneyFormatter.format(
          convertedRegPrice, market.currencySymbol, market.format);
      if (hasSpecial) {
        specialPriceFormatted = MoneyFormatter.format(
            convertedSpPrice, market.currencySymbol, market.format);
      }
      taxAmountFormatted = MoneyFormatter.format(
          taxAmount, market.currencySymbol, market.format);
      customerPriceFormatted = MoneyFormatter.format(
          customerPrice, market.currencySymbol, market.format);
    }

    final double effectiveRate = initialBreakdown != null
        ? (initialBreakdown.totalRate > 0
            ? initialBreakdown.totalRate
            : totalTaxRate)
        : totalTaxRate;

    // Rate pill text: e.g. "INR · 10%"
    final rateDisplay = effectiveRate % 1 == 0
        ? effectiveRate.toInt().toString()
        : effectiveRate.toStringAsFixed(1);
    final rateBadgeText = '${market.currencyCode} · $rateDisplay%';

    // Exchange rate string: e.g. "Exchange Rate: 1 USD = ₹95.83"
    final formattedRate = MoneyFormatter.format(
      market.conversionRate,
      market.currencySymbol,
      market.format,
    );
    final exchangeRateText =
        '${context.tr('exchangeRate', fallback: 'Exchange Rate')}: 1 ${baseCurrency.code} = $formattedRate';

    return Container(
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Card Top Header ──────────────────────────────────────
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      market.name.isNotEmpty ? market.name : market.code,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                        color: theme.colorScheme.tertiary,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      taxClassesTitles,
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                        color:
                            theme.colorScheme.tertiary.withValues(alpha: 0.6),
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.primaryColor.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  rateBadgeText,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF8D6E00), // refined dark amber
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // ── Metric Columns (Selling price | Tax amount | Customer price) ──
          Row(
            children: [
              // Column 1: Selling price
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('sellingPrice', fallback: 'Selling price'),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                        color:
                            theme.colorScheme.tertiary.withValues(alpha: 0.6),
                      ),
                    ),
                    const SizedBox(height: 3),
                    if (specialPriceFormatted != null)
                      Wrap(
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 4,
                        children: [
                          Text(
                            specialPriceFormatted,
                            style: const TextStyle(
                              fontSize: 13.5,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF2E7D32),
                            ),
                          ),
                          Text(
                            regularPriceFormatted,
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w500,
                              decoration: TextDecoration.lineThrough,
                              color: theme.colorScheme.tertiary
                                  .withValues(alpha: 0.45),
                            ),
                          ),
                        ],
                      )
                    else
                      Text(
                        regularPriceFormatted,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF2E7D32),
                        ),
                      ),
                  ],
                ),
              ),

              // Column 2: Tax amount
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('taxAmount', fallback: 'Tax amount'),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                        color:
                            theme.colorScheme.tertiary.withValues(alpha: 0.6),
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      taxAmountFormatted,
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: theme.colorScheme.tertiary,
                      ),
                    ),
                  ],
                ),
              ),

              // Column 3: Customer price
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.tr('customerPrice', fallback: 'Customer price'),
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                        color:
                            theme.colorScheme.tertiary.withValues(alpha: 0.6),
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      customerPriceFormatted,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF2E7D32),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // ── Hairline Divider & Exchange Rate ──────────────────────
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.35),
          ),
          const SizedBox(height: 8),
          Text(
            exchangeRateText,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
            ),
          ),
        ],
      ),
    );
  }
}
