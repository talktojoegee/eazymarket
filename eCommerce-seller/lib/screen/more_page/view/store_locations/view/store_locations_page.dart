import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hyper_commerce_seller/bloc/store_switcher/store_switcher_cubit.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/router/app_routes.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/cubit/store_location_cubit.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/model/store_location_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/store_locations/repo/store_location_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/model/store_model.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

/// Sentinel value on the filter dropdown that means "every store the user
/// can see" — a negative int no real store id will match.
const int _kAllStoresValue = -1;

class StoreLocationsPage extends StatelessWidget {
  const StoreLocationsPage({super.key});

  @override
  Widget build(BuildContext context) {
    // Seed with the app-wide selected store on first load so the page opens
    // with the same scope the rest of the app is showing.
    final initialStoreId = HiveStorage.selectedStoreId;
    return BlocProvider(
      create: (_) => StoreLocationCubit(StoreLocationRepo())
        ..load(
          all: initialStoreId == null,
          storeId: initialStoreId,
        ),
      child: _StoreLocationsView(initialStoreId: initialStoreId),
    );
  }
}

class _StoreLocationsView extends StatefulWidget {
  final int? initialStoreId;
  const _StoreLocationsView({this.initialStoreId});

  @override
  State<_StoreLocationsView> createState() => _StoreLocationsViewState();
}

class _StoreLocationsViewState extends State<_StoreLocationsView> {
  /// Currently selected value on the store dropdown. `_kAllStoresValue`
  /// means "every store". This mirrors what the cubit is fetching — we
  /// only touch it when the user picks a new value.
  late int _filterStoreId =
      widget.initialStoreId ?? _kAllStoresValue;

  void _onStoreChanged(int value) {
    if (value == _filterStoreId) return;
    setState(() => _filterStoreId = value);
    final cubit = context.read<StoreLocationCubit>();
    if (value == _kAllStoresValue) {
      cubit.load(all: true);
    } else {
      cubit.load(storeId: value);
    }
  }

  @override
  Widget build(BuildContext context) {

    return CustomScaffold(
      title: context.tr('storeLocationsTitle', fallback: 'Store Locations'),
      showAppbar: true,
      centerTitle: true,
      onBackTap: () => Navigator.of(context).pop(),
      body: BlocConsumer<StoreLocationCubit, StoreLocationState>(
        listenWhen: (p, c) =>
            p.actionMessage != c.actionMessage && c.actionMessage != null,
        listener: (context, state) {
          showCustomSnackbar(
            context: context,
            message: state.actionMessage ?? '',
            isError: state.actionSuccess == false,
          );
        },
        builder: (context, state) {
          return Column(
            children: [
              _StoreFilterBar(
                selectedStoreId: _filterStoreId,
                onChanged: _onStoreChanged,
              ),
              Expanded(child: _buildContent(context, state)),
            ],
          );
        },
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppColors.primaryColor,
        icon: const Icon(Icons.add_location_alt_outlined, color: Colors.white),
        label: Text(context.tr('addLocation', fallback: 'Add Location'),
            style: const TextStyle(color: Colors.white)),
        onPressed: () async {
          if (!DemoGuard.shouldProceed(context)) return;
          final cubit = context.read<StoreLocationCubit>();
          final changed = await context.pushNamed(AppRoutes.storeLocationForm);
          if (changed == true) cubit.refresh();
        },
      ),
    );
  }

  Widget _buildContent(
    BuildContext context,
    StoreLocationState state,
  ) {
    if (state.status == StoreLocationStatus.loading) {
      return const Center(child: CustomLoadingIndicator());
    }
    if (state.status == StoreLocationStatus.error) {
      return _ErrorView(
        message: state.error ?? '',
        onRetry: () => context.read<StoreLocationCubit>().refresh(),
      );
    }
    if (state.locations.isEmpty) {
      return _EmptyView(
        showsFilteredEmpty: _filterStoreId != _kAllStoresValue,
      );
    }
    return RefreshIndicator(
      onRefresh: () => context.read<StoreLocationCubit>().refresh(),
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: state.locations.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, i) => _LocationCard(
          location: state.locations[i],
          // Only show which store a card belongs to when the list spans
          // more than one — otherwise the header is just noise.
          showStoreName: _filterStoreId == _kAllStoresValue,
        ),
      ),
    );
  }
}

