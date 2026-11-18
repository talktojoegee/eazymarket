// ignore_for_file: use_build_context_synchronously

import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:hyper_commerce_seller/config/app_text_styles.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/screen/auth/widgets/auth_text_field.dart';
import 'package:hyper_commerce_seller/utils/validator_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';

class BusinessAddressStep extends StatefulWidget {
  final GlobalKey<FormState> formKey;
  final TextEditingController addressController;
  final TextEditingController landmarkController;
  final TextEditingController cityController;
  final TextEditingController stateController;
  final TextEditingController countryController;
  final TextEditingController zipCodeController;

  final FocusNode addressNode;
  final FocusNode landmarkNode;
  final FocusNode cityNode;
  final FocusNode stateNode;
  final FocusNode countryNode;
  final FocusNode zipCodeNode;

  const BusinessAddressStep({
    super.key,
    required this.formKey,
    required this.addressController,
    required this.landmarkController,
    required this.cityController,
    required this.stateController,
    required this.countryController,
    required this.zipCodeController,
    required this.addressNode,
    required this.landmarkNode,
    required this.cityNode,
    required this.stateNode,
    required this.countryNode,
    required this.zipCodeNode,
  });

  @override
  State<BusinessAddressStep> createState() => _BusinessAddressStepState();
}

class _BusinessAddressStepState extends State<BusinessAddressStep> {
  bool _isLoading = false;

  Future<void> _useCurrentLocation() async {
    setState(() => _isLoading = true);
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          _showError(context.tr('locationPermissionDenied', fallback: 'Location permission denied'));
          return;
        }
      }
      if (permission == LocationPermission.deniedForever) {
        _showError(context.tr('locationPermissionPermanentlyDenied', fallback: 'Location permissions are permanently denied'));
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
      final placemarks = await placemarkFromCoordinates(position.latitude, position.longitude);

      if (!mounted) return;
      if (placemarks.isNotEmpty) {
        final place = placemarks.first;
        setState(() {
          widget.addressController.text = [place.street, place.subLocality, place.locality]
              .where((e) => e != null && e.isNotEmpty)
              .join(', ');
          widget.landmarkController.text = place.subLocality ?? '';
          widget.cityController.text = place.locality ?? '';
          widget.stateController.text = place.administrativeArea ?? '';
          widget.zipCodeController.text = place.postalCode ?? '';
          widget.countryController.text = place.country ?? '';
        });
      }
      showCustomSnackbar(
        context: context,
        message: context.tr('locationFetchedSuccess', fallback: 'Location fetched successfully!'),
      );
    } catch (e) {
      _showError(context.tr('failedToGetLocation', fallback: 'Failed to get location'));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _showError(String message) {
    if (mounted) showCustomSnackbar(context: context, message: message, isError: true);
  }

  @override
  Widget build(BuildContext context) {

    return Form(
      key: widget.formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: double.infinity,
            height: 48,
            child: OutlinedButton.icon(
              onPressed: _isLoading ? null : () => _useCurrentLocation(),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.primaryColor,
                side: const BorderSide(color: AppColors.primaryColor, width: 1.5),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
              ),
              icon: _isLoading
                  ? const SizedBox(width: 18, height: 18, child: CustomLoadingIndicator(strokeWidth: 2, color: AppColors.primaryColor))
                  : const Icon(Icons.my_location, size: 18),
              label: Text(
                _isLoading ? "Fetching…" : "Use my current location",
                style: AppTextStyles.body.copyWith(fontWeight: FontWeight.w700, color: AppColors.primaryColor),
              ),
            ),
          ),
          const SizedBox(height: 22),
          AuthTextField(
            label: context.tr('address', fallback: "Street address"),
            isRequired: true,
            hint: "e.g. 123, Shree Complex, Station Road",
            controller: widget.addressController,
            focusNode: widget.addressNode,
            maxLines: 2,
            textCapitalization: TextCapitalization.words,
            validator: (value) => ValidatorUtils.validateEmpty(context, value),
          ),
          const SizedBox(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: AuthTextField(
                  label: context.tr('city', fallback: "City"),
                  isRequired: true,
                  hint: "e.g. Bhuj",
                  controller: widget.cityController,
                  focusNode: widget.cityNode,
                  textCapitalization: TextCapitalization.words,
                  validator: (value) => ValidatorUtils.validateEmpty(context, value),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: AuthTextField(
                  label: context.tr('state', fallback: "State"),
                  isRequired: true,
                  hint: "e.g. Gujarat",
                  controller: widget.stateController,
                  focusNode: widget.stateNode,
                  textCapitalization: TextCapitalization.words,
                  validator: (value) => ValidatorUtils.validateEmpty(context, value),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: AuthTextField(
                  label: context.tr('zipCode', fallback: "Zip code"),
                  isRequired: true,
                  hint: "e.g. 370001",
                  controller: widget.zipCodeController,
                  focusNode: widget.zipCodeNode,
                  keyboardType: TextInputType.number,
                  validator: (value) => ValidatorUtils.validateEmpty(context, value),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: AuthTextField(
                  label: context.tr('country', fallback: "Country"),
                  isRequired: true,
                  hint: "e.g. India",
                  controller: widget.countryController,
                  focusNode: widget.countryNode,
                  textCapitalization: TextCapitalization.words,
                  validator: (value) => ValidatorUtils.validateEmpty(context, value),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          AuthTextField(
            label: context.tr('landmark', fallback: "Landmark"),
            isRequired: true,
            hint: "e.g. Near Bus Stand",
            controller: widget.landmarkController,
            focusNode: widget.landmarkNode,
            textCapitalization: TextCapitalization.words,
            validator: (value) => ValidatorUtils.validateEmpty(context, value),
          ),
        ],
      ),
    );
  }
}
