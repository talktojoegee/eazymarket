// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/bloc/stores_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/tax_group/bloc/tax_group_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/model/currency_conversion_model.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/repo/add_product_repo.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/market_price_preview_widget.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';

/// Step 7 — Pricing & taxes.
///
/// Redesign is visual only — every controller, listener, bloc event,
/// `_ensureLocationState` deferred sync, and the tax-group sheet logic is
/// preserved. Content is regrouped into four section cards: **Pricing**,
/// **Taxes**, **Quick bulk actions**, **Inventory by location** (with one
/// store card per store). Inline dividers replaced with cards + hairlines;
/// currency prefix goes amber; per-location toggles reuse the switch-tile
/// pattern from the Policies step.
class PricingTaxesStep extends StatefulWidget {
  const PricingTaxesStep({super.key});

  @override
  State<PricingTaxesStep> createState() => _PricingTaxesStepState();
}

class _PricingTaxesStepState extends State<PricingTaxesStep> {
  List<int> _selectedTaxGroupIds = [];
  bool _isInclusiveTax = false;
  CurrencyConversionData? _conversionData;
  bool _loadingConversion = false;
  bool _hasUserEdited = false;

  // Global pricing controllers
  final TextEditingController _priceController = TextEditingController();
  final TextEditingController _specialPriceController =
      TextEditingController();
  final TextEditingController _costController = TextEditingController();
  final TextEditingController _skuController = TextEditingController();

  // Bulk qty controller for "Apply qty to all tracked"
  final TextEditingController _bulkQtyController = TextEditingController();

  // Per-location inventory: keyed by "storeId_locationId"
  final Map<String, _LocationState> _locationStates = {};

  // Guards against queuing more than one post-frame BLoC sync.
  bool _syncScheduled = false;

  @override
  void initState() {
    super.initState();
    context.read<TaxGroupsBloc>().add(LoadTaxGroupsInitial());
    context.read<StoresBloc>().add(LoadStoresInitial());
    _fetchCurrencyConversion();

    final productData = context.read<AddProductBloc>().state.productData;
    _selectedTaxGroupIds = List<int>.from(productData.taxGroupIds);
    _isInclusiveTax = productData.isInclusiveTax;

    // Pre-fill global price fields
    _priceController.text = productData.globalPrice ?? '';
    _specialPriceController.text = productData.globalSpecialPrice ?? '';
    _costController.text = productData.globalCost ?? '';
    _skuController.text = productData.globalSku ?? '';

    // Restore previously saved location inventory states. Rows without a real
    // location id are only used as a fallback source in _ensureLocationState.
    for (final inv in productData.locationInventory) {
      if (inv.locationId <= 0) continue;
      final key = '${inv.storeId}_${inv.locationId}';
      final ls = _LocationState(
        sellFromThisLocation: inv.sellFromThisLocation,
        trackInventory: inv.trackInventory,
        qtyController: TextEditingController(text: inv.qty),
      );
      ls.qtyController.addListener(_updateBloc);
      _locationStates[key] = ls;
    }

    // Attach listeners on global pricing controllers
    _priceController.addListener(_updateBloc);
    _specialPriceController.addListener(_updateBloc);
    _costController.addListener(_updateBloc);
    _skuController.addListener(_updateBloc);
  }

  @override
  void dispose() {
    _priceController.removeListener(_updateBloc);
    _specialPriceController.removeListener(_updateBloc);
    _costController.removeListener(_updateBloc);
    _skuController.removeListener(_updateBloc);
    _priceController.dispose();
    _specialPriceController.dispose();
    _costController.dispose();
    _skuController.dispose();
    _bulkQtyController.dispose();
    for (final ls in _locationStates.values) {
      ls.qtyController.dispose();
    }
    super.dispose();
  }

  // ─── Currency Conversion ──────────────────────────────────────────────────

