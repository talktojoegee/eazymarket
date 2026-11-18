import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter/services.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/screen/order_page/model/order_fulfillment_model.dart';

// Typography scale — mirrors the one in order_details_page.dart.
class _T {
  _T._();
  static TextStyle get title => _b(15, FontWeight.w700);
  static TextStyle get subtitle => _b(14, FontWeight.w700);
  static TextStyle get body => _b(13, FontWeight.w600);
  static TextStyle get meta => _b(12, FontWeight.w500);
  static TextStyle get micro => _b(11, FontWeight.w800, letter: 0.08);
  static TextStyle get microXs => _b(10, FontWeight.w800, letter: 0.06);
  static TextStyle _b(double s, FontWeight w, {double letter = 0}) =>
      TextStyle(
        fontFamily: AppConstants.fontFamily,
        fontSize: s,
        fontWeight: w,
        letterSpacing: letter,
        height: 1.35,
      );
}

/// One editable parcel line: quantity capped at what is still shippable.
class _LineDraft {
  final int orderItemId;
  final String title;
  final String variant;
  final int max;
  final double weightKg;
  final TextEditingController controller;

  _LineDraft({
    required this.orderItemId,
    required this.title,
    required this.variant,
    required this.max,
    required this.weightKg,
    required int initial,
  }) : controller = TextEditingController(text: initial.toString());

  int get quantity => int.tryParse(controller.text.trim()) ?? 0;
}

/// Create-parcel / edit-parcel-lines sheet.
class FulfillmentSheet extends StatefulWidget {
  final OrderFulfillment fulfillment;
  final ParcelShipment? editing;

  const FulfillmentSheet({super.key, required this.fulfillment, this.editing});

  bool get isEditMode => editing != null;

  static Future<Map<String, dynamic>?> show(
    BuildContext context, {
    required OrderFulfillment fulfillment,
    ParcelShipment? editing,
  }) {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (_) =>
          FulfillmentSheet(fulfillment: fulfillment, editing: editing),
    );
  }

  @override
  State<FulfillmentSheet> createState() => _FulfillmentSheetState();
}

class _FulfillmentSheetState extends State<FulfillmentSheet> {
  final _carrier = TextEditingController();
  final _tracking = TextEditingController();
  final _trackingUrl = TextEditingController();
  final _length = TextEditingController();
  final _breadth = TextEditingController();
  final _height = TextEditingController();
  final _weight = TextEditingController();

  late final List<_LineDraft> _lines;
  ShipmentPackage? _package;
  String? _error;

  bool get _isShiprocket => widget.fulfillment.isShiprocket;

  @override
  void initState() {
    super.initState();
    _lines = _buildLines();

    if (widget.isEditMode) {
      final dims = widget.editing!.dims;
      _length.text = _trim(dims.lengthCm);
      _breadth.text = _trim(dims.breadthCm);
      _height.text = _trim(dims.heightCm);
      _weight.text = _trim(dims.weightKg);
    } else if (_isShiprocket) {
      final defaultPackage = widget.fulfillment.packages
          .where((p) => p.isDefault)
          .cast<ShipmentPackage?>()
          .firstWhere((_) => true, orElse: () => null);
      if (defaultPackage != null) {
        _applyPackage(defaultPackage);
      } else {
        final d = widget.fulfillment.defaults;
        _length.text = _trim(d.lengthCm);
        _breadth.text = _trim(d.breadthCm);
        _height.text = _trim(d.heightCm);
        _weight.text =
            _trim(_itemsWeight() > 0 ? _itemsWeight() : d.weightKg);
      }
    }
  }

  List<_LineDraft> _buildLines() {
    if (widget.isEditMode) {
      return widget.editing!.editLines
          .map((l) => _LineDraft(
                orderItemId: l.orderItemId,
                title: l.title,
                variant: l.variant,
                max: l.remaining,
                weightKg: l.weightKg,
                initial: l.preset,
              ))
          .toList();
    }
    return widget.fulfillment.lines
        .where((l) => l.remaining > 0)
        .map((l) => _LineDraft(
              orderItemId: l.orderItemId,
              title: l.title,
              variant: l.variant,
              max: l.remaining,
              weightKg: l.weightKg,
              initial: l.remaining,
            ))
        .toList();
  }

