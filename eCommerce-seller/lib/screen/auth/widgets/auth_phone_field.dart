import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl_phone_field/countries.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/constant.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_text_field.dart';

/// Value emitted by [AuthPhoneField].
class PhoneFieldData {
  /// ISO 3166-1 alpha-2 country code, e.g. "IN".
  final String iso;

  /// Dial code including the leading plus, e.g. "+91".
  final String dialCode;

  /// National number, digits only, no country code, e.g. "9876543210".
  final String national;

  /// Full E.164 number, e.g. "+919876543210".
  final String complete;

  const PhoneFieldData({
    required this.iso,
    required this.dialCode,
    required this.national,
    required this.complete,
  });

  bool get isEmpty => national.isEmpty;
}

/// Phone number field matching the customer app: a separate country-picker
/// button (flag + dial code) beside the number field, with a searchable
/// bottom-sheet country list. Styled to match [AuthTextField].
class AuthPhoneField extends StatefulWidget {
  final String initialIso;
  final TextEditingController? controller;
  final FocusNode? focusNode;
  final ValueChanged<PhoneFieldData>? onChanged;
  final String? hint;
  final String? label;
  final bool isRequired;
  final TextInputAction textInputAction;
  final bool enabled;
  final String? Function(String?)? validator;
  final AutovalidateMode? autovalidateMode;

  const AuthPhoneField({
    super.key,
    this.initialIso = 'IN',
    this.controller,
    this.focusNode,
    this.onChanged,
    this.hint,
    this.label,
    this.isRequired = false,
    this.textInputAction = TextInputAction.done,
    this.enabled = true,
    this.validator,
    this.autovalidateMode,
  });

  @override
  State<AuthPhoneField> createState() => _AuthPhoneFieldState();
}

class _AuthPhoneFieldState extends State<AuthPhoneField> {
  late TextEditingController _controller;
  late Country _country;
  bool _ownsController = false;

  @override
  void initState() {
    super.initState();
    _controller = widget.controller ?? TextEditingController();
    _ownsController = widget.controller == null;
    _country = _resolveCountry(widget.initialIso);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _emit();
    });
  }

  @override
  void dispose() {
    if (_ownsController) _controller.dispose();
    super.dispose();
  }

  Country _resolveCountry(String iso) {
    final upper = iso.toUpperCase();
    return countries.firstWhere(
      (c) => c.code.toUpperCase() == upper,
      orElse: () => countries.firstWhere(
        (c) => c.code.toUpperCase() == 'IN',
        orElse: () => countries.first,
      ),
    );
  }

  String _dial(Country c) => '+${c.dialCode}';

  String _flag(String iso2) {
    if (iso2.length != 2) return '🏳';
    const base = 0x1F1E6; // regional-indicator "A"
    final upper = iso2.toUpperCase();
    return String.fromCharCodes(upper.codeUnits.map((c) => base + (c - 65)));
  }

  void _emit() {
    final national = _controller.text.replaceAll(RegExp(r'[^\d]'), '');
    widget.onChanged?.call(PhoneFieldData(
      iso: _country.code,
      dialCode: _dial(_country),
      national: national,
      complete: '${_dial(_country)}$national',
    ));
  }

  Future<void> _openPicker() async {
    FocusManager.instance.primaryFocus?.unfocus();
    final selected = await showModalBottomSheet<Country>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CountryPickerSheet(current: _country, flagOf: _flag),
    );
    if (selected != null && mounted) {
      setState(() => _country = selected);
      _emit();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final tertiary = theme.colorScheme.tertiary;
    final fillColor = isDark ? AppColors.mainDarkContainerBgColor : Colors.white;
    final borderColor = isDark ? AppColors.darkOutline : Colors.grey.shade200;

    final Widget field = IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          GestureDetector(
            onTap: widget.enabled ? _openPicker : null,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: fillColor,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: borderColor, width: 1.2),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_flag(_country.code), style: const TextStyle(fontSize: 20)),
                  const SizedBox(width: 6),
                  Text(
                    _dial(_country),
                    style: TextStyle(
                      fontFamily: AppConstants.fontFamily,
                      fontWeight: FontWeight.w600,
                      fontSize: 15,
                      color: tertiary,
                    ),
                  ),
                  Icon(Icons.arrow_drop_down, color: Colors.grey.shade500, size: 20),
                ],
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: AuthTextField(
              controller: _controller,
              focusNode: widget.focusNode,
              enabled: widget.enabled,
              hint: widget.hint ?? "Mobile number",
              keyboardType: TextInputType.phone,
              textInputAction: widget.textInputAction,
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[\d\s\-]'))],
              onChanged: (_) => _emit(),
              validator: widget.validator,
              autovalidateMode: widget.autovalidateMode,
            ),
          ),
        ],
      ),
    );

    if (widget.label == null) return field;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        RichText(
          text: TextSpan(
            text: widget.label,
            style: TextStyle(
              fontFamily: AppConstants.fontFamily,
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: isDark ? Colors.grey.shade300 : const Color(0xFF5A6270),
            ),
            children: [
              if (widget.isRequired)
                const TextSpan(
                  text: ' *',
                  style: TextStyle(color: Color(0xFFE0483A), fontWeight: FontWeight.w700),
                ),
            ],
          ),
        ),
        const SizedBox(height: 7),
        field,
      ],
    );
  }
}

class _CountryPickerSheet extends StatefulWidget {
  final Country current;
  final String Function(String iso2) flagOf;

  const _CountryPickerSheet({required this.current, required this.flagOf});

  @override
  State<_CountryPickerSheet> createState() => _CountryPickerSheetState();
}

class _CountryPickerSheetState extends State<_CountryPickerSheet> {
  final _searchController = TextEditingController();
  late List<Country> _filtered;

  @override
  void initState() {
    super.initState();
    _filtered = List<Country>.from(countries)..sort((a, b) => a.name.compareTo(b.name));
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onSearch(String q) {
    final query = q.trim().toLowerCase();
    final digits = query.replaceAll('+', '');
    setState(() {
      _filtered = countries.where((c) {
        if (query.isEmpty) return true;
        return c.name.toLowerCase().contains(query) ||
            c.code.toLowerCase().contains(query) ||
            (digits.isNotEmpty && c.dialCode.contains(digits));
      }).toList()
        ..sort((a, b) => a.name.compareTo(b.name));
    });
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(16),
          topRight: Radius.circular(16),
        ),
      ),
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.85),
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 10),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: scheme.outlineVariant,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: AuthTextField(
              controller: _searchController,
              onChanged: _onSearch,
              hint: "Search country",
              prefixIcon: Icons.search,
            ),
          ),
          Flexible(
            child: _filtered.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text("No countries found", style: TextStyle(color: Colors.grey.shade600)),
                  )
                : ListView.builder(
                    itemCount: _filtered.length,
                    itemBuilder: (context, i) {
                      final c = _filtered[i];
                      final selected = c.code == widget.current.code;
                      return Material(
                        color: Colors.transparent,
                        child: ListTile(
                          leading: Text(widget.flagOf(c.code), style: const TextStyle(fontSize: 22)),
                          title: Text(
                            c.name,
                            style: TextStyle(
                              fontFamily: AppConstants.fontFamily,
                              fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
                              color: scheme.tertiary,
                            ),
                          ),
                          trailing: Text(
                            '+${c.dialCode}',
                            style: TextStyle(color: Colors.grey.shade600, fontWeight: FontWeight.w600),
                          ),
                          onTap: () => Navigator.of(context).pop(c),
                        ),
                      );
                    },
                  ),
          ),
          SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
        ],
      ),
    );
  }
}