  Future<void> _fetchCurrencyConversion() async {
    if (!mounted) return;
    setState(() => _loadingConversion = true);
    try {
      final repo = AddProductRepo();
      final storesBloc = context.read<StoresBloc>();
      List<int>? marketIds;
      if (storesBloc.state.items.isNotEmpty) {
        final ids = storesBloc.state.items
            .expand((s) => s.markets)
            .map((m) => m.id)
            .where((id) => id > 0)
            .toSet()
            .toList();
        if (ids.isNotEmpty) marketIds = ids;
      }
      final response = await repo.getCurrencyConversion(marketIds: marketIds);
      if (response != null && response['data'] != null && mounted) {
        final data = CurrencyConversionData.fromJson(response['data']);
        setState(() {
          _conversionData = data;
          _loadingConversion = false;
        });
      } else if (mounted) {
        setState(() => _loadingConversion = false);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _loadingConversion = false);
      }
    }
  }

  // ─── BLoC sync ────────────────────────────────────────────────────────────

  void _updateBloc() {
    if (!mounted) return;
    if (!_hasUserEdited) {
      setState(() => _hasUserEdited = true);
    } else {
      setState(() {});
    }
    final bloc = context.read<AddProductBloc>();
    final current = bloc.state.productData;

    final List<LocationInventory> inventoryList = [];
    _locationStates.forEach((key, ls) {
      final parts = key.split('_');
      if (parts.length < 2) return;
      final storeId = int.tryParse(parts[0]) ?? 0;
      final locationId = int.tryParse(parts[1]) ?? 0;
      if (storeId <= 0 || locationId <= 0) return;
      final existing = current.locationInventory.firstWhere(
        (inv) => inv.storeId == storeId && inv.locationId == locationId,
        orElse: () => LocationInventory(
          storeId: storeId,
          storeName: '',
          locationId: locationId,
          locationName: '',
        ),
      );
      inventoryList.add(existing.copyWith(
        sellFromThisLocation: ls.sellFromThisLocation,
        trackInventory: ls.trackInventory,
        qty: ls.qtyController.text,
      ));
    });

    bloc.add(UpdateProductData(
      current.copyWith(
        globalPrice: _priceController.text,
        globalSpecialPrice: _specialPriceController.text,
        globalCost: _costController.text,
        globalSku: _skuController.text,
        taxGroupIds: _selectedTaxGroupIds,
        isInclusiveTax: _isInclusiveTax,
        locationInventory: inventoryList,
      ),
    ));
  }

  // Ensure a location state exists for the given store+location; if not, create it.
  _LocationState _ensureLocationState(
    Store store,
    int locationId,
    String locationName,
    bool isDefault,
  ) {
    final key = '${store.id}_$locationId';
    if (!_locationStates.containsKey(key)) {
      final inventory =
          context.read<AddProductBloc>().state.productData.locationInventory;

      // Prefer an exact store+location match; fall back to any row for this
      // store (the API may not report the location id on every payload).
      LocationInventory? existing;
      for (final inv in inventory) {
        if (inv.storeId == store.id && inv.locationId == locationId) {
          existing = inv;
          break;
        }
        if (inv.storeId == store.id) existing ??= inv;
      }
      existing ??= LocationInventory(
        storeId: store.id,
        storeName: store.name,
        locationId: locationId,
        locationName: locationName,
        isDefault: isDefault,
      );

      final ls = _LocationState(
        sellFromThisLocation: existing.sellFromThisLocation,
        trackInventory: existing.trackInventory,
        qtyController: TextEditingController(text: existing.qty),
      );
      ls.qtyController.addListener(_updateBloc);
      _locationStates[key] = ls;
      // Created during build — push it to the BLoC once the frame settles so
      // an untouched step still submits its inventory rows.
      _scheduleBlocSync();
    }
    return _locationStates[key]!;
  }

  /// Defers a [_updateBloc] to after the current frame; safe to call from build.
  void _scheduleBlocSync() {
    if (_syncScheduled) return;
    _syncScheduled = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _syncScheduled = false;
      _updateBloc();
    });
  }

  // ─── Bulk actions ──────────────────────────────────────────────────────────

  void _setSellFromForAll(List<Store> stores, bool value) {
    setState(() {
      for (final store in stores) {
        final locationId = _defaultLocationId(store);
        if (locationId == null) continue;
        _ensureLocationState(store, locationId, 'Shop location', true)
            .sellFromThisLocation = value;
      }
    });
    _updateBloc();
  }

  void _sellFromAll(List<Store> stores) => _setSellFromForAll(stores, true);

  void _stopSellingFromAll(List<Store> stores) =>
      _setSellFromForAll(stores, false);

  /// The store's default ship-from location, or null when it has none.
  int? _defaultLocationId(Store store) {
    if (store.locations.isEmpty) return null;
    final defaultLocation = store.locations.firstWhere(
      (l) => l.isDefault == true && l.id != null,
      orElse: () => store.locations.first,
    );
    return defaultLocation.id;
  }

  void _applyQtyToAllTracked() {
    final bulkQty = _bulkQtyController.text.trim();
    setState(() {
      for (final ls in _locationStates.values) {
        if (ls.trackInventory) {
          ls.qtyController.text = bulkQty;
        }
      }
    });
    _updateBloc();
  }

  // ─── Build ─────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final currency = HiveStorage.currencySymbol;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ── Pricing ─────────────────────────────────────────────────
        _SectionCard(
          title: "Pricing",
          children: [
            _buildGlobalPricingRow(currency),
            const SizedBox(height: 10),
            const _EmptyHint(
              text:
                  "Enter prices in the platform's base currency. Customers see them converted to their market.",
            ),
          ],
        ),
        const SizedBox(height: 14),

        // ── Tax ─────────────────────────────────────────────────────
        BlocBuilder<TaxGroupsBloc, TaxGroupsState>(
          builder: (context, state) {
            final theme = Theme.of(context);
            final selectedTaxes = state.items
                .where((tax) => _selectedTaxGroupIds.contains(tax.id))
                .toList();
            return _SectionCard(
              title: "Taxes",
              trailing: selectedTaxes.isEmpty
                  ? null
                  : _CountBadge(count: selectedTaxes.length),
              children: [
                _SwitchRow(
                  label: "Prices include tax",
                  subLabel:
                      "On: the price you entered already includes tax. Off: tax is added on top at checkout.",
                  value: _isInclusiveTax,
                  onChanged: (v) {
                    setState(() {
                      _isInclusiveTax = v;
                      _hasUserEdited = true;
                    });
                    _updateBloc();
                  },
                ),
                Divider(
                  height: 1,
                  thickness: 1,
                  color:
                      theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
                ),
                const SizedBox(height: 4),
                _SelectorRow(
                  label: "Tax group",
                  value: selectedTaxes.isEmpty
                      ? ""
                      : selectedTaxes
                          .map((t) => t.market != null &&
                                  (t.market!.name.isNotEmpty ||
                                      t.market!.code.isNotEmpty)
                              ? "${t.title} (${t.market!.name.isNotEmpty ? t.market!.name : t.market!.code})"
                              : t.title)
                          .join(", "),
                  placeholder: "Select tax group",
                  onTap: () => _showTaxGroupSelection(context, state.items),
                ),
                const SizedBox(height: 8),
                const _EmptyHint(text: "Leave empty for zero tax."),
              ],
            );
          },
        ),
        const SizedBox(height: 14),

        // ── Market Price Preview ────────────────────────────────────
        BlocBuilder<TaxGroupsBloc, TaxGroupsState>(
          builder: (context, taxState) {
            final double basePrice =
                double.tryParse(_priceController.text.trim()) ?? 0.0;
            final double? baseSpecialPrice =
                double.tryParse(_specialPriceController.text.trim());

            return MarketPricePreviewWidget(
              conversionData: _conversionData,
              isLoading: _loadingConversion,
              availableTaxGroups: taxState.items,
              selectedTaxGroupIds: _selectedTaxGroupIds,
              isInclusiveTax: _isInclusiveTax,
              basePrice: basePrice,
              baseSpecialPrice: baseSpecialPrice,
              productData: context.watch<AddProductBloc>().state.productData,
              hasUserEdited: _hasUserEdited,
            );
          },
        ),
        const SizedBox(height: 14),

        // ── Inventory & Locations ───────────────────────────────────
        BlocBuilder<StoresBloc, StoresState>(
          builder: (context, storesState) {
            if (storesState.isInitialLoading) {
              return const _SectionCard(
                title: "Inventory by location",
                children: [
                  Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Center(child: CustomLoadingIndicator()),
                  ),
                ],
              );
            }
            if (storesState.items.isEmpty) {
              return _SectionCard(
                title: "Inventory by location",
                children: [
                  _EmptyHint(
                    text: context.tr('noStoresFound'),
                  ),
                ],
              );
            }

            final stores = storesState.items;

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _SectionCard(
                  title: "Quick bulk actions",
                  children: [_buildBulkActionBar(stores)],
                ),
                const SizedBox(height: 14),
                _SectionCard(
                  title: "Inventory by location",
                  trailing: _CountBadge(count: stores.length),
                  children: [
                    ...stores.asMap().entries.map((entry) {
                      final int i = entry.key;
                      final store = entry.value;
                      return Padding(
                        padding: EdgeInsets.only(
                          bottom: i == stores.length - 1 ? 0 : 12,
                        ),
                        child: _buildStoreSection(store),
                      );
                    }),
                  ],
                ),
              ],
            );
          },
        ),

        const SizedBox(height: 12),
      ],
    );
  }

  // ── Global pricing row (Price | Special Price | Cost | SKU) ──────────────

  Widget _buildGlobalPricingRow(String currency) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final isMobile = constraints.maxWidth < 600;
        if (isMobile) {
          return Column(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: _buildPriceField(
                      label: "Price",
                      controller: _priceController,
                      isRequired: true,
                      prefix: currency,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildPriceField(
                      label: "Special price",
                      controller: _specialPriceController,
                      prefix: currency,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: _buildPriceField(
                      label: "Cost",
                      controller: _costController,
                      isRequired: true,
                      prefix: currency,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildPriceField(
                      label: "SKU",
                      controller: _skuController,
                      isRequired: true,
                      isNumeric: false,
                    ),
                  ),
                ],
              ),
            ],
          );
        }
        // Wide layout — 4 columns
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: _buildPriceField(
                label: "Price",
                controller: _priceController,
                isRequired: true,
                prefix: currency,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildPriceField(
                label: "Special price",
                controller: _specialPriceController,
                prefix: currency,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildPriceField(
                label: "Cost",
                controller: _costController,
                isRequired: true,
                prefix: currency,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildPriceField(
                label: "SKU",
                controller: _skuController,
                isRequired: true,
                isNumeric: false,
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _buildPriceField({
    required String label,
    required TextEditingController controller,
    bool isRequired = false,
    String? prefix,
    bool isNumeric = true,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        RichText(
          text: TextSpan(
            text: label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w500,
              color: Theme.of(context).colorScheme.tertiary,
            ),
            children: [
              if (isRequired)
                const TextSpan(
                  text: ' *',
                  style: TextStyle(
                    color: Colors.red,
                    fontWeight: FontWeight.w600,
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 6),
        CustomTextField(
          controller: controller,
          keyboardType: isNumeric ? TextInputType.number : TextInputType.text,
          prefixWidget: prefix != null
              ? Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 14,
                  ),
                  child: Text(
                    prefix,
                    style: const TextStyle(
                      color: AppColors.primaryColor,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                )
              : null,
          hint: isNumeric ? "0" : "",
        ),
      ],
    );
  }

  // ── Bulk action bar ───────────────────────────────────────────────────────

  Widget _buildBulkActionBar(List<Store> stores) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 10,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            _BulkChip(
              icon: Icons.done_all_rounded,
              label: "Sell from all",
              color: AppColors.primaryColor,
              onTap: () => _sellFromAll(stores),
            ),
            _BulkChip(
              icon: Icons.block_rounded,
              label: "Stop selling from all",
              color: AppColors.errorColor,
              onTap: () => _stopSellingFromAll(stores),
            ),
          ],
        ),
        const SizedBox(height: 14),
        // Apply qty inline row
        Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: CustomTextField(
                controller: _bulkQtyController,
                hint: "Qty for all tracked",
                keyboardType: TextInputType.number,
              ),
            ),
            const SizedBox(width: 10),
            SizedBox(
              height: 48,
              child: ElevatedButton.icon(
                onPressed: _applyQtyToAllTracked,
                icon: const Icon(Icons.sync_rounded, size: 16),
                label: const Text("Apply"),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primaryColor,
                  foregroundColor: Colors.black,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  textStyle: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  // ── Store section ─────────────────────────────────────────────────────────

  Widget _buildStoreSection(Store store) {
    final locationId = _defaultLocationId(store);
    if (locationId == null) return const SizedBox.shrink();
    const locationName = "Shop location";
    const isDefault = true;
    final ls =
        _ensureLocationState(store, locationId, locationName, isDefault);
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Store Card Header
          Container(
            padding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
            decoration: BoxDecoration(
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.14),
              border: Border(
                bottom: BorderSide(
                  color: theme.colorScheme.outlineVariant
                      .withValues(alpha: 0.5),
                ),
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: AppColors.primaryColor.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(9),
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    Icons.storefront_rounded,
                    size: 18,
                    color: AppColors.primaryColor,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        store.name,
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                          color: theme.colorScheme.tertiary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (store.slug.isNotEmpty)
                        Text(
                          store.slug,
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: theme.colorScheme.tertiary
                                .withValues(alpha: 0.55),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: theme.cardColor,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.6),
                    ),
                  ),
                  child: Text(
                    "1 location",
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: theme.colorScheme.tertiary
                          .withValues(alpha: 0.7),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Location details
          _buildLocationRow(store, locationId, locationName, isDefault, ls),
        ],
      ),
    );
  }

  Widget _buildLocationRow(
    Store store,
    int locationId,
    String locationName,
    bool isDefault,
    _LocationState ls,
  ) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Location Header Line
          Row(
            children: [
              const Icon(
                Icons.location_on_rounded,
                size: 16,
                color: AppColors.primaryColor,
              ),
              const SizedBox(width: 6),
              Text(
                locationName,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: theme.colorScheme.tertiary,
                ),
              ),
              if (isDefault) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.primaryColor,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text(
                    "DEFAULT",
                    style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                      color: Colors.black,
                      letterSpacing: 0.06,
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 14),
          _SwitchRow(
            label: "Sell from this location",
            subLabel: "Enable product availability for this store.",
            value: ls.sellFromThisLocation,
            onChanged: (v) {
              setState(() => ls.sellFromThisLocation = v);
              _updateBloc();
            },
          ),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
          ),
          _SwitchRow(
            label: "Track inventory",
            subLabel: "Manage stock count for orders.",
            value: ls.trackInventory,
            onChanged: (v) {
              setState(() => ls.trackInventory = v);
              _updateBloc();
            },
          ),
          if (ls.trackInventory) ...[
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: 12,
                vertical: 10,
              ),
              decoration: BoxDecoration(
                color: AppColors.primaryColor.withValues(alpha: 0.06),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color:
                      AppColors.primaryColor.withValues(alpha: 0.25),
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          "Available quantity",
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: theme.colorScheme.tertiary,
                          ),
                        ),
                        Text(
                          "Stock count available at this location.",
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: theme.colorScheme.tertiary
                                .withValues(alpha: 0.55),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 10),
                  SizedBox(
                    width: 92,
                    child: CustomTextField(
                      controller: ls.qtyController,
                      hint: "0",
                      keyboardType: TextInputType.number,
                      enabled: ls.trackInventory,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ── Tax group selection sheet ─────────────────────────────────────────────

  void _showTaxGroupSelection(BuildContext context, List<dynamic> taxGroups) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final theme = Theme.of(context);
            return Container(
              height: MediaQuery.of(context).size.height * 0.55,
              decoration: BoxDecoration(
                color: theme.scaffoldBackgroundColor,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(22),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Handle
                  Padding(
                    padding: const EdgeInsets.only(top: 10, bottom: 4),
                    child: Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: theme.colorScheme.outlineVariant,
                          borderRadius: BorderRadius.circular(4),
                        ),
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 12, 12, 12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                "Select tax groups",
                                style: TextStyle(
                                  fontSize: 17,
                                  fontWeight: FontWeight.w700,
                                  color: theme.colorScheme.tertiary,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                "Tap to toggle. Multiple can be selected.",
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: theme.colorScheme.tertiary
                                      .withValues(alpha: 0.55),
                                ),
                              ),
                            ],
                          ),
                        ),
                        Material(
                          color: AppColors.primaryColor,
                          borderRadius: BorderRadius.circular(999),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(999),
                            onTap: () => Navigator.pop(context),
                            child: const Padding(
                              padding: EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 8,
                              ),
                              child: Text(
                                "Done",
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                  color: Colors.black,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Divider(
                    height: 1,
                    thickness: 1,
                    color: theme.colorScheme.outlineVariant
                        .withValues(alpha: 0.5),
                  ),
                  Expanded(
                    child: ListView.separated(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                      itemCount: taxGroups.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 4),
                      itemBuilder: (context, index) {
                        final tax = taxGroups[index];
                        final isSelected =
                            _selectedTaxGroupIds.contains(tax.id);

                        final marketLabel = tax.market != null &&
                                (tax.market!.code.isNotEmpty ||
                                    tax.market!.name.isNotEmpty)
                            ? ' (${tax.market!.name.isNotEmpty ? tax.market!.name : tax.market!.code})'
                            : '';
                        final rateSubtitle = tax.taxRates != null &&
                                tax.taxRates!.isNotEmpty
                            ? tax.taxRates!
                                .map((r) => '${r.title} (${r.rate}%)')
                                .join(', ')
                            : (tax.totalRate > 0 ? '${tax.totalRate}%' : null);

                        return _TaxTile(
                          title: '${tax.title}$marketLabel',
                          subtitle: rateSubtitle,
                          isSelected: isSelected,
                          onTap: () {
                            setState(() {
                              _hasUserEdited = true;
                              if (isSelected) {
                                _selectedTaxGroupIds.remove(tax.id);
                              } else {
                                _selectedTaxGroupIds.add(tax.id);
                              }
                            });
                            final bloc = context.read<AddProductBloc>();
                            bloc.add(
                              UpdateProductData(
                                bloc.state.productData.copyWith(
                                  taxGroupIds: _selectedTaxGroupIds,
                                ),
                              ),
                            );
                            setModalState(() {});
                          },
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
    );
  }
}

/// Mutable local state for a single store location row.
class _LocationState {
  bool sellFromThisLocation;
  bool trackInventory;
  final TextEditingController qtyController;

  _LocationState({
    required this.sellFromThisLocation,
    required this.trackInventory,
    required this.qtyController,
  });
}

// ─── Local wizard widgets (duplicated per-batch until shared) ─────────

class _SectionCard extends StatelessWidget {
  final String title;
  final List<Widget> children;
  final Widget? trailing;

  const _SectionCard({
    required this.title,
    required this.children,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: theme.cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 12, 12),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                ),
                if (trailing != null) trailing!,
              ],
            ),
          ),
          Divider(
            height: 1,
            thickness: 1,
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: children,
            ),
          ),
        ],
      ),
    );
  }
}

