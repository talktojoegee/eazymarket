// ignore_for_file: deprecated_member_use

import 'dart:ui';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/bloc/attributes_bloc/attributes_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/model/attribute_values_model.dart'
    as values_model;
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/model/attributes_model.dart'
    as attr_model;
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/repo/attributes_repo.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/bloc/stores_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/model/store_location_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/repo/store_location_repo.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/bloc/add_product_bloc/add_product_bloc.dart';
import 'package:hyper_commerce_seller/screen/products_page/add_products/widgets/kit_product_selection_sheet.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/critical_field_shield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_dropdown.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_upload_area.dart';

/// Step 4 — Product variation.

class ProductVariationStep extends StatefulWidget {
  const ProductVariationStep({super.key});

  @override
  State<ProductVariationStep> createState() => _ProductVariationStepState();
}

class _ProductVariationStepState extends State<ProductVariationStep> {
  String _productType = 'simple'; // simple, variable
  final TextEditingController _barcodeController = TextEditingController();
  final AttributesRepo _attributesRepo = AttributesRepo();

  // When editing (or when returning to this step), rebuild the attribute selectors
  // from the already-loaded variants so the UI shows selected attributes/values.
  bool _didHydrateAttributeSelections = false;
  bool _isHydratingAttributeSelections = false;
  final List<attr_model.Attribute> _extraAvailableAttributes = [];

  // Dimensions Controllers (Simple)
  final TextEditingController _weightController = TextEditingController();
  final TextEditingController _heightController = TextEditingController();
  final TextEditingController _breadthController = TextEditingController();
  final TextEditingController _lengthController = TextEditingController();

  // Dimensions Units (Simple)
  String _weightUnit = 'kg';
  String _heightUnit = 'cm';
  String _breadthUnit = 'cm';
  String _lengthUnit = 'cm';

  // Focus Nodes
  final FocusNode _barcodeFocus = FocusNode();
  final FocusNode _weightFocus = FocusNode();
  final FocusNode _heightFocus = FocusNode();
  final FocusNode _breadthFocus = FocusNode();
  final FocusNode _lengthFocus = FocusNode();

  // Variable Product Controllers
  final List<AttributeSelectionController> _attributeSelections = [];
  List<VariantController> _variantControllers = [];

  // Kit Product Controllers & State
  int? _selectedStoreId;
  String? _selectedStoreName;
  final List<KitComponentData> _kitComponents = [];
  final List<TextEditingController> _kitQtyControllers = [];
  final TextEditingController _kitPriceController = TextEditingController();
  final FocusNode _kitPriceFocus = FocusNode();
  bool _userOverrodeKitPrice = false;

  // Kit Location Availability
  final StoreLocationRepo _locationRepo = StoreLocationRepo();
  final List<StoreLocation> _storeLocations = [];
  final Map<int, bool> _locationAvailability = {};
  bool _isLoadingLocations = false;

  @override
  void initState() {
    super.initState();
    context.read<AttributesBloc>().add(LoadAttributesInitial());
    context.read<StoresBloc>().add(LoadStoresInitial());

    final productData = context.read<AddProductBloc>().state.productData;
    _productType = productData.type;
    _barcodeController.text = productData.barcode ?? "";

    _selectedStoreId = productData.kitStoreId;
    _selectedStoreName = productData.kitStoreName;
    _kitComponents.addAll(productData.kitComponents);
    for (final comp in _kitComponents) {
      _kitQtyControllers
          .add(TextEditingController(text: comp.quantity.toString()));
    }
    _kitPriceController.text =
        productData.kitPrice ?? productData.globalPrice ?? "";
    if (_kitPriceController.text.isNotEmpty) {
      _userOverrodeKitPrice = true;
    }

    // Restore location availability if already saved
    for (final inv in productData.locationInventory) {
      if (inv.storeId == _selectedStoreId) {
        _locationAvailability[inv.locationId] = inv.sellFromThisLocation;
      }
    }
    if (_productType == 'kit' && _kitComponents.isNotEmpty) {
      _fetchKitLocations();
    }

    if (_productType == 'simple' && productData.variants.isNotEmpty) {
      final v = productData.variants.first;
      if (_variantControllers.isEmpty) {
        _variantControllers.add(
          VariantController(
            id: v.id ?? "s_${DateTime.now().microsecondsSinceEpoch}",
            title: "simple_variant_placeholder",
            attributeValueIds: [],
          ),
        );
      }
      _variantControllers.first.weightController.text = v.weight ?? "";
      _variantControllers.first.heightController.text = v.height ?? "";
      _variantControllers.first.lengthController.text = v.length ?? "";
      _variantControllers.first.breadthController.text = v.breadth ?? "";

      // Populate local controllers for Simple UI
      _weightController.text = v.weight ?? "";
      _heightController.text = v.height ?? "";
      _lengthController.text = v.length ?? "";
      _breadthController.text = v.breadth ?? "";
    } else if (_productType == 'simple') {
      // Ensure at least one variant controller exists for simple product to hold price/stock
      if (_variantControllers.isEmpty) {
        _variantControllers.add(
          VariantController(
            id: "s_${DateTime.now().microsecondsSinceEpoch}",
            title: "simple_variant_placeholder",
            attributeValueIds: [],
          ),
        );
      }
    } else if (_productType == 'variant' && productData.variants.isNotEmpty) {
      // Reconstruct variant controllers
      _variantControllers = productData.variants.map((v) {
        final vc = VariantController(
          id: v.id ?? "v_${DateTime.now().millisecondsSinceEpoch}",
          title: v.title,
          attributeValueIds: v.attributeValueIds,
          attributes: v.attributes,
        );
        vc.nameController.text = v.title;
        vc.barcodeController.text = v.barcode ?? "";
        vc.weightController.text = v.weight ?? "";
        vc.heightController.text = v.height ?? "";
        vc.lengthController.text = v.length ?? "";
        vc.breadthController.text = v.breadth ?? "";
        vc.isAvailable = v.isAvailable;
        vc.isDefault = v.isDefault;
        vc.image = v.image;

        vc.nameController.addListener(_updateBloc);
        vc.barcodeController.addListener(_updateBloc);
        vc.weightController.addListener(_updateBloc);
        vc.heightController.addListener(_updateBloc);
        vc.lengthController.addListener(_updateBloc);
        vc.breadthController.addListener(_updateBloc);

        return vc;
      }).toList();
    }

    // Add listeners for simple product controllers
    _barcodeController.addListener(_updateBloc);
    _weightController.addListener(_updateBloc);
    _heightController.addListener(_updateBloc);
    _lengthController.addListener(_updateBloc);
    _breadthController.addListener(_updateBloc);

    // Add listeners for kit product controllers
    _kitPriceController.addListener(_onKitPriceChanged);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Update simple variant titles if they have the placeholder
    for (var controller in _variantControllers) {
      if (controller.title == "simple_variant_placeholder") {
        controller.title = context.tr('simpleVariantTitle', fallback: "Simple");
      }
    }
  }

  @override
  void dispose() {
    _barcodeController.removeListener(_updateBloc);
    _weightController.removeListener(_updateBloc);
    _heightController.removeListener(_updateBloc);
    _lengthController.removeListener(_updateBloc);
    _breadthController.removeListener(_updateBloc);
    _kitPriceController.removeListener(_onKitPriceChanged);

    _barcodeController.dispose();
    _weightController.dispose();
    _heightController.dispose();
    _breadthController.dispose();
    _lengthController.dispose();
    _kitPriceController.dispose();
    _kitPriceFocus.dispose();

    for (final c in _kitQtyControllers) {
      c.dispose();
    }

    _barcodeFocus.dispose();
    _weightFocus.dispose();
    _heightFocus.dispose();
    _breadthFocus.dispose();
    _lengthFocus.dispose();

    for (var selection in _attributeSelections) {
      selection.dispose();
    }
    for (var controller in _variantControllers) {
      controller.dispose();
    }
    super.dispose();
  }

