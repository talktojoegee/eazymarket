import 'dart:async';
import 'package:hyper_commerce_seller/extensions/translation_extension.dart';
import 'dart:developer';
import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/utils/ui_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_loading_indicator.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';

class MapScreen extends StatefulWidget {
  final double? initialLatitude;
  final double? initialLongitude;

  const MapScreen({super.key, this.initialLatitude, this.initialLongitude});

  @override
  State<MapScreen> createState() => _MapScreenState();
}

class _MapScreenState extends State<MapScreen> {
  final Completer<GoogleMapController> _controller = Completer();
  final TextEditingController _searchController = TextEditingController();

  LatLng _selectedPosition = const LatLng(23.0225, 72.5714);
  String _selectedAddress = '';
  String _city = '';
  String _state = '';
  String _zipCode = '';
  String _country = '';
  String _landmark = '';
  String _sublocality = '';
  String _countryISO2 = '';

  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initializeLocation();
    });
  }

  Future<void> _initializeLocation() async {
    setState(() => _isLoading = true);

    try {
      final initFuture = _doInitializeLocation();
      await initFuture.timeout(
        const Duration(seconds: 60),
        onTimeout: () {
          throw TimeoutException('Location initialization timed out');
        },
      );
    } catch (e, stack) {
      debugPrint("Init location error: $e\n$stack");
      if (mounted) {
        ScaffoldMessenger.of(context).hideCurrentSnackBar();
        showCustomSnackbar(context: context, message: context.tr('errorLoadLocationWithDetail', positionalArgs: [e.toString()]), isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Future<void> _doInitializeLocation() async {

    if (widget.initialLatitude != null && widget.initialLongitude != null) {
      _selectedPosition = LatLng(widget.initialLatitude!, widget.initialLongitude!);
      await _getAddressFromLatLng(_selectedPosition);
    } else {
      bool hasPermission = await _checkAndRequestLocationPermission();
      if (hasPermission) {
        await _getCurrentLocation();
      } else {
        if (mounted) {

          ScaffoldMessenger.of(context).hideCurrentSnackBar();
          showCustomSnackbar(context: context, message: context.tr('locationPermissionDeniedMessage'), isError: true);
        }
      }
    }
  }

  Future<bool> _checkAndRequestLocationPermission() async {
    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      return false;
    }

    // Also check if location service is enabled
    if (!await Geolocator.isLocationServiceEnabled()) {
      // Optional: ask to enable
      await Geolocator.openLocationSettings();
      return await Geolocator.isLocationServiceEnabled();
    }

    return true;
  }

  Future<void> _getCurrentLocation() async {
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

      log('woiehrfdref ::::: position :: ${position?.latitude}');
      log('woiehrfdref ::::: position :: ${position?.longitude}');

      if (position != null) {
        _selectedPosition = LatLng(position.latitude, position.longitude);
        await _getAddressFromLatLng(_selectedPosition);

        final controller = await _controller.future;
        controller.animateCamera(CameraUpdate.newCameraPosition(CameraPosition(target: _selectedPosition, zoom: 15)));
      }
    } catch (e) {
      debugPrint('Error getting location: $e');
    }
  }

  Future<void> _getAddressFromLatLng(LatLng position) async {
    try {
      List<Placemark> placemarks = await placemarkFromCoordinates(position.latitude, position.longitude);

      if (placemarks.isNotEmpty) {
        final place = placemarks.first;
        setState(() {
          _selectedAddress = [
            place.street,
            place.subLocality,
            place.locality,
          ].where((e) => e != null && e.isNotEmpty).join(', ');
          _sublocality = place.subLocality ?? '';
          _city = place.locality ?? '';
          _state = place.administrativeArea ?? '';
          _zipCode = place.postalCode ?? '';
          _country = place.country ?? '';
          _landmark = place.subLocality ?? '';
          _countryISO2 = place.isoCountryCode ?? '';

        });
      }
    } catch (e) {
      debugPrint('Error getting address: $e');
    }
  }

  void _onMapTapped(LatLng position) async {
    setState(() => _selectedPosition = position);
    await _getAddressFromLatLng(position);
  }

  void _searchPlaces(String query) async {
    if (query.isEmpty) return;

    try {
      // Use geocoding to search for places
      List<Location> locations = await locationFromAddress(query);

      if (locations.isNotEmpty) {
        final location = locations.first;
        final newPosition = LatLng(location.latitude, location.longitude);

        setState(() => _selectedPosition = newPosition);
        await _getAddressFromLatLng(newPosition);

        final controller = await _controller.future;
        controller.animateCamera(CameraUpdate.newCameraPosition(CameraPosition(target: newPosition, zoom: 15)));
      }
    } catch (e) {
      if (!mounted) return;
      showCustomSnackbar(
        context: context,
        message: context.tr('couldNotFindLocationWithQuery', positionalArgs: [query]),
        isError: true,
      );
    } finally {}
  }

  void _confirmLocation() {
    Navigator.pop(context, {
      'sublocality': _sublocality,
      'latitude': _selectedPosition.latitude,
      'longitude': _selectedPosition.longitude,
      'address': _selectedAddress,
      'city': _city,
      'state': _state,
      'zipCode': _zipCode,
      'country': _country,
      'landmark': _landmark,
      'isoCountryCode': _countryISO2
    });
  }

  @override
  Widget build(BuildContext context) {
    final screenType = context.screenType;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bool hasText = _searchController.text.isNotEmpty;
    return CustomScaffold(
      title: context.tr('selectLocation'),
      showAppbar: true,
      centerTitle: true,
      onBackTap: () => Navigator.pop(context),
      body: _isLoading
          ? const Center(child: CustomLoadingIndicator())
          : Stack(
              children: [
                // Google Map
                GoogleMap(
                  initialCameraPosition: CameraPosition(target: _selectedPosition, zoom: 15),
                  onMapCreated: (controller) {
                    _controller.complete(controller);
                  },
                  onTap: _onMapTapped,
                  markers: {
                    Marker(
                      markerId: const MarkerId('selected-location'),
                      position: _selectedPosition,
                      icon: BitmapDescriptor.defaultMarkerWithHue(_getHueFromColor(AppColors.primaryColor)),
                    ),
                  },
                  myLocationEnabled: true,
                  myLocationButtonEnabled: false,
                  zoomControlsEnabled: false,
                  mapToolbarEnabled: false,
                ),

                // Search bar at top
                Positioned(
                  top: UIUtils.gapMD(screenType),
                  left: UIUtils.gapMD(screenType),
                  right: UIUtils.gapMD(screenType),
                  child: Container(
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF161B22) : Colors.white,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: TextField(
                      cursorColor: AppColors.primaryColor,
                      controller: _searchController,
                      decoration: InputDecoration(
                        hintText: context.tr('searchLocation'),
                        hintStyle: TextStyle(color: Colors.grey.shade500, fontSize: 14),
                        prefixIcon: Icon(Icons.search_rounded, color: AppColors.primaryColor, size: 22),
                        suffixIcon: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (_searchController.text.isNotEmpty)
                              IconButton(
                                icon: const Icon(Icons.close, size: 20),
                                onPressed: () {
                                  _searchController.clear();
                                  setState(() {});
                                },
                              ),
                          ],
                        ),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide.none,
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: hasText ? Theme.of(context).colorScheme.tertiary : const Color(0xFFC4C4C4),
                            width: 1,
                          ),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            width: 1,
                            color: hasText ? Theme.of(context).colorScheme.tertiary : const Color(0xFFC4C4C4),
                          ),
                        ),
                        disabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFC4C4C4), width: 1),
                        ),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
                      ),
                      onChanged: (value) => setState(() {}),
                      onSubmitted: _searchPlaces,
                    ),
                  ),
                ),

                // Action buttons floating on map
                Positioned(
                  right: UIUtils.gapMD(screenType),
                  top: 100, // Below search bar
                  child: Column(
                    children: [
                      _buildFloatingButton(
                        icon: Icons.my_location_rounded,
                        onPressed: _getCurrentLocation,
                        isDark: isDark,
                        color: AppColors.primaryColor,
                      ),
                    ],
                  ),
                ),
                Positioned(
                  bottom: 0,
                  left: 0,
                  right: 0,
                  child: Container(
                    padding: EdgeInsets.all(UIUtils.gapMD(screenType)),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF161B22) : Colors.white,
                      borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.1),
                          blurRadius: 10,
                          offset: const Offset(0, -5),
                        ),
                      ],
                    ),
                    child: SafeArea(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.location_on, color: AppColors.primaryColor, size: 24),
                              SizedBox(width: UIUtils.gapSM(screenType)),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      context.tr('selectedLocation'),
                                      style: TextStyle(fontSize: UIUtils.caption(screenType), color: Colors.grey),
                                    ),
                                    Text(
                                      _selectedAddress.isNotEmpty
                                          ? _selectedAddress
                                          : context.tr('tapOnMapToSelectLocation'),
                                      style: TextStyle(
                                        fontSize: UIUtils.body(screenType),
                                        fontWeight: UIUtils.semiBold,
                                      ),
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          if (_city.isNotEmpty || _state.isNotEmpty) ...[
                            SizedBox(height: UIUtils.gapSM(screenType)),
                            Text(
                              "$_city, $_state $_zipCode",
                              style: TextStyle(fontSize: UIUtils.caption(screenType), color: Colors.grey.shade600),
                            ),
                          ],
                          SizedBox(height: UIUtils.gapMD(screenType)),
                          SizedBox(
                            width: double.infinity,
                            child: ElevatedButton(
                              onPressed: _confirmLocation,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primaryColor,
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(100)),
                                elevation: 0,
                              ),
                              child: Text(
                                context.tr('confirmLocation'),
                                style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  double _getHueFromColor(Color color) {
    final hsv = HSVColor.fromColor(color);
    return hsv.hue;
  }

  Widget _buildFloatingButton({
    required IconData icon,
    required VoidCallback onPressed,
    required bool isDark,
    Color? color,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF161B22) : Colors.white,
        shape: BoxShape.circle,
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: IconButton(
        icon: Icon(icon, color: color ?? Colors.grey.shade600, size: 22),
        onPressed: onPressed,
      ),
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }
}
