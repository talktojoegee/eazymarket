import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/services.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_return_model.dart';
import 'package:hyper_commerce_seller/utils/document_picker_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

// Typography scale — mirrors the one in order_details_page.dart.
class _T {
  _T._();
  static TextStyle get title => _b(15, FontWeight.w700);
  static TextStyle get subtitle => _b(14, FontWeight.w700);
  static TextStyle get body => _b(13, FontWeight.w600);
  static TextStyle get meta => _b(12, FontWeight.w500);
  static TextStyle get micro => _b(11, FontWeight.w800, letter: 0.08);
  static TextStyle _b(double s, FontWeight w, {double letter = 0}) =>
      TextStyle(
        fontFamily: AppConstants.fontFamily,
        fontSize: s,
        fontWeight: w,
        letterSpacing: letter,
        height: 1.35,
      );
}

class ReturnApproveResult {
  final Map<String, dynamic> body;
  final String? labelPath;

  const ReturnApproveResult(this.body, this.labelPath);
}

class ReturnApproveSheet extends StatefulWidget {
  final OrderReturn orderReturn;

  const ReturnApproveSheet({super.key, required this.orderReturn});

  static Future<ReturnApproveResult?> show(BuildContext context, OrderReturn r) {
    return showModalBottomSheet<ReturnApproveResult>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (_) => ReturnApproveSheet(orderReturn: r),
    );
  }

  @override
  State<ReturnApproveSheet> createState() => _ReturnApproveSheetState();
}

class _ReturnApproveSheetState extends State<ReturnApproveSheet> {
  late final TextEditingController _quantity;
  late final TextEditingController _shippingCharge;
  final _reason = TextEditingController();
  final _carrier = TextEditingController();
  final _tracking = TextEditingController();
  final _trackingUrl = TextEditingController();

  bool _noShippingRequired = false;
  String? _labelPath;
  String? _error;

  @override
  void initState() {
    super.initState();
    _quantity =
        TextEditingController(text: widget.orderReturn.quantity.toString());
    _shippingCharge =
        TextEditingController(text: widget.orderReturn.returnShippingCharge);
    _carrier.text = widget.orderReturn.returnCarrier ?? '';
    _tracking.text = widget.orderReturn.returnTrackingNumber ?? '';
    _trackingUrl.text = widget.orderReturn.returnTrackingUrl ?? '';
    _noShippingRequired = widget.orderReturn.noShippingRequired;
  }

  @override
  void dispose() {
    _quantity.dispose();
    _shippingCharge.dispose();
    _reason.dispose();
    _carrier.dispose();
    _tracking.dispose();
    _trackingUrl.dispose();
    super.dispose();
  }

  Future<void> _pickLabel() async {
    final path = await DocumentPickerUtils.pick(
      onTooLarge: (mb) {
        if (!mounted) return;
        showCustomSnackbar(
          context: context,
          message:
              'Label must be 5 MB or smaller (picked ${mb.toStringAsFixed(1)} MB)',
          isError: true,
        );
      },
    );
    if (path != null && mounted) setState(() => _labelPath = path);
  }

  String? _text(TextEditingController c) {
    final v = c.text.trim();
    return v.isEmpty ? null : v;
  }

