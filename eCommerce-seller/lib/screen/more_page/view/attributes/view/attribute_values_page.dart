import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/utils/image_path.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/bloc/screen_size/screen_size_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/model/attributes_model.dart'
    as attr_model;
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/model/attribute_values_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/bloc/attribute_values_bloc/attribute_values_bloc.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_card.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/attributes/widgets/add_attribute_value_dialog.dart';
import 'package:hyper_commerce_seller/config/app_permissions.dart';
import 'package:hyper_commerce_seller/service/demo_guard.dart';
import 'package:hyper_commerce_seller/utils/permission_checker.dart';
import 'package:hyper_commerce_seller/widgets/custom/card_shimmers.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_shimmer.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/ui/empty_state_widget.dart';

class AttributeValuesPage extends StatefulWidget {
  final attr_model.Attribute attribute;
  const AttributeValuesPage({super.key, required this.attribute});

  @override
  State<AttributeValuesPage> createState() => _AttributeValuesPageState();
}

class _AttributeValuesPageState extends State<AttributeValuesPage> {
  @override
  void initState() {
    super.initState();
    context.read<AttributeValuesBloc>().add(
      LoadAttributeValues(widget.attribute.id),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<ScreenSizeBloc, ScreenSizeState>(
      builder: (context, screenSizeState) {
        final screenType = screenSizeState.screenType;

        return CustomScaffold(
          centerTitle: true,
          showAppbar: true,
          title: context.tr('attributeValues', fallback: 'Attribute Values'),
          body: RefreshIndicator(
            color: AppColors.primaryColor,
            onRefresh: () async {
              context.read<AttributeValuesBloc>().add(
                LoadAttributeValues(widget.attribute.id),
              );
            },
            child: BlocConsumer<AttributeValuesBloc, AttributeValuesState>(
              listener: (context, state) {
                if (state is AttributeValuesFailure) {
                  showCustomSnackbar(
                    context: context,
                    message: state.error,
                    isError: true,
                  );
                }
              },
              builder: (context, state) {
                final isLoading = state is AttributeValuesLoading;
                final data = (state is AttributeValuesLoaded)
                    ? state.data
                    : (state is AttributeValuesFailure)
                    ? state.data
                    : null;
                final values = data?.values ?? [];

                if (!isLoading && values.isEmpty) {
                  return EmptyStateWidget(
                    svgPath: ImagesPath.noProductFoundSvg,
                    title:
                        context.tr('noAttributeValuesFound', fallback: 'No attribute value found'),
                    subtitle:
                        context.tr('noAttributeValuesAddedYet', fallback: 'You have not added any attribute value yet'),
                    actionText: context.tr('addValues', fallback: 'Add Values'),
                    onAction: () => _showAddDialog(context),
                  );
                }

                return Column(
                  children: [
                    Padding(
                      padding: UIUtils.pagePadding(screenType),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          isLoading
                              ? CustomShimmer(
                                  width: 150,
                                  height: UIUtils.body(screenType),
                                )
                              : Text(
                                  "${widget.attribute.title} (${values.length})",
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: UIUtils.body(screenType),
                                  ),
                                ),
                          isLoading
                              ? CustomShimmer(
                                  width: 100,
                                  height: 40,
                                  borderRadius: BorderRadius.circular(
                                    UIUtils.radiusMD(screenType),
                                  ),
                                )
                              : SecondaryButton(
                                  text: context.tr('addValue', fallback: 'Add Value'),
                                  icon: Icons.add,
                                  onPressed: () =>
                                      _showAddDialog(context),
                                ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: ListView.separated(
                        padding: UIUtils.cardsPadding(screenType),
                        itemCount: isLoading ? 6 : values.length,
                        separatorBuilder: (context, index) =>
                            SizedBox(height: UIUtils.gapMD(screenType)),
                        itemBuilder: (context, index) {
                          if (isLoading) {
                            return CardShimmer(
                              type: 'attribute',
                              screenType: screenType,
                            );
                          }
                          final value = values[index];
                          return CustomCard(
                            type: CardType.attributeValue,
                            screenType: screenType,
                            data: {
                              'id': value.id,
                              'name': value.title,
                              'swatch_value': value.swatcheValue,
                              'swatch_type': widget.attribute.swatcheType,
                              'date': value.createdAt,
                            },
                            onEdit: () =>
                                _showAddDialog(context, value: value),
                            onDelete: () =>
                                _confirmDelete(context, value.id),
                          );
                        },
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        );
      },
    );
  }

  void _showAddDialog(
    BuildContext context, {
    AttributeValue? value,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) =>
          AddAttributeValueDialog(attribute: widget.attribute, value: value),
    );
  }

  void _confirmDelete(
    BuildContext context,
    int valueId,
  ) {
    if (!PermissionChecker.hasPermission(AppPermissions.attributeDelete)) {
      showCustomSnackbar(
        context: context,
        message: context.tr(
          'noPermissionDeleteAttribute',
          fallback: "You don't have permission to delete attributes",
        ),
        isWarning: true,
      );
      return;
    }
    if (!DemoGuard.shouldProceed(context)) return;

    showAppAlertDialog(
      context: context,
      title: context.tr('deleteAttributeValue', fallback: 'Delete Attribute Value'),
      message: context.tr(
        'deleteAttributeValueConfirmation',
        fallback: 'Are you sure you want to delete this attribute value?',
      ),
      icon: Icons.delete_outline_rounded,
      isDestructive: true,
      confirmText: context.tr('delete', fallback: 'Delete'),
      cancelText: context.tr('cancel', fallback: 'Cancel'),
      onConfirm: () {
        context.read<AttributeValuesBloc>().add(
          DeleteAttributeValue(widget.attribute.id, valueId),
        );
      },
    );
  }
}
