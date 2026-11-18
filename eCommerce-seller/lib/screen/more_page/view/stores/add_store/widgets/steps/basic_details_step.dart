import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/model/market_model.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/markets/repo/markets_repo.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/bloc/add_store_bloc.dart';
import 'package:hyper_commerce_seller/screen/more_page/view/stores/add_store/bloc/add_store_state.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_phone_field.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

class BasicDetailsStep extends StatefulWidget {
  final GlobalKey<FormState> formKey;
  const BasicDetailsStep({super.key, required this.formKey});

  @override
  State<BasicDetailsStep> createState() => _BasicDetailsStepState();
}

class _BasicDetailsStepState extends State<BasicDetailsStep> {
  final TextEditingController _storeNameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _taxNameController = TextEditingController();
  final TextEditingController _taxNumberController = TextEditingController();
  final FocusNode storeNameNode = FocusNode();
  final FocusNode emailNode = FocusNode();
  final FocusNode phoneNode = FocusNode();

  final MarketsRepo _marketsRepo = MarketsRepo();
  List<Market> _markets = [];
  final Set<int> _selectedMarketIds = {};
  bool _loadingMarkets = true;

  bool _isSyncing = false;

  @override
  void dispose() {
    storeNameNode.dispose();
    emailNode.dispose();
    phoneNode.dispose();
    _storeNameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _taxNameController.dispose();
    _taxNumberController.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    final formData = context.read<AddStoreBloc>().state.formData;
    _syncWithFormData(formData);
    _loadMarkets();

    _storeNameController.addListener(_updateBloc);
    _emailController.addListener(_updateBloc);
    _phoneController.addListener(_updateBloc);
    _taxNameController.addListener(_updateBloc);
    _taxNumberController.addListener(_updateBloc);
  }

  Future<void> _loadMarkets() async {
    try {
      final res = await _marketsRepo.searchMarkets(limit: 50);
      final parsed = MarketsResponse.fromJson(res);
      if (mounted) setState(() { _markets = parsed.markets; _loadingMarkets = false; });
    } catch (_) {
      if (mounted) setState(() => _loadingMarkets = false);
    }
  }

  void _syncWithFormData(Map<String, dynamic> data) {
    _isSyncing = true;
    if (data['name'] != _storeNameController.text) {
      _storeNameController.text = data['name'] ?? '';
    }
    if (data['contact_email'] != _emailController.text) {
      _emailController.text = data['contact_email'] ?? '';
    }
    if (data['contact_number'] != _phoneController.text) {
      _phoneController.text = data['contact_number'] ?? '';
    }
    if (data['tax_name'] != _taxNameController.text) {
      _taxNameController.text = data['tax_name'] ?? '';
    }
    if (data['tax_number'] != _taxNumberController.text) {
      _taxNumberController.text = data['tax_number'] ?? '';
    }
    final ids = data['market_ids'];
    if (ids is List) {
      _selectedMarketIds
        ..clear()
        ..addAll(ids.map((e) => e is int ? e : int.tryParse(e.toString()) ?? 0).where((e) => e > 0));
    }
    _isSyncing = false;
  }

  void _updateBloc() {
    if (_isSyncing) return;
    context.read<AddStoreBloc>().add(
      UpdateStoreField({
        'name': _storeNameController.text,
        'contact_email': _emailController.text,
        'contact_number': _phoneController.text,
        'mobile': _phoneController.text,
        'tax_name': _taxNameController.text,
        'tax_number': _taxNumberController.text,
      }),
    );
  }

  void _toggleMarket(int id, bool selected) {
    setState(() {
      selected ? _selectedMarketIds.add(id) : _selectedMarketIds.remove(id);
    });
    context.read<AddStoreBloc>().add(
      UpdateStoreField({'market_ids': _selectedMarketIds.toList()}),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<AddStoreBloc, AddStoreState>(
      listenWhen: (previous, current) => previous.formData != current.formData,
      listener: (context, state) => _syncWithFormData(state.formData),
      child: Form(
        key: widget.formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CustomTextField(
              label: context.tr('storeName', fallback: "Store Name"),
              isRequired: true,
              hint: context.tr('enterNickName', fallback: "Enter Nickname"),
              controller: _storeNameController,
              validator: (value) => ValidatorUtils.validateEmpty(context, value),
              focusNode: storeNameNode,
            ),
            const SizedBox(height: 20),
            CustomTextField(
              label: context.tr('contactEmail', fallback: "Contact Email"),
              isRequired: true,
              hint: context.tr('enterEmail', fallback: "Enter Email"),
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              validator: (value) => ValidatorUtils.validateEmail(context, value),
              focusNode: emailNode,
            ),
            const SizedBox(height: 20),
            CustomPhoneField(
              label: context.tr('mobileNumber', fallback: "Mobile Number"),
              isRequired: true,
              controller: _phoneController,
              focusNode: phoneNode,
              autovalidateMode: AutovalidateMode.onUserInteraction,
            ),
            const SizedBox(height: 20),
            CustomTextField(
              label: context.tr('taxName', fallback: "Tax Name"),
              isRequired: true,
              hint: "GST",
              controller: _taxNameController,
              validator: (value) => ValidatorUtils.validateEmpty(context, value),
            ),
            const SizedBox(height: 20),
            CustomTextField(
              label: context.tr('taxNumber', fallback: "Tax Number"),
              isRequired: true,
              hint: "GST00012345",
              controller: _taxNumberController,
              validator: (value) => ValidatorUtils.validateEmpty(context, value),
            ),
            const SizedBox(height: 20),
            Text(
              context.tr('marketsLabel', fallback: 'Markets'),
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
            ),
            const SizedBox(height: 2),
            Text(
              context.tr('selectMarkets', fallback: 'Select markets you sell in'),
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 8),
            if (_loadingMarkets)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 8),
                child: SizedBox(height: 20, width: 20, child: CustomLoadingIndicator(strokeWidth: 2)),
              )
            else
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: _markets.map((m) {
                  final selected = _selectedMarketIds.contains(m.id);
                  return FilterChip(
                    label: Text('${m.code} — ${m.name}'),
                    selected: selected,
                    onSelected: (v) => _toggleMarket(m.id, v),
                  );
                }).toList(),
              ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }
}