  void _submit() {
    final qty = int.tryParse(_quantity.text.trim()) ?? 0;

    if (qty < 1 || qty > widget.orderReturn.quantity) {
      setState(() => _error =
          '${context.tr('quantity', fallback: "Quantity")} 1 – ${widget.orderReturn.quantity}');
      return;
    }

    final body = <String, dynamic>{
      'quantity': qty,
      'no_shipping_required': _noShippingRequired,
      if (_text(_shippingCharge) != null)
        'return_shipping_charge':
            double.tryParse(_shippingCharge.text.trim()) ?? 0,
      if (_text(_reason) != null) 'reason': _text(_reason),
      if (_text(_carrier) != null) 'return_carrier': _text(_carrier),
      if (_text(_tracking) != null) 'return_tracking_number': _text(_tracking),
      if (_text(_trackingUrl) != null)
        'return_tracking_url': _text(_trackingUrl),
    };

    Navigator.pop(context, ReturnApproveResult(body, _labelPath));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    final hasLabel = _labelPath != null;

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.85,
        maxChildSize: 0.95,
        builder: (context, scrollController) => Column(
          children: [
            _SheetHandle(),
            _SheetHeader(
              title: context.tr('approveReturn', fallback: 'Approve return'),
              subtitle:
                  'Confirm the quantity and (optionally) reverse-pickup details.',
              onClose: () => Navigator.pop(context),
            ),
            Divider(
              height: 1,
              thickness: 1,
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
            ),
            Expanded(
              child: ListView(
                controller: scrollController,
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 20),
                children: [
                  // Product title chip — neutral, was amber.
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 12, vertical: 10),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: theme.colorScheme.outlineVariant
                            .withValues(alpha: 0.5),
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 30,
                          height: 30,
                          decoration: BoxDecoration(
                            color: theme.colorScheme.outlineVariant
                                .withValues(alpha: 0.5),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          alignment: Alignment.center,
                          child: Icon(
                            Icons.assignment_return_rounded,
                            size: 16,
                            color: tertiary.withValues(alpha: 0.75),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            widget.orderReturn.productTitle ??
                                '#${widget.orderReturn.id}',
                            style: _T.subtitle.copyWith(
                              fontWeight: FontWeight.w800,
                              color: tertiary,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 18),

                  _sectionLabel(theme, 'Return details'),
                  const SizedBox(height: 10),
                  _SheetField(
                    controller: _quantity,
                    label:
                        '${context.tr('quantity', fallback: "Quantity")} (max ${widget.orderReturn.quantity})',
                    keyboard: TextInputType.number,
                    formatters: [FilteringTextInputFormatter.digitsOnly],
                  ),
                  const SizedBox(height: 12),
                  _SheetField(
                    controller: _shippingCharge,
                    label: context.tr('returnShippingCharge', fallback: 'Return shipping charge'),
                    keyboard:
                        const TextInputType.numberWithOptions(decimal: true),
                  ),
                  const SizedBox(height: 4),
                  _SwitchTile(
                    label: context.tr('noShippingRequired', fallback: 'No return shipping required'),
                    subLabel:
                        'Approve without collecting the return shipment.',
                    value: _noShippingRequired,
                    onChanged: (v) =>
                        setState(() => _noShippingRequired = v),
                  ),
                  const SizedBox(height: 16),

                  _sectionLabel(
                      theme, context.tr('reversePickup', fallback: 'Reverse pickup (optional)')),
                  const SizedBox(height: 10),
                  _SheetField(
                    controller: _carrier,
                    label: context.tr('carrierName', fallback: 'Carrier name'),
                  ),
                  const SizedBox(height: 12),
                  _SheetField(
                    controller: _tracking,
                    label: context.tr('trackingNumber', fallback: 'Tracking number'),
                  ),
                  const SizedBox(height: 12),
                  _SheetField(
                    controller: _trackingUrl,
                    label: context.tr('trackingUrl', fallback: 'Tracking URL'),
                    keyboard: TextInputType.url,
                  ),
                  const SizedBox(height: 12),
                  _SheetField(
                    controller: _reason,
                    label: context.tr('reason', fallback: 'Reason'),
                    maxLines: 2,
                  ),
                  const SizedBox(height: 12),

                  // Label attach — keep amber accent since this is the
                  // one non-obligatory action on the sheet.
                  Material(
                    color: hasLabel
                        ? AppColors.primaryColor.withValues(alpha: 0.10)
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(12),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: _pickLabel,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: AppColors.primaryColor,
                            width: 1.3,
                          ),
                        ),
                        child: Row(
                          children: [
                            const Icon(
                              Icons.attach_file_rounded,
                              size: 18,
                              color: AppColors.primaryColor,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                hasLabel
                                    ? _labelPath!.split('/').last
                                    : (context.tr('attachReturnLabel', fallback: 'Attach return label')),
                                style: _T.body.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.primaryColor,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (hasLabel)
                              GestureDetector(
                                onTap: () =>
                                    setState(() => _labelPath = null),
                                child: const Icon(
                                  Icons.close_rounded,
                                  size: 16,
                                  color: AppColors.primaryColor,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),

                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    _ErrorLine(text: _error!),
                  ],
                ],
              ),
            ),
            SafeArea(
              top: false,
              minimum: const EdgeInsets.only(bottom: 6),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 8),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green.shade600,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      textStyle:
                          _T.title.copyWith(fontWeight: FontWeight.w800),
                    ),
                    child: Text(context.tr('approve', fallback: 'Approve')),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionLabel(ThemeData theme, String text) => Text(
        text.toUpperCase(),
        style: _T.micro.copyWith(
          color: theme.colorScheme.tertiary.withValues(alpha: 0.55),
        ),
      );
}

// ═════════════════════════════════════════════════════════════════════
// Local widgets
// ═════════════════════════════════════════════════════════════════════

class _SheetHandle extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10, bottom: 4),
      child: Center(
        child: Container(
          width: 40,
          height: 4,
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.outlineVariant,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
      ),
    );
  }
}

class _SheetHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final VoidCallback onClose;