/// Store filter dropdown that sits above the list. Sourced from the same
/// `StoreSwitcherCubit` the app already keeps warm for the bottom store
/// switcher, so this doesn't fire any additional network call.
class _StoreFilterBar extends StatelessWidget {
  final int selectedStoreId;
  final ValueChanged<int> onChanged;
  const _StoreFilterBar({
    required this.selectedStoreId,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<StoreSwitcherCubit, StoreSwitcherState>(
      builder: (context, state) {
        final stores = state.stores;

        // Only one store — nothing to switch between; hide the bar entirely
        // instead of rendering a pointless one-option dropdown.
        if (stores.length <= 1) return const SizedBox.shrink();

        final validIds = stores.map((s) => s.id).toSet();
        final effectiveValue = (selectedStoreId == _kAllStoresValue ||
                validIds.contains(selectedStoreId))
            ? selectedStoreId
            : _kAllStoresValue;

        return Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: DropdownButtonFormField<int>(
            value: effectiveValue,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.tr('store', fallback: 'Store'),
              prefixIcon: const Icon(Icons.storefront_outlined),
              border: const OutlineInputBorder(),
              isDense: true,
            ),
            items: [
              DropdownMenuItem<int>(
                value: _kAllStoresValue,
                child: Text(context.tr('all', fallback: 'All stores')),
              ),
              ...stores.map<DropdownMenuItem<int>>((Store s) =>
                  DropdownMenuItem<int>(
                    value: s.id,
                    child: Text(s.name,
                        maxLines: 1, overflow: TextOverflow.ellipsis),
                  )),
            ],
            onChanged: (id) {
              if (id == null) return;
              onChanged(id);
            },
          ),
        );
      },
    );
  }
}

class _LocationCard extends StatelessWidget {
  final StoreLocation location;
  final bool showStoreName;

  const _LocationCard({
    required this.location,
    this.showStoreName = false,
  });

  String _storeNameFor(BuildContext context, int storeId) {
    final stores = context.read<StoreSwitcherCubit>().state.stores;
    for (final s in stores) {
      if (s.id == storeId) return s.name;
    }
    return 'Store #$storeId';
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final address = [location.address1, location.city, location.state, location.pinCode]
        .where((e) => e.isNotEmpty)
        .join(', ');

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF161B22) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (showStoreName) ...[
            Row(children: [
              Icon(Icons.storefront_outlined,
                  size: 13, color: Colors.grey.shade600),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  _storeNameFor(context, location.storeId),
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.2,
                    color: Colors.grey.shade700,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ]),
            const SizedBox(height: 6),
          ],
          Row(
            children: [
              Expanded(
                child: Text(location.name,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
              ),
              if (location.isDefault) _chip(context.tr('defaultLabel', fallback: 'Default'), Colors.green),
            ],
          ),
          const SizedBox(height: 6),
          Text(address, style: TextStyle(fontSize: 12.5, color: Colors.grey.shade600)),
          const SizedBox(height: 10),
          Wrap(spacing: 8, runSpacing: 6, children: [
            _chip(
              location.isRegisteredWithShiprocket
                  ? (context.tr('shiprocketReady', fallback: 'Shiprocket ready'))
                  : (context.tr('shiprocketNotRegistered', fallback: 'Not registered')),
              location.isRegisteredWithShiprocket ? Colors.blue : Colors.orange,
            ),
            if (!location.isActive) _chip(context.tr('inactive', fallback: 'Inactive'), Colors.grey),
          ]),
          const Divider(height: 20),
          Row(
            children: [
              _action(context, Icons.edit_outlined, context.tr('edit', fallback: 'Edit'), () async {
                if (!DemoGuard.shouldProceed(context)) return;
                final cubit = context.read<StoreLocationCubit>();
                final changed = await context.pushNamed(AppRoutes.storeLocationForm, extra: location);
                if (changed == true) cubit.refresh();
              }),
              if (!location.isDefault)
                _action(context, Icons.star_outline, context.tr('setDefault', fallback: 'Set default'), () {
                  if (!DemoGuard.shouldProceed(context)) return;
                  context.read<StoreLocationCubit>().promoteDefault(location.id);
                }),
              _action(context, Icons.sync, context.tr('shiprocketSyncLabel', fallback: 'Sync'), () {
                if (!DemoGuard.shouldProceed(context)) return;
                context.read<StoreLocationCubit>().shiprocketSync(location.id);
              }),
              if (!location.isDefault)
                _action(context, Icons.delete_outline, context.tr('delete', fallback: 'Delete'), () {
                  if (!DemoGuard.shouldProceed(context)) return;
                  context.read<StoreLocationCubit>().delete(location.id);
                }, color: Colors.red),
            ],
          ),
        ],
      ),
    );
  }

  Widget _chip(String text, Color color) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
      );

  Widget _action(BuildContext context, IconData icon, String label, VoidCallback onTap, {Color? color}) => Expanded(
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(children: [
              Icon(icon, size: 20, color: color ?? Colors.grey.shade700),
              const SizedBox(height: 2),
              Text(label, style: TextStyle(fontSize: 10.5, color: color ?? Colors.grey.shade700)),
            ]),
          ),
        ),
      );
}

class _EmptyView extends StatelessWidget {
  final bool showsFilteredEmpty;
  const _EmptyView({this.showsFilteredEmpty = false});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.location_off_outlined, size: 56, color: Colors.grey.shade400),
        const SizedBox(height: 12),
        Text(
          showsFilteredEmpty
              ? 'No locations for the selected store'
              : (context.tr('noLocationsFound', fallback: 'No ship-from locations yet')),
          style: TextStyle(color: Colors.grey.shade600),
        ),
      ]),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.error_outline, size: 48, color: Colors.red.shade300),
        const SizedBox(height: 10),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 24), child: Text(message, textAlign: TextAlign.center)),
        const SizedBox(height: 12),
        TextButton(onPressed: onRetry, child: const Text('Retry')),
      ]),
    );
  }
}