  double _calculateKitSum() {
    double total = 0.0;
    for (final comp in _kitComponents) {
      total += (comp.price * comp.quantity);
    }
    return total;
  }

  void _recalculatePriceFromContents() {
    final sum = _calculateKitSum();
    _userOverrodeKitPrice = false;
    _kitPriceController.text = sum > 0 ? sum.toStringAsFixed(2) : '0.00';
    _updateBloc();
  }

  void _onKitPriceChanged() {
    final currentText = _kitPriceController.text.trim();
    final calculated = _calculateKitSum().toStringAsFixed(2);
    if (currentText != calculated) {
      _userOverrodeKitPrice = true;
    }
    _updateBloc();
  }

  void _addKitComponent(KitComponentData comp) {
    setState(() {
      _kitComponents.add(comp);
      _kitQtyControllers
          .add(TextEditingController(text: comp.quantity.toString()));
      if (!_userOverrodeKitPrice) {
        final sum = _calculateKitSum();
        _kitPriceController.text = sum.toStringAsFixed(2);
      }
    });
    _updateBloc();
    _fetchKitLocations();
  }

  void _removeKitComponent(int index) {
    setState(() {
      _kitComponents.removeAt(index);
      if (index < _kitQtyControllers.length) {
        _kitQtyControllers[index].dispose();
        _kitQtyControllers.removeAt(index);
      }
      if (!_userOverrodeKitPrice) {
        final sum = _calculateKitSum();
        _kitPriceController.text = sum > 0 ? sum.toStringAsFixed(2) : '0.00';
      }
    });
    _updateBloc();
    _fetchKitLocations();
  }

  Future<void> _fetchKitLocations() async {
    if (_kitComponents.isEmpty) {
      if (mounted) {
        setState(() {
          _storeLocations.clear();
          _locationAvailability.clear();
          _isLoadingLocations = false;
        });
        _updateBloc();
      }
      return;
    }

    setState(() {
      _isLoadingLocations = true;
    });

    try {
      final variantIds =
          _kitComponents.map((c) => c.productVariantId).toSet().toList();
      final response = await _locationRepo.getStoresWithLocations(
        componentVariantIds: variantIds,
      );

      List<dynamic> storesData = [];
      if (response is Map<String, dynamic> && response['data'] is List) {
        storesData = response['data'] as List;
      }

      // Find store matching _selectedStoreId, or default to first store if available
      Map<String, dynamic>? currentStoreJson;
      if (_selectedStoreId != null) {
        for (final s in storesData) {
          if (s is Map<String, dynamic> && s['id'] == _selectedStoreId) {
            currentStoreJson = s;
            break;
          }
        }
      }
      if (currentStoreJson == null &&
          storesData.isNotEmpty &&
          storesData.first is Map<String, dynamic>) {
        currentStoreJson = storesData.first as Map<String, dynamic>;
        _selectedStoreId = currentStoreJson['id'] as int?;
        _selectedStoreName = currentStoreJson['name'] as String?;
      }

      final List<StoreLocation> locs = [];
      if (currentStoreJson != null) {
        final storeId = currentStoreJson['id'] is int
            ? currentStoreJson['id'] as int
            : _selectedStoreId ?? 0;
        final rawLocs = currentStoreJson['locations'] as List? ?? [];
        for (final l in rawLocs) {
          if (l is Map<String, dynamic>) {
            locs.add(
              StoreLocation(
                id: l['id'] is int
                    ? l['id'] as int
                    : int.tryParse(l['id']?.toString() ?? '0') ?? 0,
                storeId: storeId,
                name: l['name']?.toString() ?? 'Shop location',
                isDefault: l['is_default'] == true,
                isActive: l['is_active'] != false,
              ),
            );
          }
        }
      }

      if (mounted) {
        setState(() {
          _storeLocations
            ..clear()
            ..addAll(locs);
          // Keep previously chosen availability for existing locations
          for (final l in _storeLocations) {
            _locationAvailability.putIfAbsent(l.id, () => false);
          }
          _isLoadingLocations = false;
        });
        _updateBloc();
      }
    } catch (e) {
      debugPrint('Error fetching kit locations: $e');
      if (mounted) {
        setState(() {
          _isLoadingLocations = false;
        });
        _updateBloc();
      }
    }
  }

  void _addAttribute() {
    setState(() {
      _attributeSelections.add(
        AttributeSelectionController(onChanged: _generateVariantsAuto),
      );
    });
  }

  static String _norm(String s) => s.trim().toLowerCase();

  static attr_model.Attribute? _findAttributeById(
    List<attr_model.Attribute> items,
    int id,
  ) {
    for (final a in items) {
      if (a.id == id) return a;
    }
    return null;
  }

  static attr_model.Attribute? _findAttributeBySlug(
    List<attr_model.Attribute> items,
    String slug,
  ) {
    final target = _norm(slug);
    for (final a in items) {
      if (_norm(a.slug) == target) return a;
    }
    return null;
  }

  static attr_model.Attribute? _findAttributeByTitle(
    List<attr_model.Attribute> items,
    String title,
  ) {
    final target = _norm(title);
    for (final a in items) {
      if (_norm(a.title) == target) return a;
    }
    return null;
  }

  List<attr_model.Attribute> _mergeAvailableAttributes(
    List<attr_model.Attribute> fromBloc,
  ) {
    final merged = <attr_model.Attribute>[...fromBloc];
    for (final a in _extraAvailableAttributes) {
      final exists = merged.any((x) => x.id == a.id);
      if (!exists) merged.add(a);
    }
    return merged;
  }

