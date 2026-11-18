import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../../../config/colors.dart';
import '../../../../../../service/api_base_helper.dart';
import '../../../../../../utils/ui_utils.dart';
import '../../../../../../widgets/custom/custom_buttons.dart';
import '../../../../../../widgets/custom/custom_dropdown.dart';
import '../../../../../../widgets/custom/custom_loading_indicator.dart';
import '../../../../../../widgets/custom/custom_scaffold.dart';
import '../../../../../../widgets/custom/custom_snackbar.dart';
import '../../../../../../widgets/custom/custom_textfield.dart';
import '../../../../../../widgets/custom/multi_selection_field.dart';
import '../../../../../../widgets/custom/custom_multi_selection_sheet.dart';
import '../../../../../../screen/products_page/product_addons/create_product_addons/models/lookup_models.dart';
import '../cubit/add_store_addon_inventory_cubit.dart';
import '../cubit/add_store_addon_inventory_state.dart';

class AddStoreAddonInventoryPage extends StatelessWidget {
  final int? inventoryId;

  const AddStoreAddonInventoryPage({super.key, this.inventoryId});

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;

    return CustomScaffold(
      showAppbar: true,
      centerTitle: true,
      title: inventoryId != null
          ? (context.tr('editStoreAddonInventory', fallback: "Edit Store Addon Inventory"))
          : (context.tr('addStoreAddonInventory', fallback: "Add Store Addon Inventory")),
      body: BlocListener<AddStoreAddonInventoryCubit, AddStoreAddonInventoryState>(
        listenWhen: (prev, curr) => prev.status != curr.status,
        listener: (context, state) {
          if (state.status == ApiStatus.success) {
            showCustomSnackbar(
              context: context,
              message: state.message ?? "Success",
            );
            Navigator.pop(context);
          } else if (state.status == ApiStatus.failure) {
            showCustomSnackbar(
              context: context,
              message:
                  state.message ??
                  (context.tr('somethingWentWrong', fallback: "Something went wrong")),
              isError: true,
            );
          }
        },
        child: BlocBuilder<AddStoreAddonInventoryCubit, AddStoreAddonInventoryState>(
          builder: (context, state) {
            final isLoadingData =
                state.lookupStatus == ApiStatus.loading ||
                (inventoryId != null &&
                    state.status == ApiStatus.loading &&
                    state.itemRows.isEmpty);

            if (isLoadingData) {
              return const Center(child: CustomLoadingIndicator());
            }

            return Column(
              children: [
                Expanded(
                  child: SingleChildScrollView(
                    padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Store Selection (Only shown in Add mode)
                        if (inventoryId == null) ...[
                          MultiSelectionField<StoreLookup>(
                            isRequired: true,
                            label: context.tr('selectStore', fallback: "Select Store"),
                            hint: context.tr('selectStore', fallback: "Select Store"),
                            selectedItems: state.selectedStores,
                            itemLabel: (s) => s.name,
                            onTap: () => _showStoreSelection(context, state),
                            onDeleted: (s) {
                              final newList = List<StoreLookup>.from(
                                state.selectedStores,
                              )..remove(s);
                              context
                                  .read<AddStoreAddonInventoryCubit>()
                                  .updateSelectedStores(newList);
                            },
                          ),
                          const SizedBox(height: 24),
                        ],

                        if (inventoryId == null) ...[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                context.tr('addonItemsToAdd', fallback: "Addon items to add"),
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 16,
                                ),
                              ),
                              SecondaryButton(
                                icon: Icons.add,
                                text: context.tr('addAddonItem', fallback: "Add Addon Item"),
                                onPressed: () {
                                  context
                                      .read<AddStoreAddonInventoryCubit>()
                                      .addRow();
                                },
                                height: 36,
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                        ],

                        // Item Rows
                        ...state.itemRows.map(
                          (row) =>
                              BlocBuilder<
                                AddStoreAddonInventoryCubit,
                                AddStoreAddonInventoryState
                              >(
                                key: ValueKey(row.id),
                                buildWhen: (prev, curr) {
                                  final prevRow = prev.itemRows.firstWhere(
                                    (r) => r.id == row.id,
                                    orElse: () => row,
                                  );
                                  final currRow = curr.itemRows.firstWhere(
                                    (r) => r.id == row.id,
                                    orElse: () => row,
                                  );
                                  return prevRow != currRow ||
                                      prev.selectedStores !=
                                          curr.selectedStores ||
                                      prev.addonGroups != curr.addonGroups;
                                },
                                builder: (context, state) {
                                  final latestRow = state.itemRows.firstWhere(
                                    (r) => r.id == row.id,
                                  );
                                  return BlocListener<
                                    AddStoreAddonInventoryCubit,
                                    AddStoreAddonInventoryState
                                  >(
                                    listenWhen: (prev, curr) =>
                                        prev.status !=
                                        curr.status, // Listen for global status changes (like success/failure)
                                    listener: (context, state) {
                                      // We can add global row logic here if needed
                                    },
                                    child: InventoryItemRowWidget(
                                      row: latestRow,
                                      state: state,
                                      isEdit: inventoryId != null,
                                    ),
                                  );
                                },
                              ),
                        ),
                      ],
                    ),
                  ),
                ),

                // Save Button
                Padding(
                  padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
                  child: PrimaryButton(
                    text: inventoryId != null
                        ? (context.tr('update', fallback: "Update"))
                        : (context.tr('save', fallback: "Save")),
                    isLoading: state.status == ApiStatus.loading,
                    onPressed: () {
                      context.read<AddStoreAddonInventoryCubit>().save();
                    },
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  void _showStoreSelection(
    BuildContext context,
    AddStoreAddonInventoryState state,
  ) {
    CustomMultiSelectionSheet.show<StoreLookup>(
      context: context,
      title: "Select Stores",
      items: state.stores
          .map((s) => MultiSelectionItem(label: s.name, value: s))
          .toList(),
      selectedValues: state.selectedStores,
      onSelected: (selected) {
        context.read<AddStoreAddonInventoryCubit>().updateSelectedStores(
          selected,
        );
      },
    );
  }
}

class InventoryItemRowWidget extends StatefulWidget {
  final InventoryItemRow row;
  final AddStoreAddonInventoryState state;

  final bool isEdit;

  const InventoryItemRowWidget({
    super.key,
    required this.row,
    required this.state,
    this.isEdit = false,
  });

  @override
  State<InventoryItemRowWidget> createState() => _InventoryItemRowWidgetState();
}

class _InventoryItemRowWidgetState extends State<InventoryItemRowWidget> {
  late TextEditingController _priceController;
  late TextEditingController _costController;
  late TextEditingController _stockController;

  @override
  void initState() {
    super.initState();
    _priceController = TextEditingController(text: widget.row.price.toString());
    _costController = TextEditingController(text: widget.row.cost.toString());
    _stockController = TextEditingController(text: widget.row.stock.toString());
  }

  @override
  void dispose() {
    _priceController.dispose();
    _costController.dispose();
    _stockController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final row = widget.row;
    final state = widget.state;

    final otherSelectedIds = state.itemRows
        .where((r) => r.id != row.id && r.selectedItem != null)
        .map((r) => r.selectedItem!.id)
        .toList();

    return BlocListener<
      AddStoreAddonInventoryCubit,
      AddStoreAddonInventoryState
    >(
      listenWhen: (prev, curr) {
        final prevRow = prev.itemRows.firstWhere(
          (r) => r.id == row.id,
          orElse: () => row,
        );
        final currRow = curr.itemRows.firstWhere(
          (r) => r.id == row.id,
          orElse: () => row,
        );

        // Only trigger listener if item changed or loading finished (external updates)
        return prevRow.selectedItem?.id != currRow.selectedItem?.id ||
            (prevRow.isLoadingState && !currRow.isLoadingState);
      },
      listener: (context, state) {
        final latestRow = state.itemRows.firstWhere((r) => r.id == row.id);
        _priceController.text = latestRow.price.toString();
        _costController.text = latestRow.cost.toString();
        _stockController.text = latestRow.stock.toString();
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!widget.isEdit) ...[
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    "Item #${state.itemRows.indexOf(row) + 1}",
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: Colors.blue,
                    ),
                  ),
                  if (state.itemRows.length > 1)
                    IconButton(
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      onPressed: () => context
                          .read<AddStoreAddonInventoryCubit>()
                          .removeRow(row.id),
                    ),
                ],
              ),
              const Divider(),
              const SizedBox(height: 8),
            ],

            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (widget.isEdit) ...[
                  Expanded(
                    child: _buildDropdown<StoreLookup>(
                      label: context.tr('store', fallback: "Store"),
                      isRequired: true,
                      value: state.selectedStores.isNotEmpty
                          ? state.selectedStores.first
                          : null,
                      items: state.stores,
                      hint: context.tr('selectStore', fallback: "Select Store"),
                      labelMapper: (s) => s.name,
                      onChanged: (val) {
                        if (val != null) {
                          context
                              .read<AddStoreAddonInventoryCubit>()
                              .updateSelectedStores([val]);
                        }
                      },
                    ),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  child: _buildDropdown<AddonGroupLookup>(
                    label: context.tr('addonGroup', fallback: "Add-on Group"),
                    isRequired: true,
                    value: row.group,
                    items: state.addonGroups,
                    hint: context.tr('addonGroup', fallback: "Select Group"),
                    labelMapper: (g) => g.title,
                    onChanged: (val) {
                      context
                          .read<AddStoreAddonInventoryCubit>()
                          .updateRowGroup(row.id, val);
                      // Clear controllers when group changes
                      _priceController.clear();
                      _costController.clear();
                      _stockController.clear();
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            _buildDropdown<AddonItemLookup>(
              label: context.tr('addonItem', fallback: "Add-on Item"),
              isRequired: true,
              value: row.selectedItem,
              items: row.availableItems,
              disabledValues: otherSelectedIds,
              onDisabledTapMessage:
                  "This item is already selected in another row",
              hint: context.tr('addonItem', fallback: "Select Item"),
              labelMapper: (i) => i.title,
              isLoading: row.isLoadingState,
              onChanged: (val) {
                context.read<AddStoreAddonInventoryCubit>().updateRowItem(
                  row.id,
                  val,
                );
                if (val != null) {
                  _priceController.text = val.price.toString();
                  _costController.text = val.cost.toString();
                } else {
                  _priceController.clear();
                  _costController.clear();
                  _stockController.clear();
                }
              },
            ),
            const SizedBox(height: 16),

            Row(
              children: [
                Expanded(
                  child: CustomTextField(
                    label: context.tr('price', fallback: "Price"),
                    isRequired: true,
                    controller: _priceController,
                    hint: "0.00",
                    prefixWidget: const Icon(Icons.payments_outlined, size: 20),
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    onChanged: (val) => context
                        .read<AddStoreAddonInventoryCubit>()
                        .updateRowFields(
                          row.id,
                          price: double.tryParse(val) ?? 0,
                        ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: CustomTextField(
                    label: context.tr('cost', fallback: "Cost"),
                    isRequired: true,
                    controller: _costController,
                    hint: "0.00",
                    prefixWidget: const Icon(
                      Icons.shopping_bag_outlined,
                      size: 20,
                    ),
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    onChanged: (val) => context
                        .read<AddStoreAddonInventoryCubit>()
                        .updateRowFields(
                          row.id,
                          cost: double.tryParse(val) ?? 0,
                        ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: CustomTextField(
                    label: context.tr('stock', fallback: "Stock"),
                    isRequired: true,
                    controller: _stockController,
                    hint: "0",
                    prefixWidget: const Icon(
                      Icons.inventory_2_outlined,
                      size: 20,
                    ),
                    keyboardType: TextInputType.number,
                    onChanged: (val) => context
                        .read<AddStoreAddonInventoryCubit>()
                        .updateRowFields(row.id, stock: int.tryParse(val) ?? 0),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),

            Row(
              children: [
                Switch.adaptive(
                  value: row.isAvailable,
                  onChanged: (val) => context
                      .read<AddStoreAddonInventoryCubit>()
                      .updateRowFields(row.id, isAvailable: val),
                  activeColor: AppColors.primaryColor,
                ),
                const SizedBox(width: 8),
                _buildLabel(context.tr('isAvailable', fallback: "Is available")),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4, left: 2),
      child: Text(
        label,
        style: const TextStyle(
          fontWeight: FontWeight.w500,
          fontSize: 13,
          color: Colors.grey,
        ),
      ),
    );
  }

  Widget _buildDropdown<T>({
    required String label,
    required T? value,
    required List<T> items,
    required String hint,
    required String Function(T) labelMapper,
    required void Function(T?) onChanged,
    bool isLoading = false,
    bool isRequired = false,
    List<dynamic> disabledValues = const [],
    String? onDisabledTapMessage,
  }) {
    return CustomDropdown<T>(
      label: label,
      hint: isLoading ? "Loading..." : hint,
      value: value,
      isRequired: isRequired,
      items: items.map((e) {
        final id = (e as dynamic).id;
        final isDisabled = disabledValues.contains(id);
        return CustomDropdownItem(
          label: labelMapper(e),
          value: e,
          isDisabled: isDisabled,
          onDisabledTapMessage: onDisabledTapMessage,
        );
      }).toList(),
      onChanged: isLoading ? null : onChanged,
    );
  }
}
