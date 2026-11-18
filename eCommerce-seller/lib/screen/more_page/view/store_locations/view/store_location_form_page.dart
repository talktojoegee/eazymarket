import 'dart:developer';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:geolocator/geolocator.dart';
import 'package:hyper_commerce_seller/bloc/store_switcher/store_switcher_cubit.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/model/store_location_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/repo/store_location_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/view/map_screen.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';

/// Create / edit a ship-from location. Pass an existing [location] to edit.
class StoreLocationFormPage extends StatefulWidget {
  final StoreLocation? location;
  const StoreLocationFormPage({super.key, this.location});

  @override
  State<StoreLocationFormPage> createState() => _StoreLocationFormPageState();
}

class _StoreLocationFormPageState extends State<StoreLocationFormPage> {
  final _formKey = GlobalKey<FormState>();
  final _repo = StoreLocationRepo();

  late final TextEditingController _name;
  late final TextEditingController _address1;
  late final TextEditingController _address2;
  late final TextEditingController _city;
  late final TextEditingController _state;
  late final TextEditingController _countryIso2;
  late final TextEditingController _pinCode;

  double? _latitude;
  double? _longitude;
  bool _fulfillsOnline = true;
  bool _isActive = true;
  bool _saving = false;
  int? _selectedStoreId;

  bool get _isEdit => widget.location != null;

  @override
  void initState() {
    super.initState();
    final l = widget.location;
    _name = TextEditingController(text: l?.name ?? '');
    _address1 = TextEditingController(text: l?.address1 ?? '');
    _address2 = TextEditingController(text: l?.address2 ?? '');
    _city = TextEditingController(text: l?.city ?? '');
    _state = TextEditingController(text: l?.state ?? '');
    _countryIso2 = TextEditingController(text: l?.countryIso2 ?? '');
    _pinCode = TextEditingController(text: l?.pinCode ?? '');
    _fulfillsOnline = l?.fulfillsOnlineOrders ?? true;
    _isActive = l?.isActive ?? true;
    _latitude = double.tryParse(l?.latitude ?? '');
    _longitude = double.tryParse(l?.longitude ?? '');
    // Prefer the existing location's store when editing so the seller sees
    // the same store they picked originally; fall back to the currently
    // selected global store for new locations.
    _selectedStoreId = (l != null && l.storeId != 0)
        ? l.storeId
        : HiveStorage.selectedStoreId;
  }