  void _maybeHydrateAttributeSelections(
      List<attr_model.Attribute> allAttributes) {
    if (_didHydrateAttributeSelections || _isHydratingAttributeSelections) {
      return;
    }
    if (_productType != 'variant') {
      _didHydrateAttributeSelections = true;
      return;
    }

    if (_attributeSelections.isNotEmpty) {
      _didHydrateAttributeSelections = true;
      return;
    }

    // Nothing to hydrate if there are no variants/attributes yet.
    final hasAnyAttrMappings = _variantControllers.any(
      (v) => v.attributes.isNotEmpty,
    );
    if (_variantControllers.isEmpty || !hasAnyAttrMappings) {
      _didHydrateAttributeSelections = true;
      return;
    }

    _isHydratingAttributeSelections = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _hydrateAttributeSelectionsFromVariants(allAttributes);
    });
  }

  Future<void> _hydrateAttributeSelectionsFromVariants(
    List<attr_model.Attribute> allAttributes,
  ) async {
    try {
      final aggregates = <String, _VariantAttributeAggregate>{};

      for (final vc in _variantControllers) {
        for (final raw in vc.attributes) {
          final rawAttrId = raw['attribute_id'];
          final rawAttrName = raw['attribute_name'];
          final rawValueId = raw['value_id'];
          final rawValueName = raw['value_name'];

          int? attrId;
          String? attrSlug;
          if (rawAttrId is int) {
            attrId = rawAttrId;
          } else if (rawAttrId is String) {
            final parsed = int.tryParse(rawAttrId);
            if (parsed != null) {
              attrId = parsed;
            } else if (rawAttrId.trim().isNotEmpty) {
              attrSlug = rawAttrId;
            }
          } else if (rawAttrId != null) {
            final asString = rawAttrId.toString();
            final parsed = int.tryParse(asString);
            if (parsed != null) {
              attrId = parsed;
            } else if (asString.trim().isNotEmpty) {
              attrSlug = asString;
            }
          }

          String attrName = (rawAttrName ?? '').toString().trim();
          if (attrName.isEmpty) {
            if (attrId != null) {
              attrName = _findAttributeById(allAttributes, attrId)?.title ?? "";
            } else if (attrSlug != null) {
              attrName =
                  _findAttributeBySlug(allAttributes, attrSlug)?.title ?? "";
            }
          }

          int? valueId;
          String? valueTitle;
          if (rawValueId is int) {
            valueId = rawValueId;
          } else if (rawValueId is String) {
            final parsed = int.tryParse(rawValueId);
            if (parsed != null) {
              valueId = parsed;
            } else if (rawValueId.trim().isNotEmpty) {
              valueTitle = rawValueId;
            }
          } else if (rawValueId != null) {
            final asString = rawValueId.toString();
            final parsed = int.tryParse(asString);
            if (parsed != null) {
              valueId = parsed;
            } else if (asString.trim().isNotEmpty) {
              valueTitle = asString;
            }
          }

          if (rawValueName != null &&
              rawValueName.toString().trim().isNotEmpty) {
            valueTitle = rawValueName.toString();
          }

          final key = attrId != null
              ? 'id:$attrId'
              : (attrSlug != null ? 'slug:${_norm(attrSlug)}' : null);
          if (key == null) continue;

          final agg = aggregates.putIfAbsent(
            key,
            () => _VariantAttributeAggregate(
              attrId: attrId,
              attrSlug: attrSlug,
              attrName: attrName,
            ),
          );

          if (valueId != null) agg.valueIds.add(valueId);
          if (valueTitle != null && valueTitle.trim().isNotEmpty) {
            agg.valueTitles.add(valueTitle.trim());
          }
        }
      }

      if (aggregates.isEmpty) return;

      final selections = <AttributeSelectionController>[];

      // Index for variant normalization.
      final Map<int, Map<int, values_model.AttributeValue>> valuesById = {};
      final Map<int, Map<String, values_model.AttributeValue>> valuesByTitle =
          {};

      for (final agg in aggregates.values) {
        attr_model.Attribute? attribute;

        if (agg.attrId != null) {
          attribute = _findAttributeById(allAttributes, agg.attrId!);
        }

        if (attribute == null && agg.attrSlug != null) {
          attribute = _findAttributeBySlug(allAttributes, agg.attrSlug!);
        }

        if (attribute == null && agg.attrName.trim().isNotEmpty) {
          attribute = _findAttributeByTitle(allAttributes, agg.attrName);
        }

        // Fallback: attribute not in first page of AttributesBloc results.
        if (attribute == null) {
          final query = (agg.attrSlug ?? agg.attrName).trim();
          if (query.isNotEmpty) {
            try {
              final resp = await _attributesRepo.getAttributes(
                page: 1,
                perPage: 100,
                search: query,
              );
              final parsed = attr_model.AttributesResponse.fromJson(resp);
              final items = parsed.data?.attributes ?? [];
              if (items.isNotEmpty) {
                attribute = (agg.attrSlug != null)
                    ? _findAttributeBySlug(items, agg.attrSlug!)
                    : null;
                attribute ??= _findAttributeByTitle(items, agg.attrName.trim());
                attribute ??= items.first;
              }
            } catch (_) {
              // ignore
            }
          }
        }

        if (attribute == null) continue;

        // Ensure dropdown can render this attribute even if it's not in Bloc's
        // first page.
        final attrId = attribute.id;
        final alreadyInAll = allAttributes.any((a) => a.id == attrId);
        if (!alreadyInAll) {
          final exists = _extraAvailableAttributes.any((a) => a.id == attrId);
          if (!exists) _extraAvailableAttributes.add(attribute);
        }

        final controller =
            AttributeSelectionController(onChanged: _generateVariantsAuto);
        controller.selectedAttributeId = attribute.id;
        controller.selectedAttributeName = attribute.title;

        try {
          final response =
              await _attributesRepo.getAttributeValues(attribute.id);
          final valuesResponse =
              values_model.AttributeValuesResponse.fromJson(response);
          controller.availableValues = valuesResponse.data?.values ?? [];

          // Selected by id (preferred) or by title.
          controller.selectedValues = controller.availableValues.where((v) {
            if (agg.valueIds.contains(v.id)) return true;
            if (agg.valueTitles.isEmpty) return false;
            final t = _norm(v.title);
            for (final wanted in agg.valueTitles) {
              if (_norm(wanted) == t) return true;
            }
            return false;
          }).toList();

          valuesById[attribute.id] = {
            for (final v in controller.availableValues) v.id: v,
          };
          valuesByTitle[attribute.id] = {
            for (final v in controller.availableValues) _norm(v.title): v,
          };
        } catch (_) {
          // ignore fetch failures; UI will still show the attribute selector.
        }

        selections.add(controller);
      }

      if (!mounted) return;

      // Update local UI state.
      setState(() {
        _attributeSelections
          ..clear()
          ..addAll(selections);
      });

      // Normalize variants so future auto-generation can preserve existing
      // variants by attributeValueIds.
      final allAttrsNow = _mergeAvailableAttributes(allAttributes);
      final Map<String, int> attrIdBySlug = {
        for (final a in allAttrsNow) _norm(a.slug): a.id,
      };
      final Map<int, String> attrNameById = {
        for (final a in allAttrsNow) a.id: a.title,
      };

      bool anyVariantUpdated = false;

      for (final vc in _variantControllers) {
        if (vc.attributes.isEmpty) continue;

        final newIds = <int>[];
        final newAttrs = <Map<String, dynamic>>[];

        for (final raw in vc.attributes) {
          final rawAttrId = raw['attribute_id'];

          int? attrId;
          if (rawAttrId is int) {
            attrId = rawAttrId;
          } else if (rawAttrId is String) {
            final parsed = int.tryParse(rawAttrId);
            if (parsed != null) {
              attrId = parsed;
            } else {
              attrId = attrIdBySlug[_norm(rawAttrId)];
            }
          } else if (rawAttrId != null) {
            final asString = rawAttrId.toString();
            final parsed = int.tryParse(asString);
            if (parsed != null) {
              attrId = parsed;
            } else {
              attrId = attrIdBySlug[_norm(asString)];
            }
          }

          if (attrId == null) {
            final rawAttrName = raw['attribute_name'];
            final attrName = (rawAttrName ?? '').toString().trim();
            if (attrName.isNotEmpty) {
              attrId = _findAttributeByTitle(allAttrsNow, attrName)?.id;
            }
          }

          if (attrId == null) continue;

          values_model.AttributeValue? value;

          final rawValueId = raw['value_id'];
          if (rawValueId is int) {
            value = valuesById[attrId]?[rawValueId];
          } else if (rawValueId is String) {
            final parsed = int.tryParse(rawValueId);
            if (parsed != null) {
              value = valuesById[attrId]?[parsed];
            }
          } else if (rawValueId != null) {
            final asString = rawValueId.toString();
            final parsed = int.tryParse(asString);
            if (parsed != null) {
              value = valuesById[attrId]?[parsed];
            }
          }

          if (value == null) {
            final rawValueName = raw['value_name'] ?? raw['value_id'];
            final valueName = (rawValueName ?? '').toString().trim();
            if (valueName.isNotEmpty) {
              value = valuesByTitle[attrId]?[_norm(valueName)];
            }
          }

          if (value == null) continue;

          newIds.add(value.id);
          newAttrs.add({
            "attribute_id": attrId,
            "value_id": value.id,
            "attribute_name":
                attrNameById[attrId] ?? raw['attribute_name'] ?? "",
            "value_name": value.title,
          });
        }

        if (newIds.isNotEmpty &&
            (vc.attributeValueIds.isEmpty || vc.attributes != newAttrs)) {
          vc.attributeValueIds = newIds;
          if (newAttrs.isNotEmpty) vc.attributes = newAttrs;
          anyVariantUpdated = true;
        }
      }

      if (anyVariantUpdated) {
        setState(() {});
        _updateBloc();
      }
    } finally {
      _didHydrateAttributeSelections = true;
      _isHydratingAttributeSelections = false;
    }
  }

  void _removeAttribute(int index) {
    setState(() {
      _attributeSelections[index].dispose();
      _attributeSelections.removeAt(index);
      _generateVariantsAuto();
    });
  }

  void _updateBloc() {
    if (!mounted) return;
    final bloc = context.read<AddProductBloc>();
    final currentData = bloc.state.productData;

    // Sync local dimensions for Simple product to the single variant
    if (_productType == 'simple' && _variantControllers.isNotEmpty) {
      final v = _variantControllers.first;
      v.barcodeController.text = _barcodeController.text;
      v.weightController.text = _weightController.text;
      v.heightController.text = _heightController.text;
      v.lengthController.text = _lengthController.text;
      v.breadthController.text = _breadthController.text;
    }

    final variants = _variantControllers
        .map(
          (vc) => VariantData(
            id: vc.id,
            title: vc.title,
            name: vc.nameController.text,
            attributeValueIds: vc.attributeValueIds,
            attributes: vc.attributes,
            barcode: vc.barcodeController.text,
            weight: vc.weightController.text,
            height: vc.heightController.text,
            length: vc.lengthController.text,
            breadth: vc.breadthController.text,
            isAvailable: vc.isAvailable,
            isDefault: vc.isDefault,
            image: vc.image,
          ),
        )
        .toList();

    final List<LocationInventory> locationInventory = _productType == 'kit'
        ? _storeLocations.map((loc) {
            return LocationInventory(
              storeId: _selectedStoreId ?? 0,
              storeName: _selectedStoreName ?? '',
              locationId: loc.id,
              locationName: loc.name,
              isDefault: loc.isDefault,
              sellFromThisLocation: _locationAvailability[loc.id] ?? false,
              trackInventory: false,
              qty: '0',
            );
          }).toList()
        : currentData.locationInventory;

    bloc.add(
      UpdateProductData(
        currentData.copyWith(
          type: _productType,
          barcode: _barcodeController.text,
          variants: variants,
          kitStoreId: _selectedStoreId,
          kitStoreName: _selectedStoreName,
          kitComponents: _kitComponents,
          kitPrice: _kitPriceController.text,
          globalPrice: _productType == 'kit'
              ? _kitPriceController.text
              : currentData.globalPrice,
          locationInventory: locationInventory,
        ),
      ),
    );
  }

  void _generateVariantsAuto() {
    // Collect all selected values and their names for each attribute
    final List<List<values_model.AttributeValue>> allOptions = [];
    final List<String> attributeNames = [];
    for (var selection in _attributeSelections) {
      if (selection.selectedValues.isNotEmpty) {
        allOptions.add(selection.selectedValues);
        attributeNames.add(selection.selectedAttributeName ?? "");
      }
    }

    if (allOptions.isEmpty) {
      setState(() {
        _variantControllers = [];
      });
      _updateBloc();
      return;
    }

    // Cartesian product of allOptions
    final List<List<values_model.AttributeValue>> cartesian =
        _getCartesianProduct(allOptions);

    final List<VariantController> newVariants = [];
    for (int i = 0; i < cartesian.length; i++) {
      final combo = cartesian[i];
      final title = combo.map((v) => v.title).join(", ");
      final ids = combo.map((v) => v.id).toList();

      // Try to find existing variant to preserve data
      final existingIndex = _variantControllers.indexWhere(
        (v) =>
            v.attributeValueIds.length == ids.length &&
            v.attributeValueIds.every((id) => ids.contains(id)),
      );

      if (existingIndex != -1) {
        newVariants.add(_variantControllers[existingIndex]);
      } else {
        final id = "v_${DateTime.now().microsecondsSinceEpoch}_$i";
        // Map to standard detailed attribute structure
        final attributesMapping = List.generate(combo.length, (j) {
          final cv = combo[j];
          return {
            "attribute_id": cv.globalAttributeId,
            "value_id": cv.id,
            "attribute_name": attributeNames[j],
            "value_name": cv.title,
          };
        }).toList();

        final v = VariantController(
          id: id,
          title: title,
          attributeValueIds: ids,
          attributes: attributesMapping,
        );
        v.barcodeController.addListener(_updateBloc);
        v.weightController.addListener(_updateBloc);
        v.heightController.addListener(_updateBloc);
        v.lengthController.addListener(_updateBloc);
        v.breadthController.addListener(_updateBloc);
        newVariants.add(v);
      }
    }

    setState(() {
      _variantControllers = newVariants;
    });
    _updateBloc();
  }

  List<List<T>> _getCartesianProduct<T>(List<List<T>> lists) {
    List<List<T>> result = [[]];
    for (var list in lists) {
      List<List<T>> temp = [];
      for (var prefix in result) {
        for (var item in list) {
          temp.add([...prefix, item]);
        }
      }
      result = temp;
    }
    return result;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionCard(
          title: context.tr('productType'),
          criticalFieldKey: 'type',
          children: [
            CustomDropdown<String>(
              label: null,
              value: _productType,
              items: [
                CustomDropdownItem(
                  value: 'simple',
                  label:
                      context.tr('simpleProduct', fallback: "Simple Product"),
                ),
                CustomDropdownItem(
                  value: 'variant',
                  label: context.tr('variableProduct',
                      fallback: "Variable Product"),
                ),
                CustomDropdownItem(
                  value: 'kit',
                  label: "Kit",
                ),
              ],
              onChanged: (val) {
                if (val == null || val == _productType) return;
                setState(() {
                  final previousType = _productType;
                  _productType = val;

                  if (_productType == 'simple' && _variantControllers.isEmpty) {
                    _variantControllers.add(
                      VariantController(
                        id: 's_${DateTime.now().microsecondsSinceEpoch}',
                        title: context.tr('simpleVariantTitle'),
                        attributeValueIds: [],
                      ),
                    );
                  } else if (_productType == 'variant' &&
                      previousType == 'simple') {
                    for (var vc in _variantControllers) {
                      vc.dispose();
                    }
                    _variantControllers = [];
                    for (var selection in _attributeSelections) {
                      selection.dispose();
                    }
                    _attributeSelections.clear();
                  } else if (_productType == 'kit') {
                    if (_kitPriceController.text.isEmpty &&
                        _kitComponents.isNotEmpty) {
                      _kitPriceController.text =
                          _calculateKitSum().toStringAsFixed(2);
                    }
                    if (_kitComponents.isNotEmpty) {
                      _fetchKitLocations();
                    }
                  }
                });
                _updateBloc();
              },
            ),
          ],
        ),
        const SizedBox(height: 14),
        if (_productType == 'simple') _buildSimpleProductUI(),
        if (_productType == 'variant') _buildVariableProductUI(),
        if (_productType == 'kit') _buildKitProductUI(),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildSimpleProductUI() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionCard(
          title: "Identity",
          children: [
            CustomTextField(
              label: context.tr('barcode'),
              isRequired: true,
              hint: context.tr('enterBarcode'),
              focusNode: _barcodeFocus,
              controller: _barcodeController,
            ),
          ],
        ),
        const SizedBox(height: 14),
        _SectionCard(
          title: "Dimensions",
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _DimensionField(
                    label: context.tr('weight'),
                    controller: _weightController,
                    focusNode: _weightFocus,
                    unit: _weightUnit,
                    units: ["kg", "g"],
                    onUnitChanged: (v) {
                      setState(() => _weightUnit = v!);
                      _updateBloc();
                    },
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _DimensionField(
                    label: context.tr('height'),
                    controller: _heightController,
                    focusNode: _heightFocus,
                    unit: _heightUnit,
                    units: ["cm", "m"],
                    onUnitChanged: (v) {
                      setState(() => _heightUnit = v!);
                      _updateBloc();
                    },
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _DimensionField(
                    label: context.tr('length'),
                    controller: _lengthController,
                    focusNode: _lengthFocus,
                    unit: _lengthUnit,
                    units: ["cm", "m"],
                    onUnitChanged: (v) {
                      setState(() => _lengthUnit = v!);
                      _updateBloc();
                    },
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _DimensionField(
                    label: context.tr('breadth'),
                    controller: _breadthController,
                    focusNode: _breadthFocus,
                    unit: _breadthUnit,
                    units: ["cm", "m"],
                    onUnitChanged: (v) {
                      setState(() => _breadthUnit = v!);
                      _updateBloc();
                    },
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
              ],
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildVariableProductUI() {
    return BlocBuilder<AttributesBloc, AttributesState>(
      builder: (context, attributesState) {
        final isLoadingAttributes = attributesState.isInitialLoading;
        final availableAttributes =
            _mergeAvailableAttributes(attributesState.items);

        if (!isLoadingAttributes) {
          _maybeHydrateAttributeSelections(availableAttributes);
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionCard(
              title: context.tr('attributes'),
              trailing: isLoadingAttributes
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CustomLoadingIndicator(size: 20, strokeWidth: 2),
                    )
                  : _HeaderAddChip(
                      label: context.tr('addAttribute'),
                      onTap: _addAttribute,
                    ),
              children: [
                if (_attributeSelections.isEmpty)
                  const _EmptyHint(
                    text: "Add one or more attributes (e.g. Size, Colour) "
                        "to generate variants automatically.",
                  )
                else
                  ..._attributeSelections.asMap().entries.map((entry) {
                    final int index = entry.key;
                    final controller = entry.value;
                    return Padding(
                      padding: EdgeInsets.only(
                        bottom:
                            index == _attributeSelections.length - 1 ? 0 : 12,
                      ),
                      child: _buildAttributeSelector(
                        index,
                        controller,
                        availableAttributes,
                      ),
                    );
                  }),
              ],
            ),
            const SizedBox(height: 14),
            _SectionCard(
              title:
                  "${context.tr('productVariants')} (${_variantControllers.length})",
              criticalFieldKey: 'variants',
              children: [
                if (_variantControllers.isEmpty)
                  const _EmptyHint(
                    text: "Variants will appear here once you pick "
                        "attribute values above.",
                  )
                else
                  ..._variantControllers.asMap().entries.map((entry) {
                    final int index = entry.key;
                    return Padding(
                      padding: EdgeInsets.only(
                        bottom:
                            index == _variantControllers.length - 1 ? 0 : 10,
                      ),
                      child: _buildVariantTile(index, entry.value),
                    );
                  }),
              ],
            ),
          ],
        );
      },
    );
  }

  Widget _buildKitProductUI() {
    return BlocBuilder<StoresBloc, StoresState>(
      builder: (context, storesState) {
        final stores = storesState.items;

        // Auto-select store if not yet selected and stores exist
        if (_selectedStoreId == null && stores.isNotEmpty) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted && _selectedStoreId == null) {
              setState(() {
                _selectedStoreId = stores.first.id;
                _selectedStoreName = stores.first.name;
              });
              if (_kitComponents.isNotEmpty) {
                _fetchKitLocations();
              }
            }
          });
        }

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionCard(
              title: "Store & Actions",
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: CustomDropdown<int>(
                        label: "${context.tr('store', fallback: 'Store')} *",
                        isRequired: true,
                        value: _selectedStoreId,
                        hint: storesState.isInitialLoading
                            ? "Loading stores..."
                            : "Select a store",
                        items: stores.map((s) {
                          return CustomDropdownItem<int>(
                            value: s.id,
                            label: s.name,
                          );
                        }).toList(),
                        onChanged: (newStoreId) {
                          if (newStoreId == null ||
                              newStoreId == _selectedStoreId) {
                            return;
                          }
                          final chosen = stores.firstWhere(
                              (s) => s.id == newStoreId,
                              orElse: () => stores.first);
                          setState(() {
                            _selectedStoreId = newStoreId;
                            _selectedStoreName = chosen.name;
                            _locationAvailability.clear();
                            _storeLocations.clear();
                            if (_kitComponents.isNotEmpty) {
                              _kitComponents.clear();
                              for (final c in _kitQtyControllers) {
                                c.dispose();
                              }
                              _kitQtyControllers.clear();
                              _kitPriceController.text = '0.00';
                              _userOverrodeKitPrice = false;
                            }
                          });
                          _updateBloc();
                        },
                      ),
                    ),
                    const SizedBox(width: 12),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 2.0),
                      child: InkWell(
                        onTap: () {
                          if (_selectedStoreId == null) {
                            showCustomSnackbar(
                              context: context,
                              message: 'Please select a store first',
                              isError: true,
                            );
                            return;
                          }
                          KitProductSelectionSheet.show(
                            context,
                            storeId: _selectedStoreId!,
                            onSelected: (comp) {
                              _addKitComponent(comp);
                            },
                          );
                        },
                        borderRadius: BorderRadius.circular(8),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(
                            color: const Color(
                                0xFFFFC107), // Amber yellow from screenshot
                            borderRadius: BorderRadius.circular(8),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.06),
                                blurRadius: 4,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: const [
                              Icon(Icons.add, size: 16, color: Colors.black87),
                              SizedBox(width: 4),
                              Text(
                                'Add product',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.bold,
                                  color: Colors.black87,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Kit Price Info Banner
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFF0F7FF),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFBFDBFE)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Kit price is calculated from the included products and quantities. You can edit it under Pricing and Inventory.',
                          style: TextStyle(
                            fontSize: 12,
                            color: Color(0xFF1E3A8A),
                            fontWeight: FontWeight.w400,
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 8),
                        InkWell(
                          onTap: _recalculatePriceFromContents,
                          borderRadius: BorderRadius.circular(6),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 5),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(6),
                              border:
                                  Border.all(color: const Color(0xFFEAB308)),
                              color: Colors.white,
                            ),
                            child: const Text(
                              'Recalculate from contents',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFFB45309),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    '\$${_calculateKitSum().toStringAsFixed(2)}',
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF1E293B),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),

            // Kit Price and Barcode Fields
            _SectionCard(
              title: "Kit Pricing & Identity",
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: CustomTextField(
                        label: "Kit Price",
                        isRequired: true,
                        hint: "0.00",
                        controller: _kitPriceController,
                        focusNode: _kitPriceFocus,
                        keyboardType: const TextInputType.numberWithOptions(
                            decimal: true),
                        prefixIcon: Icons.attach_money,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: CustomTextField(
                        label:
                            "${context.tr('barcode', fallback: 'Barcode')} *",
                        isRequired: true,
                        hint: "123456",
                        controller: _barcodeController,
                        focusNode: _barcodeFocus,
                        prefixIcon: Icons.qr_code_scanner,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 14),

            // Included Products List
            _SectionCard(
              title: "Included Products (${_kitComponents.length})",
              children: [
                if (_kitComponents.isEmpty) ...[
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 20.0),
                    child: Center(
                      child: Column(
                        children: [
                          Icon(Icons.inventory_2_outlined,
                              size: 40, color: Colors.grey.shade400),
                          const SizedBox(height: 8),
                          Text(
                            "No products added to kit yet",
                            style: TextStyle(
                                color: Colors.grey.shade600,
                                fontSize: 13,
                                fontWeight: FontWeight.w500),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            "Click '+ Add product' above to add products from the selected store",
                            style: TextStyle(
                                color: Colors.grey.shade500, fontSize: 11),
                          ),
                        ],
                      ),
                    ),
                  ),
                ] else ...[
                  Padding(
                    padding: const EdgeInsets.only(
                        bottom: 8.0, left: 2.0, right: 2.0),
                    child: Row(
                      children: [
                        Expanded(
                          flex: 5,
                          child: Text(
                            "Product and variant",
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurfaceVariant,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        SizedBox(
                          width: 80,
                          child: Text(
                            "Qty in pack",
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurfaceVariant,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        const SizedBox(width: 88),
                      ],
                    ),
                  ),
                  ...List.generate(_kitComponents.length, (index) {
                    final comp = _kitComponents[index];
                    final qtyController = index < _kitQtyControllers.length
                        ? _kitQtyControllers[index]
                        : TextEditingController(text: comp.quantity.toString());

                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10.0),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(
                            flex: 5,
                            child: InkWell(
                              onTap: () {
                                if (_selectedStoreId != null) {
                                  KitProductSelectionSheet.show(
                                    context,
                                    storeId: _selectedStoreId!,
                                    onSelected: (newComp) {
                                      setState(() {
                                        _kitComponents[index] =
                                            newComp.copyWith(
                                          quantity: comp.quantity,
                                        );
                                        if (!_userOverrodeKitPrice) {
                                          _kitPriceController.text =
                                              _calculateKitSum()
                                                  .toStringAsFixed(2);
                                        }
                                      });
                                      _updateBloc();
                                    },
                                  );
                                }
                              },
                              borderRadius: BorderRadius.circular(8),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 12),
                                decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(
                                    color: Theme.of(context)
                                        .colorScheme
                                        .outlineVariant
                                        .withOpacity(0.5),
                                  ),
                                  color: Theme.of(context).cardColor,
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        "${comp.productTitle} — ${comp.variantTitle}",
                                        style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w500,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    if (comp.price > 0) ...[
                                      const SizedBox(width: 6),
                                      Text(
                                        "\$${comp.price.toStringAsFixed(2)}",
                                        style: const TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                          color: AppColors.primaryColor,
                                        ),
                                      ),
                                    ],
                                    const SizedBox(width: 6),
                                    Icon(
                                      Icons.keyboard_arrow_down,
                                      size: 18,
                                      color: Theme.of(context)
                                          .colorScheme
                                          .onSurfaceVariant,
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          SizedBox(
                            width: 80,
                            child: Container(
                              height: 46,
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .outlineVariant
                                      .withOpacity(0.5),
                                ),
                                color: Theme.of(context).cardColor,
                              ),
                              child: Center(
                                child: TextField(
                                  controller: qtyController,
                                  keyboardType: TextInputType.number,
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                  ),
                                  decoration: const InputDecoration(
                                    border: InputBorder.none,
                                    isDense: true,
                                    contentPadding: EdgeInsets.zero,
                                  ),
                                  onChanged: (val) {
                                    final parsed = int.tryParse(val);
                                    if (parsed != null && parsed > 0) {
                                      setState(() {
                                        comp.quantity = parsed;
                                        if (!_userOverrodeKitPrice) {
                                          _kitPriceController.text =
                                              _calculateKitSum()
                                                  .toStringAsFixed(2);
                                        }
                                      });
                                      _updateBloc();
                                    }
                                  },
                                ),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          OutlinedButton(
                            onPressed: () => _removeKitComponent(index),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Color(0xFFFCA5A5)),
                              backgroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 10, vertical: 12),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            child: const Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.delete_outline,
                                    size: 16, color: Color(0xFFEF4444)),
                                SizedBox(width: 4),
                                Text(
                                  "Remove",
                                  style: TextStyle(
                                    color: Color(0xFFEF4444),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                ],
              ],
            ),
            const SizedBox(height: 14),
            _buildKitAvailabilityByLocationCard(),
          ],
        );
      },
    );
  }

  Widget _buildKitAvailabilityByLocationCard() {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              "Availability by location",
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: theme.colorScheme.onSurface,
              ),
            ),
            const SizedBox(width: 4),
            const Text(
              "*",
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: Color(0xFFEF4444),
              ),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          "Choose where this Kit can be sold. Its available stock is calculated from component inventory at those locations.",
          style: TextStyle(
            fontSize: 12,
            color: theme.colorScheme.onSurfaceVariant,
            height: 1.35,
          ),
        ),
        const SizedBox(height: 10),
        Container(
          width: double.infinity,
          decoration: BoxDecoration(
            color: theme.cardColor,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                color: theme.colorScheme.outlineVariant.withValues(alpha: 0.12),
                child: Row(
                  children: [
                    Text(
                      "LOCATION",
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.6,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      "AVAILABLE FOR SALE",
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.6,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1, thickness: 1),

              // Rows or loading
              if (_isLoadingLocations)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: CustomLoadingIndicator()),
                )
              else if (_storeLocations.isEmpty)
                Padding(
                  padding:
                      const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
                  child: Center(
                    child: Text(
                      _kitComponents.isEmpty
                          ? "Please add at least one product above to view available locations."
                          : "No locations found for this store.",
                      style: TextStyle(
                        fontSize: 12,
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                )
              else
                ...List.generate(_storeLocations.length, (index) {
                  final loc = _storeLocations[index];
                  final isAvailable = _locationAvailability[loc.id] ?? false;

                  return Column(
                    children: [
                      if (index > 0)
                        Divider(
                          height: 1,
                          thickness: 1,
                          color: theme.colorScheme.outlineVariant
                              .withValues(alpha: 0.25),
                        ),
                      Padding(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 8),
                        child: Row(
                          children: [
                            Icon(
                              Icons.location_on_outlined,
                              size: 18,
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      loc.name,
                                      style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  if (loc.isDefault) ...[
                                    const SizedBox(width: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 7,
                                        vertical: 2,
                                      ),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFDBEAFE),
                                        borderRadius: BorderRadius.circular(4),
                                      ),
                                      child: const Text(
                                        "Default",
                                        style: TextStyle(
                                          fontSize: 10.5,
                                          fontWeight: FontWeight.w600,
                                          color: Color(0xFF1D4ED8),
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Switch.adaptive(
                              value: isAvailable,
                              activeColor: AppColors.primaryColor,
                              onChanged: (val) {
                                setState(() {
                                  _locationAvailability[loc.id] = val;
                                });
                                _updateBloc();
                              },
                            ),
                          ],
                        ),
                      ),
                    ],
                  );
                }),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildAttributeSelector(
    int index,
    AttributeSelectionController controller,
    List<attr_model.Attribute> availableAttributes,
  ) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 8,
                  vertical: 3,
                ),
                decoration: BoxDecoration(
                  color: AppColors.primaryColor.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  "${context.tr('attribute')} ${index + 1}",
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryColor,
                    letterSpacing: 0.03,
                  ),
                ),
              ),
              const Spacer(),
              _DeleteChip(onTap: () => _removeAttribute(index)),
            ],
          ),
          const SizedBox(height: 12),
          CustomDropdown<int>(
            label: context.tr('attribute'),
            isRequired: true,
            hint: context.tr('selectAttribute'),
            value: controller.selectedAttributeId,
            items: availableAttributes
                .map(
                  (attr) =>
                      CustomDropdownItem(label: attr.title, value: attr.id),
                )
                .toList(),
            onChanged: (val) async {
              if (val != null) {
                controller.selectedAttributeId = val;
                controller.selectedAttributeName =
                    _findAttributeById(availableAttributes, val)?.title ??
                        controller.selectedAttributeName;
                controller.selectedValues = [];
                controller.availableValues = [];
                setState(() {});

                try {
                  final response = await _attributesRepo.getAttributeValues(
                    val,
                  );
                  final valuesResponse =
                      values_model.AttributeValuesResponse.fromJson(response);
                  controller.availableValues =
                      valuesResponse.data?.values ?? [];
                  if (mounted) setState(() {});
                } catch (e) {
                  // Handle error
                }
              }
            },
          ),
          if (controller.selectedAttributeId != null) ...[
            const SizedBox(height: 14),
            Text(
              context.tr('values'),
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: theme.colorScheme.tertiary.withValues(alpha: 0.6),
                letterSpacing: 0.04,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: controller.availableValues.map((val) {
                final isSelected = controller.selectedValues.any(
                  (v) => v.id == val.id,
                );
                return _ValueChip(
                  label: val.title,
                  selected: isSelected,
                  onToggle: () {
                    setState(() {
                      if (isSelected) {
                        controller.selectedValues
                            .removeWhere((v) => v.id == val.id);
                      } else {
                        controller.selectedValues.add(val);
                      }
                      _generateVariantsAuto();
                    });
                  },
                );
              }).toList(),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildVariantTile(int index, VariantController controller) {
    final theme = Theme.of(context);
    final letter = _variantLetter(index);
    final bool isDefault = controller.isDefault;

    return Container(
      decoration: BoxDecoration(
        color: isDefault
            ? AppColors.primaryColor.withValues(alpha: 0.08)
            : theme.cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isDefault
              ? AppColors.primaryColor
              : theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
          width: isDefault ? 1.2 : 1,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Theme(
        // Remove ExpansionTile's built-in top/bottom dividers.
        data: theme.copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
          leading: _VariantAvatar(letter: letter, isDefault: isDefault),
          title: Row(
            children: [
              Flexible(
                child: Text(
                  controller.title,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: theme.colorScheme.tertiary,
                  ),
                  overflow: TextOverflow.ellipsis,
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
          trailing: Icon(
            Icons.keyboard_arrow_down_rounded,
            color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
          ),
          children: [
            CustomTextField(
              label: context.tr('variantName'),
              hint: context.tr('enterName'),
              isRequired: true,
              controller: controller.nameController,
            ),
            const SizedBox(height: 14),
            CustomTextField(
              label: context.tr('barcode'),
              hint: "123456",
              isRequired: true,
              controller: controller.barcodeController,
              focusNode: controller.barcodeFocus,
            ),
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _DimensionField(
                    label: context.tr('weight'),
                    controller: controller.weightController,
                    focusNode: controller.weightFocus,
                    unit: "kg",
                    units: ["kg", "g"],
                    onUnitChanged: (v) {},
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _DimensionField(
                    label: context.tr('height'),
                    controller: controller.heightController,
                    focusNode: controller.heightFocus,
                    unit: "cm",
                    units: ["cm", "m"],
                    onUnitChanged: (v) {},
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: _DimensionField(
                    label: context.tr('length'),
                    controller: controller.lengthController,
                    focusNode: controller.lengthFocus,
                    unit: "cm",
                    units: ["cm", "m"],
                    onUnitChanged: (v) {},
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _DimensionField(
                    label: context.tr('breadth'),
                    controller: controller.breadthController,
                    focusNode: controller.breadthFocus,
                    unit: "cm",
                    units: ["cm", "m"],
                    onUnitChanged: (v) {},
                    onChanged: (_) => _updateBloc(),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _SwitchRow(
              label: context.tr('availability'),
              value: controller.isAvailable,
              onChanged: (v) {
                setState(() => controller.isAvailable = v);
                _updateBloc();
              },
            ),
            _SwitchRow(
              label: context.tr('setAsDefaultVariant'),
              value: controller.isDefault,
              onChanged: (v) {
                setState(() {
                  if (v) {
                    // Only one variant can be default.
                    for (var vc in _variantControllers) {
                      vc.isDefault = false;
                    }
                  }
                  controller.isDefault = v;
                });
                _updateBloc();
              },
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  context.tr('variantImage'),
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: theme.colorScheme.tertiary,
                  ),
                ),
                if (HiveStorage.isCriticalField('variant_images')) ...[
                  const SizedBox(width: 4),
                  const CriticalFieldShield(fieldKey: 'variant_images'),
                ],
              ],
            ),
            const SizedBox(height: 8),
            CustomUploadArea(
              hint: context.tr('uploadVariantImage'),
              fileName: controller.image,
              height: 100,
              onTap: () async {
                final path = await ImagePickerUtils.pickMedia(
                  context,
                  mediaType: MediaType.image,
                );
                if (path != null) {
                  setState(() => controller.image = path);
                  _updateBloc();
                }
              },
              onRemove: () {
                setState(() => controller.image = null);
                _updateBloc();
              },
            ),
          ],
        ),
      ),
    );
  }

  // A → Z → AA → AB … for the variant avatar letter.
  String _variantLetter(int index) {
    if (index < 26) return String.fromCharCode(65 + index);
    final first = String.fromCharCode(65 + (index ~/ 26) - 1);
    final second = String.fromCharCode(65 + (index % 26));
    return "$first$second";
  }
}

// ─── Local wizard widgets (duplicated per-batch until shared) ─────────

/// Section card shell — matches the shell used in earlier step files.
class _SectionCard extends StatelessWidget {
  final String title;
  final String? criticalFieldKey;
  final List<Widget> children;
  final Widget? trailing;

  const _SectionCard({
    required this.title,
    required this.children,
    this.criticalFieldKey,
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
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Flexible(
                        child: Text(
                          title,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: theme.colorScheme.tertiary,
                          ),
                        ),
                      ),
                      if (criticalFieldKey != null &&
                          HiveStorage.isCriticalField(criticalFieldKey!)) ...[
                        const SizedBox(width: 4),
                        CriticalFieldShield(
                          fieldKey: criticalFieldKey!,
                          size: 16,
                        ),
                      ],
                    ],
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

class _DimensionField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final FocusNode focusNode;
  final String unit;
  final List<String> units; // 👈 NEW
  final ValueChanged<String?> onUnitChanged;
  final ValueChanged<String>? onChanged;

  const _DimensionField({
    required this.label,
    required this.controller,
    required this.focusNode,
    required this.unit,
    required this.units, // 👈 NEW
    required this.onUnitChanged,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final borderColor = isDark ? Colors.grey.shade700 : Colors.grey.shade300;
    final tertiary = theme.colorScheme.tertiary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        RichText(
          text: TextSpan(
            text: label,
            style: TextStyle(
              fontWeight: FontWeight.w500,
              color: tertiary,
              fontSize: 14,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF0D1117) : Colors.white,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: borderColor),
          ),
          child: Row(
            children: [
              Expanded(
                flex: 2,
                child: TextField(
                  controller: controller,
                  focusNode: focusNode,
                  keyboardType: TextInputType.number,
                  cursorColor: AppColors.primaryColor,
                  onChanged: onChanged,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: tertiary,
                  ),
                  decoration: InputDecoration(
                    hintText: "0",
                    hintStyle: TextStyle(
                      color: Colors.grey.shade500,
                      fontSize: 15,
                    ),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 14,
                    ),
                  ),
                ),
              ),
              Container(width: 1, height: 28, color: borderColor),
              Expanded(
                flex: 1,
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: unit,
                    isExpanded: true,
                    icon: Icon(
                      Icons.keyboard_arrow_down_rounded,
                      color: tertiary.withValues(alpha: 0.55),
                    ),
                    style: TextStyle(
                      color: tertiary,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    items: units
                        .map((e) => DropdownMenuItem(
                              value: e,
                              child: Text(e),
                            ))
                        .toList(),
                    onChanged: onUnitChanged,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Small ghost-amber "+ label" chip used in section headers.
class _HeaderAddChip extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _HeaderAddChip({required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: AppColors.primaryColor,
              width: 1.2,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.add_rounded,
                size: 16,
                color: AppColors.primaryColor,
              ),
              const SizedBox(width: 4),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryColor,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Small red-tinted delete affordance (same visual language as Batch 3).
class _DeleteChip extends StatelessWidget {
  final VoidCallback onTap;

  const _DeleteChip({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.errorColor.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(8),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onTap,
        child: Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: AppColors.errorColor.withValues(alpha: 0.35),
            ),
          ),
          child: const Icon(
            Icons.delete_outline_rounded,
            size: 16,
            color: AppColors.errorColor,
          ),
        ),
      ),
    );
  }
}

/// Amber-tinted value chip (replaces FilterChip so the visual matches
/// the rest of the wizard).
class _ValueChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onToggle;

  const _ValueChip({
    required this.label,
    required this.selected,
    required this.onToggle,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: selected
          ? AppColors.primaryColor.withValues(alpha: 0.18)
          : theme.cardColor,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: onToggle,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? AppColors.primaryColor
                  : theme.colorScheme.outlineVariant.withValues(alpha: 0.6),
              width: selected ? 1.2 : 1,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (selected) ...[
                const Icon(
                  Icons.check_rounded,
                  size: 14,
                  color: AppColors.primaryColor,
                ),
                const SizedBox(width: 4),
              ],
              Text(
                label,
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                  color: selected
                      ? theme.colorScheme.tertiary
                      : theme.colorScheme.tertiary.withValues(alpha: 0.85),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A → Z letter tile for the variant header.
class _VariantAvatar extends StatelessWidget {
  final String letter;
  final bool isDefault;

  const _VariantAvatar({required this.letter, required this.isDefault});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final Color bg = isDefault
        ? AppColors.primaryColor
        : theme.colorScheme.outlineVariant.withValues(alpha: 0.5);
    final Color fg = isDefault
        ? Colors.black
        : theme.colorScheme.tertiary.withValues(alpha: 0.7);
    return Container(
      width: 34,
      height: 34,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(9),
      ),
      child: Text(
        letter,
        style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w800,
          color: fg,
        ),
      ),
    );
  }
}

/// Compact switch row used inside expanded variant tiles (avoids the
/// visual mass of SwitchListTile inside an already-nested card).
class _SwitchRow extends StatelessWidget {
  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SwitchRow({
    required this.label,
    required this.value,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: () => onChanged(!value),
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: theme.colorScheme.tertiary,
                ),
              ),
            ),
            Switch(
              value: value,
              onChanged: onChanged,
              activeThumbColor: Colors.white,
              activeTrackColor: AppColors.primaryColor,
              inactiveThumbColor: Colors.white,
              inactiveTrackColor: Colors.grey.shade300,
              trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
              trackOutlineWidth: WidgetStateProperty.all(0),
            ),
          ],
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

// ─── Helper Classes for Controllers (unchanged) ───────────────────────

class _VariantAttributeAggregate {
  final int? attrId;
  final String? attrSlug;
  final String attrName;
  final Set<int> valueIds = {};
  final Set<String> valueTitles = {};

  _VariantAttributeAggregate({
    required this.attrId,
    required this.attrSlug,
    required this.attrName,
  });
}

class AttributeSelectionController {
  int? selectedAttributeId;
  String? selectedAttributeName;
  List<values_model.AttributeValue> availableValues = [];
  List<values_model.AttributeValue> selectedValues = [];
  final VoidCallback onChanged;

  AttributeSelectionController({required this.onChanged});

  void dispose() {}
}

class VariantController {
  String id;

  String title;
  List<int> attributeValueIds;
  List<Map<String, dynamic>> attributes;
  final TextEditingController nameController = TextEditingController(); // Added
  final TextEditingController barcodeController = TextEditingController();
  final TextEditingController weightController = TextEditingController();
  final TextEditingController heightController = TextEditingController();
  final TextEditingController breadthController = TextEditingController();
  final TextEditingController lengthController = TextEditingController();

  final FocusNode barcodeFocus = FocusNode();
  final FocusNode weightFocus = FocusNode();
  final FocusNode heightFocus = FocusNode();
  final FocusNode breadthFocus = FocusNode();
  final FocusNode lengthFocus = FocusNode();

  bool isAvailable = true;
  bool isDefault = false;
  String? image;

  VariantController({
    required this.id,
    required this.title,
    required this.attributeValueIds,
    this.attributes = const [],
    this.image,
  });

  void dispose() {
    nameController.dispose();
    barcodeController.dispose();
    weightController.dispose();
    heightController.dispose();
    breadthController.dispose();
    lengthController.dispose();

    barcodeFocus.dispose();
    weightFocus.dispose();
    heightFocus.dispose();
    breadthFocus.dispose();
    lengthFocus.dispose();
  }
}

// Dashed Painter — preserved (may be imported elsewhere).
class DashedRectPainter extends CustomPainter {
  final Color color;
  final double strokeWidth;
  final double gap;

  DashedRectPainter({
    this.color = Colors.grey,
    this.strokeWidth = 1.0,
    this.gap = 5.0,
  });

  @override
  void paint(Canvas canvas, Size size) {
    Paint paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke;

    Path path = Path();
    path.addRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(0, 0, size.width, size.height),
        const Radius.circular(8),
      ),
    );

    double dashWidth = 5, dashSpace = 5;
    final Path dashedPath = Path();
    for (PathMetric measurePath in path.computeMetrics()) {
      double distance = 0;
      while (distance < measurePath.length) {
        dashedPath.addPath(
          measurePath.extractPath(distance, distance + dashWidth),
          Offset.zero,
        );
        distance += dashWidth + dashSpace;
      }
    }
    canvas.drawPath(dashedPath, paint);
  }

  @override
  bool shouldRepaint(CustomPainter oldDelegate) => false;
}