  const _SheetHeader({
    required this.title,
    required this.onClose,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 12, 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: _T.title.copyWith(
                    fontWeight: FontWeight.w800,
                    color: tertiary,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    subtitle!,
                    style: _T.meta.copyWith(
                      color: tertiary.withValues(alpha: 0.55),
                      height: 1.5,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Material(
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.35),
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: onClose,
              child: SizedBox(
                width: 34,
                height: 34,
                child: Icon(
                  Icons.close_rounded,
                  size: 18,
                  color: tertiary.withValues(alpha: 0.75),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SheetField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final TextInputType? keyboard;
  final int maxLines;
  final List<TextInputFormatter>? formatters;

  const _SheetField({
    required this.controller,
    required this.label,
    this.keyboard,
    this.maxLines = 1,
    this.formatters,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return TextField(
      controller: controller,
      keyboardType: keyboard,
      maxLines: maxLines,
      inputFormatters: formatters,
      cursorColor: AppColors.primaryColor,
      style: _T.body.copyWith(color: tertiary),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: _T.body.copyWith(
          fontWeight: FontWeight.w500,
          color: tertiary.withValues(alpha: 0.7),
        ),
        floatingLabelStyle: _T.body.copyWith(
          color: AppColors.primaryColor,
          fontWeight: FontWeight.w700,
        ),
        filled: true,
        fillColor: theme.cardColor,
        isDense: true,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.7),
          ),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.7),
          ),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(
            color: AppColors.primaryColor,
            width: 1.5,
          ),
        ),
      ),
    );
  }
}

class _SwitchTile extends StatelessWidget {
  final String label;
  final String? subLabel;
  final bool value;
  final ValueChanged<bool> onChanged;

  const _SwitchTile({
    required this.label,
    required this.value,
    required this.onChanged,
    this.subLabel,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return InkWell(
      onTap: () => onChanged(!value),
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: _T.body.copyWith(
                      fontWeight: FontWeight.w700,
                      color: tertiary,
                    ),
                  ),
                  if (subLabel != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      subLabel!,
                      style: _T.meta.copyWith(
                        color: tertiary.withValues(alpha: 0.55),
                        height: 1.5,
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

class _ErrorLine extends StatelessWidget {
  final String text;
  const _ErrorLine({required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.error_outline_rounded,
          color: AppColors.errorColor,
          size: 15,
        ),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            text,
            style: _T.meta.copyWith(
              color: AppColors.errorColor,
              fontWeight: FontWeight.w600,
              height: 1.5,
            ),
          ),
        ),
      ],
    );
  }
}