  @override
  void dispose() {
    for (final c in [_name, _address1, _address2, _city, _state, _countryIso2, _pinCode]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _pickOnMap() async {
    log('Pickup Map $_latitude');
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        return;
      }

      Position? position;
      try {
        position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
        ).timeout(const Duration(seconds: 5));
      } catch (e) {
        debugPrint('Timeout or error getting current position: $e');
        position = await Geolocator.getLastKnownPosition();
      }

      if (position != null) {
        _latitude = position.latitude;
        _longitude = position.longitude;
      }
    } catch (e) {
      debugPrint('Error getting location: $e');
    }
    final result = await Navigator.of(context).push<Map<String, dynamic>>(
      MaterialPageRoute(
        builder: (_) => MapScreen(initialLatitude: _latitude, initialLongitude: _longitude),
      ),
    );
    if (result == null) return;
    setState(() {
      _address1.text = (result['address'] ?? _address1.text).toString();
      _city.text = (result['city'] ?? _city.text).toString();
      _state.text = (result['state'] ?? _state.text).toString();
      _pinCode.text = (result['zipCode'] ?? _pinCode.text).toString();
      _latitude = result['latitude'] as double?;
      _longitude = result['longitude'] as double?;
      _countryIso2.text = (result['isoCountryCode'] ?? _countryIso2.text).toString();
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedStoreId == null || _selectedStoreId == 0) {
      showCustomSnackbar(
        context: context,
        message: 'Please select a store for this location',
        isError: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) return;

    setState(() => _saving = true);
    final body = <String, dynamic>{
      'store_id': _selectedStoreId,
      'name': _name.text.trim(),
      'address_1': _address1.text.trim(),
      'address_2': _address2.text.trim(),
      'city': _city.text.trim(),
      'state': _state.text.trim(),
      'country_iso2': _countryIso2.text.trim().toUpperCase(),
      'pin_code': _pinCode.text.trim(),
      if (_latitude != null) 'latitude': _latitude,
      if (_longitude != null) 'longitude': _longitude,
      'fulfills_online_orders': _fulfillsOnline ? 1 : 0,
      'is_active': _isActive ? 1 : 0,
    };

    try {
      final res = _isEdit
          ? await _repo.updateLocation(widget.location!.id, body)
          : await _repo.createLocation(body);
      final ok = res is Map && res['success'] == true;
      if (!mounted) return;
      showCustomSnackbar(
        context: context,
        message: res is Map ? (res['message']?.toString() ?? '') : '',
        isError: !ok,
      );
      if (ok) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) showCustomSnackbar(context: context, message: e.toString(), isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return CustomScaffold(
      title: _isEdit ? (context.tr('editLocation', fallback: 'Edit Location')) : (context.tr('addLocation', fallback: 'Add Location')),
      showAppbar: true,
      centerTitle: true,
      onBackTap: () => Navigator.of(context).pop(),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildStorePicker(context),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _pickOnMap,
              icon: const Icon(Icons.map_outlined),
              label: Text(context.tr('pickOnMap', fallback: 'Pick on map')),
            ),
            const SizedBox(height: 12),
            CustomTextField(label: context.tr('locationName', fallback: 'Location name'), controller: _name),
            const SizedBox(height: 12),
            CustomTextField(
              label: context.tr('addressLine1', fallback: 'Address line 1'),
              controller: _address1,
              validator: (v) => (v == null || v.trim().isEmpty) ? (context.tr('required', fallback: 'Required')) : null,
            ),
            const SizedBox(height: 12),
            CustomTextField(label: context.tr('addressLine2', fallback: 'Address line 2'), controller: _address2),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: CustomTextField(
                  label: context.tr('city', fallback: 'City'),
                  controller: _city,
                  validator: (v) => (v == null || v.trim().isEmpty) ? (context.tr('required', fallback: 'Required')) : null,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: CustomTextField(
                  label: context.tr('state', fallback: 'State'),
                  controller: _state,
                  validator: (v) => (v == null || v.trim().isEmpty) ? (context.tr('required', fallback: 'Required')) : null,
                ),
              ),
            ]),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: CustomTextField(
                  label: context.tr('countryIso2', fallback: 'Country (ISO2)'),
                  controller: _countryIso2,
                  validator: (v) => (v == null || v.trim().length != 2) ? (context.tr('required', fallback: 'ISO2')) : null,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: CustomTextField(
                  label: context.tr('pinCode', fallback: 'Pin code'),
                  controller: _pinCode,
                  validator: (v) => (v == null || v.trim().isEmpty) ? (context.tr('required', fallback: 'Required')) : null,
                ),
              ),
            ]),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(context.tr('fulfillsOnlineOrders', fallback: 'Fulfils online orders')),
              value: _fulfillsOnline,
              onChanged: (v) => setState(() => _fulfillsOnline = v),
              activeTrackColor: Colors.black,
              inactiveTrackColor: Colors.grey.shade400,
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(context.tr('active', fallback: 'Active')),
              value: _isActive,
              onChanged: (v) => setState(() => _isActive = v),
              activeTrackColor: Colors.black,
              inactiveTrackColor: Colors.grey.shade400,
            ),
            const SizedBox(height: 16),
            PrimaryButton(
              text: _isEdit ? (context.tr('update', fallback: 'Update')) : (context.tr('save', fallback: 'Save')),
              isLoading: _saving,
              onPressed: _saving ? () {} : _save,
            ),
          ],
        ),
      ),
    );
  }

  /// Store picker sourced from `StoreSwitcherCubit` (already loaded at app
  /// boot for the bottom store-switcher widget). If the seller has only one
  /// store, a read-only chip is shown instead of a dropdown so the field
  /// still reads as "yes, this location belongs to that store" without
  /// giving them a pointless one-option menu.
  Widget _buildStorePicker(BuildContext context) {
    return BlocBuilder<StoreSwitcherCubit, StoreSwitcherState>(
      builder: (context, state) {
        final stores = state.stores;

        if (state.isLoading && stores.isEmpty) {
          return const _StorePickerShimmer();
        }

        if (stores.isEmpty) {
          // Fallback — should be rare (seller must have at least one store),
          // but if the switcher hasn't loaded and we have a selected id
          // from HiveStorage we still write it on save.
          return const SizedBox.shrink();
        }

        // Ensure the currently selected id is a valid option; if it isn't
        // (e.g. the store was deleted) fall back to the first available.
        final validIds = stores.map((s) => s.id).toSet();
        final effectiveId = validIds.contains(_selectedStoreId)
            ? _selectedStoreId
            : stores.first.id;
        if (effectiveId != _selectedStoreId) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) setState(() => _selectedStoreId = effectiveId);
          });
        }

        // Single-store sellers don't need a dropdown — just show it.
        if (stores.length == 1) {
          final s = stores.first;
          return InputDecorator(
            decoration: InputDecoration(
              labelText: context.tr('store', fallback: 'Store'),
              border: const OutlineInputBorder(),
              prefixIcon: const Icon(Icons.storefront_outlined),
            ),
            child: Text(
              s.name,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          );
        }

        return DropdownButtonFormField<int>(
          value: effectiveId,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: context.tr('store', fallback: 'Store'),
            border: const OutlineInputBorder(),
            prefixIcon: const Icon(Icons.storefront_outlined),
          ),
          items: stores
              .map<DropdownMenuItem<int>>((Store s) => DropdownMenuItem<int>(
                    value: s.id,
                    child: Text(
                      s.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ))
              .toList(),
          onChanged: (id) {
            if (id == null) return;
            setState(() => _selectedStoreId = id);
          },
          validator: (v) => v == null
              ? (context.tr('required', fallback: 'Please select a store'))
              : null,
        );
      },
    );
  }
}

/// Simple placeholder while `StoreSwitcherCubit` is still loading its list.
/// A shimmer widget already exists elsewhere in the app but bringing it in
/// would drag more imports; a plain skeleton box does the job.
class _StorePickerShimmer extends StatelessWidget {
  const _StorePickerShimmer();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 58,
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.3),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppColors.primaryColor.withValues(alpha: 0.2)),
      ),
    );
  }
}