  @override
  void dispose() {
    for (final l in _lines) {
      l.controller.dispose();
    }
    _carrier.dispose();
    _tracking.dispose();
    _trackingUrl.dispose();
    _length.dispose();
    _breadth.dispose();
    _height.dispose();
    _weight.dispose();
    super.dispose();
  }

  String _trim(double v) {
    if (v == v.roundToDouble()) return v.toStringAsFixed(0);
    return v.toString();
  }

  double _itemsWeight() {
    double sum = 0;
    for (final l in _lines) {
      sum += l.quantity * l.weightKg;
    }
    return VolumetricWeight.round3(sum);
  }

  void _applyPackage(ShipmentPackage p) {
    _package = p;
    _length.text = _trim(p.lengthCm);
    _breadth.text = _trim(p.breadthCm);
    _height.text = _trim(p.heightCm);
    _weight.text =
        _trim(VolumetricWeight.round3(p.weightKg + _itemsWeight()));
  }

  double _num(TextEditingController c) => double.tryParse(c.text.trim()) ?? 0;

  void _submit() {
    final picked = _lines.where((l) => l.quantity > 0).toList();

    if (_lines.isNotEmpty && picked.isEmpty) {
      setState(() => _error =
          context.tr('selectAtLeastOneItem', fallback: 'Select at least one item'));
      return;
    }
    for (final l in _lines) {
      if (l.quantity > l.max) {
        setState(() =>
            _error = 'Quantity for "${l.title}" exceeds ${l.max}');
        return;
      }
    }

    final items = picked
        .map((l) => {'order_item_id': l.orderItemId, 'quantity': l.quantity})
        .toList();

    final body = <String, dynamic>{};

    if (_isShiprocket) {
      body['length_cm'] =
          _length.text.trim().isEmpty ? null : _num(_length);
      body['breadth_cm'] =
          _breadth.text.trim().isEmpty ? null : _num(_breadth);
      body['height_cm'] =
          _height.text.trim().isEmpty ? null : _num(_height);
      body['weight_kg'] =
          _weight.text.trim().isEmpty ? null : _num(_weight);
    } else {
      body['carrier_name'] =
          _carrier.text.trim().isEmpty ? null : _carrier.text.trim();
      body['tracking_number'] =
          _tracking.text.trim().isEmpty ? null : _tracking.text.trim();
      body['tracking_url'] =
          _trackingUrl.text.trim().isEmpty ? null : _trackingUrl.text.trim();
    }

    if (items.isNotEmpty || widget.isEditMode) {
      body['items'] = items;
    }

    Navigator.pop(context, body);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = widget.isEditMode
        ? (context.tr('editItems', fallback: 'Edit items'))
        : (context.tr('createParcel', fallback: 'Create parcel'));

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
              title: title,
              subtitle: widget.isEditMode
                  ? 'Adjust item quantities for this parcel.'
                  : 'Choose items and carrier details for the parcel.',
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
                  if (_isShiprocket) _shiprocketNote(theme, null),
                  if (_lines.isNotEmpty) ...[
                    _sectionLabel(theme,
                        context.tr('orderItems', fallback: 'Items in this parcel')),
                    const SizedBox(height: 8),
                    ..._lines.map((l) => _lineRow(theme, l)),
                    const SizedBox(height: 8),
                  ],
                  if (_isShiprocket)
                    ..._shiprocketBlock(theme, null)
                  else
                    ..._manualBlock(theme, null),
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
                      backgroundColor: AppColors.primaryColor,
                      foregroundColor: Colors.black,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      textStyle:
                          _T.title.copyWith(fontWeight: FontWeight.w800),
                    ),
                    child: Text(
                      widget.isEditMode
                          ? (context.tr('save', fallback: 'Save'))
                          : (context.tr('createParcel', fallback: 'Create parcel')),
                    ),
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