class _CountBadge extends StatelessWidget {
  final int count;
  const _CountBadge({required this.count});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        "$count",
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: theme.colorScheme.tertiary.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}

class _EmptyHint extends StatelessWidget {
  final String text;
  const _EmptyHint({required this.text});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Text(
      text,
      style: TextStyle(
        fontSize: 12.5,
        fontWeight: FontWeight.w500,
        color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
        height: 1.4,
      ),
    );
  }
}

class _SelectorRow extends StatelessWidget {
  final String label;
  final String value;
  final String placeholder;
  final VoidCallback onTap;

  const _SelectorRow({
    required this.label,
    required this.value,
    required this.placeholder,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final tertiary = theme.colorScheme.tertiary;
    final hasValue = value.trim().isNotEmpty;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: tertiary,
          ),
        ),
        const SizedBox(height: 8),
        Material(
          color: isDark ? const Color(0xFF0D1117) : Colors.white,
          borderRadius: BorderRadius.circular(8),
          child: InkWell(
            borderRadius: BorderRadius.circular(8),
            onTap: onTap,
            child: Container(
              padding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 14,
              ),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(8),
                border: Border.all(
                  color: isDark
                      ? Colors.grey.shade700
                      : Colors.grey.shade300,
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      hasValue ? value : placeholder,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight:
                            hasValue ? FontWeight.w600 : FontWeight.w400,
                        color:
                            hasValue ? tertiary : Colors.grey.shade500,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Icon(
                    Icons.keyboard_arrow_down_rounded,
                    size: 22,
                    color: tertiary.withValues(alpha: 0.55),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _SwitchRow extends StatelessWidget {
  final String label;
  final String? subLabel;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SwitchRow({
    required this.label,
    required this.value,
    required this.onChanged,
    this.subLabel,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: () => onChanged(!value),
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: theme.colorScheme.tertiary,
                    ),
                  ),
                  if (subLabel != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      subLabel!,
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w500,
                        color: theme.colorScheme.tertiary
                            .withValues(alpha: 0.55),
                        height: 1.35,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            Switch(
              value: value,
              onChanged: onChanged,
              activeThumbColor: Colors.white,
              activeTrackColor: AppColors.primaryColor,
              inactiveThumbColor: Colors.white,
              inactiveTrackColor: Colors.grey.shade300,
              trackOutlineColor:
                  WidgetStateProperty.all(Colors.transparent),
              trackOutlineWidth: WidgetStateProperty.all(0),
            ),
          ],
        ),
      ),
    );
  }
}

/// Bulk-action chip with an outline + subtle tint. Two per row on mobile.
class _BulkChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _BulkChip({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: color.withValues(alpha: 0.4)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 16, color: color),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TaxTile extends StatelessWidget {
  final String title;
  final String? subtitle;
  final bool isSelected;
  final VoidCallback onTap;

  const _TaxTile({
    required this.title,
    this.subtitle,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: isSelected
          ? AppColors.primaryColor.withValues(alpha: 0.10)
          : Colors.transparent,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isSelected
                  ? AppColors.primaryColor
                  : Colors.transparent,
            ),
          ),
          child: Row(
            children: [
              // Checkbox visual
              Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  color: isSelected
                      ? AppColors.primaryColor
                      : Colors.transparent,
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(
                    color: isSelected
                        ? AppColors.primaryColor
                        : theme.colorScheme.outlineVariant
                            .withValues(alpha: 0.9),
                    width: 1.5,
                  ),
                ),
                alignment: Alignment.center,
                child: isSelected
                    ? const Icon(
                        Icons.check_rounded,
                        size: 16,
                        color: Colors.black,
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontSize: 14.5,
                        fontWeight:
                            isSelected ? FontWeight.w700 : FontWeight.w500,
                        color: theme.colorScheme.tertiary,
                      ),
                    ),
                    if (subtitle != null && subtitle!.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle!,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w400,
                          color: theme.colorScheme.tertiary
                              .withValues(alpha: 0.6),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