  Widget _shiprocketNote(ThemeData theme, dynamic l10n) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.blue.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.blue.withValues(alpha: 0.3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline_rounded,
              size: 16, color: Colors.blue.shade700),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Booked automatically through ${widget.fulfillment.shiprocketTitle}. '
              'Courier, AWB and label are assigned by ${widget.fulfillment.shiprocketTitle} — '
              'no manual tracking to enter.',
              style: _T.meta.copyWith(
                color: Colors.blue.shade900,
                height: 1.5,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _lineRow(ThemeData theme, _LineDraft line) {
    final tertiary = theme.colorScheme.tertiary;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: theme.colorScheme.outlineVariant.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  line.title,
                  style: _T.body.copyWith(
                    fontWeight: FontWeight.w700,
                    color: tertiary,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (line.variant.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      line.variant,
                      style: _T.meta.copyWith(
                        color: tertiary.withValues(alpha: 0.6),
                      ),
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.5),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      'Max ${line.max}',
                      style: _T.microXs.copyWith(
                        color: tertiary.withValues(alpha: 0.75),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          SizedBox(
            width: 72,
            child: TextField(
              controller: line.controller,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              textAlign: TextAlign.center,
              cursorColor: AppColors.primaryColor,
              style: _T.subtitle.copyWith(color: tertiary),
              onChanged: (_) {
                if (_isShiprocket && _package != null) {
                  setState(() => _applyPackage(_package!));
                } else {
                  setState(() {});
                }
              },
              decoration: InputDecoration(
                isDense: true,
                filled: true,
                fillColor: theme.cardColor,
                contentPadding: const EdgeInsets.symmetric(
                    vertical: 12, horizontal: 8),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(
                    color: theme.colorScheme.outlineVariant
                        .withValues(alpha: 0.7),
                  ),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(
                    color: theme.colorScheme.outlineVariant
                        .withValues(alpha: 0.7),
                  ),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(
                    color: AppColors.primaryColor,
                    width: 1.5,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _manualBlock(ThemeData theme, dynamic l10n) {
    return [
      _sectionLabel(theme, context.tr('trackingDetails', fallback: 'Tracking (optional)')),
      const SizedBox(height: 10),
      _field(_carrier, context.tr('carrierName', fallback: 'Carrier name')),
      _field(_tracking, context.tr('trackingNumber', fallback: 'Tracking number')),
      _field(_trackingUrl,
          context.tr('trackingUrl', fallback: 'Tracking URL (https://…)'),
          keyboard: TextInputType.url),
    ];
  }

  List<Widget> _shiprocketBlock(ThemeData theme, dynamic l10n) {
    final dead = _num(_weight);
    final vol = VolumetricWeight.volumetric(
        _num(_length), _num(_breadth), _num(_height));
    final billed = VolumetricWeight.billed(dead, vol);
    final tertiary = theme.colorScheme.tertiary;

    return [
      _sectionLabel(theme, context.tr('packageLabel', fallback: 'Package')),
      const SizedBox(height: 10),
      if (widget.fulfillment.packages.isNotEmpty && !widget.isEditMode)
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: DropdownButtonFormField<ShipmentPackage?>(
            initialValue: _package,
            isExpanded: true,
            decoration: InputDecoration(
              isDense: true,
              filled: true,
              fillColor: theme.cardColor,
              contentPadding: const EdgeInsets.symmetric(
                  horizontal: 12, vertical: 14),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(
                  color:
                      theme.colorScheme.outlineVariant.withValues(alpha: 0.7),
                ),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(
                  color:
                      theme.colorScheme.outlineVariant.withValues(alpha: 0.7),
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
            items: [
              ...widget.fulfillment.packages.map(
                (p) => DropdownMenuItem<ShipmentPackage?>(
                  value: p,
                  child: Text(p.label,
                      style: _T.body.copyWith(color: tertiary)),
                ),
              ),
              DropdownMenuItem<ShipmentPackage?>(
                value: null,
                child: Text(context.tr('custom', fallback: 'Custom…'),
                    style: _T.body.copyWith(color: tertiary)),
              ),
            ],
            onChanged: (p) {
              setState(() {
                if (p == null) {
                  _package = null;
                } else {
                  _applyPackage(p);
                }
              });
            },
          ),
        ),
      Row(
        children: [
          Expanded(
              child: _field(_length, context.tr('lengthCm', fallback: 'Length (cm)'),
                  keyboard: _decimal)),
          const SizedBox(width: 10),
          Expanded(
              child: _field(_breadth, context.tr('breadthCm', fallback: 'Breadth (cm)'),
                  keyboard: _decimal)),
        ],
      ),
      Row(
        children: [
          Expanded(
              child: _field(_height, context.tr('heightCm', fallback: 'Height (cm)'),
                  keyboard: _decimal)),
          const SizedBox(width: 10),
          Expanded(
              child: _field(_weight, context.tr('weightKg', fallback: 'Weight (kg)'),
                  keyboard: _decimal)),
        ],
      ),
      const SizedBox(height: 4),
      Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: theme.colorScheme.outlineVariant.withValues(alpha: 0.18),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
          ),
        ),
        child: Column(
          children: [
            _kv(theme, context.tr('deadWeight', fallback: 'Dead weight'),
                '${dead.toStringAsFixed(3)} kg'),
            _kv(theme, context.tr('volumetricWeight', fallback: 'Volumetric weight'),
                '${vol.toStringAsFixed(3)} kg'),
            _kv(theme, context.tr('billedWeight', fallback: 'Billed weight'),
                '${billed.toStringAsFixed(3)} kg',
                bold: true),
          ],
        ),
      ),
    ];
  }

  static const TextInputType _decimal =
      TextInputType.numberWithOptions(decimal: true);

  Widget _kv(ThemeData theme, String k, String v, {bool bold = false}) {
    final tertiary = theme.colorScheme.tertiary;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            k,
            style: _T.meta.copyWith(color: tertiary.withValues(alpha: 0.7)),
          ),
          Text(
            v,
            style: _T.body.copyWith(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              color: tertiary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _field(TextEditingController c, String label,
      {TextInputType? keyboard}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: _SheetField(
        controller: c,
        label: label,
        keyboard: keyboard,
        onChanged: (_) => setState(() {}),
      ),
    );
  }
}

/// Carrier / tracking edit for a manual parcel.
class EditShipmentSheet extends StatefulWidget {
  final ParcelShipment shipment;

  const EditShipmentSheet({super.key, required this.shipment});

  static Future<Map<String, dynamic>?> show(
      BuildContext context, ParcelShipment shipment) {
    return showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (_) => EditShipmentSheet(shipment: shipment),
    );
  }

  @override
  State<EditShipmentSheet> createState() => _EditShipmentSheetState();
}

class _EditShipmentSheetState extends State<EditShipmentSheet> {
  late final TextEditingController _carrier;
  late final TextEditingController _tracking;
  late final TextEditingController _trackingUrl;

  @override
  void initState() {
    super.initState();
    _carrier = TextEditingController(text: widget.shipment.carrierName ?? '');
    _tracking =
        TextEditingController(text: widget.shipment.trackingNumber ?? '');
    _trackingUrl =
        TextEditingController(text: widget.shipment.trackingUrl ?? '');
  }

  @override
  void dispose() {
    _carrier.dispose();
    _tracking.dispose();
    _trackingUrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _SheetHandle(),
            _SheetHeader(
              title: context.tr('editFulfillment', fallback: 'Edit fulfillment'),
              subtitle:
                  'Update carrier and tracking info for this manual parcel.',
              onClose: () => Navigator.pop(context),
            ),
            Divider(
              height: 1,
              thickness: 1,
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  _SheetField(
                      controller: _carrier,
                      label: context.tr('carrierName', fallback: 'Carrier name')),
                  const SizedBox(height: 12),
                  _SheetField(
                      controller: _tracking,
                      label: context.tr('trackingNumber', fallback: 'Tracking number')),
                  const SizedBox(height: 12),
                  _SheetField(
                    controller: _trackingUrl,
                    label:
                        context.tr('trackingUrl', fallback: 'Tracking URL (https://…)'),
                    keyboard: TextInputType.url,
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 52,
                    child: ElevatedButton(
                      onPressed: () => Navigator.pop(context, {
                        'carrier_name': _carrier.text.trim().isEmpty
                            ? null
                            : _carrier.text.trim(),
                        'tracking_number': _tracking.text.trim().isEmpty
                            ? null
                            : _tracking.text.trim(),
                        'tracking_url': _trackingUrl.text.trim().isEmpty
                            ? null
                            : _trackingUrl.text.trim(),
                      }),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primaryColor,
                        foregroundColor: Colors.black,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                        textStyle:
                            _T.title.copyWith(fontWeight: FontWeight.w800),
                      ),
                      child: Text(context.tr('save', fallback: 'Save')),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// NDR: re-attempt delivery or send the parcel back.
class NdrActionSheet extends StatefulWidget {
  const NdrActionSheet({super.key});

  static Future<Map<String, String>?> show(BuildContext context) {
    return showModalBottomSheet<Map<String, String>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (_) => const NdrActionSheet(),
    );
  }

  @override
  State<NdrActionSheet> createState() => _NdrActionSheetState();
}

class _NdrActionSheetState extends State<NdrActionSheet> {
  final _comments = TextEditingController();
  String _action = 're-attempt';
  String? _error;

  @override
  void dispose() {
    _comments.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            _SheetHandle(),
            _SheetHeader(
              title: context.tr('ndrAction', fallback: 'Delivery attempt failed'),
              subtitle: 'Choose how to handle the failed delivery attempt.',
              onClose: () => Navigator.pop(context),
            ),
            Divider(
              height: 1,
              thickness: 1,
              color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 20, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  _NdrChoice(
                    icon: Icons.replay_rounded,
                    label: context.tr('reAttemptDelivery', fallback: 'Re-attempt delivery'),
                    selected: _action == 're-attempt',
                    onTap: () => setState(() => _action = 're-attempt'),
                  ),
                  const SizedBox(height: 8),
                  _NdrChoice(
                    icon: Icons.assignment_return_rounded,
                    label: context.tr('returnToOrigin', fallback: 'Return to origin'),
                    selected: _action == 'return',
                    onTap: () => setState(() => _action = 'return'),
                  ),
                  const SizedBox(height: 14),
                  _SheetField(
                    controller: _comments,
                    label: context.tr('comments', fallback: 'Comments (required)'),
                    maxLines: 3,
                    maxLength: 500,
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 4),
                    _ErrorLine(text: _error!),
                    const SizedBox(height: 8),
                  ],
                  SizedBox(
                    height: 52,
                    child: ElevatedButton(
                      onPressed: () {
                        final text = _comments.text.trim();
                        if (text.isEmpty) {
                          setState(() => _error =
                              context.tr('fieldRequired', fallback: 'Comments are required'));
                          return;
                        }
                        Navigator.pop(
                            context, {'action': _action, 'comments': text});
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primaryColor,
                        foregroundColor: Colors.black,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                        textStyle:
                            _T.title.copyWith(fontWeight: FontWeight.w800),
                      ),
                      child: Text(context.tr('submit', fallback: 'Submit')),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
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
  final int? maxLength;
  final ValueChanged<String>? onChanged;

  const _SheetField({
    required this.controller,
    required this.label,
    this.keyboard,
    this.maxLines = 1,
    this.maxLength,
    this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return TextField(
      controller: controller,
      keyboardType: keyboard,
      maxLines: maxLines,
      maxLength: maxLength,
      onChanged: onChanged,
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

class _NdrChoice extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  const _NdrChoice({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tertiary = theme.colorScheme.tertiary;
    return Material(
      color: selected
          ? AppColors.primaryColor.withValues(alpha: 0.10)
          : theme.cardColor,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding:
              const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: selected
                  ? AppColors.primaryColor
                  : theme.colorScheme.outlineVariant.withValues(alpha: 0.7),
              width: selected ? 1.3 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: selected
                      ? AppColors.primaryColor
                      : theme.colorScheme.outlineVariant
                          .withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(9),
                ),
                alignment: Alignment.center,
                child: Icon(
                  icon,
                  size: 18,
                  color: selected
                      ? Colors.black
                      : tertiary.withValues(alpha: 0.7),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: _T.subtitle.copyWith(
                    fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                    color: tertiary,
                  ),
                ),
              ),
              if (selected)
                const Icon(
                  Icons.check_circle_rounded,
                  size: 20,
                  color: AppColors.primaryColor,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
