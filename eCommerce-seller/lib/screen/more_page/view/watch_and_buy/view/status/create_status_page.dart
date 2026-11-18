// ignore_for_file: deprecated_member_use

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/config/hive_storage.dart';
import 'package:hyper_commerce_seller/utils/image_picker_utils.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_buttons.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_scaffold.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_snackbar.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_textfield.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_upload_area.dart';
import '../../model/eligible_product_model.dart';
import '../../repo/watch_and_buy_repo.dart';
import '../../widgets/eligible_products_sheet.dart';
import '../../widgets/watch_and_buy_media_viewer_page.dart';

class CreateStatusPage extends StatefulWidget {
  final int? profileId;

  const CreateStatusPage({
    super.key,
    this.profileId,
  });

  @override
  State<CreateStatusPage> createState() => _CreateStatusPageState();
}

class _CreateStatusPageState extends State<CreateStatusPage> {
  final _formKey = GlobalKey<FormState>();
  final TextEditingController _textController = TextEditingController();
  final WatchAndBuyRepo _repo = WatchAndBuyRepo();

  File? _mediaFile;
  String _mediaType = 'image'; // 'image' or 'video'
  List<EligibleProductModel> _selectedProducts = [];

  bool _isLoading = false;

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  void _removeMedia() {
    setState(() {
      _mediaFile = null;
    });
  }

  Future<void> _pickImage() async {
    final settings = HiveStorage.watchAndBuySettings;
    final path = await ImagePickerUtils.pickMedia(
      context,
      mediaType: MediaType.image,
      maxSizeMb: settings.maxUploadMb.toDouble(),
    );
    if (path != null) {
      setState(() {
        _mediaFile = File(path);
        _mediaType = 'image';
      });
    }
  }

  Future<void> _pickVideo() async {
    final settings = HiveStorage.watchAndBuySettings;
    final path = await ImagePickerUtils.pickMedia(
      context,
      mediaType: MediaType.video,
      maxSizeMb: settings.maxUploadMb.toDouble(),
      maxVideoDuration: Duration(seconds: settings.maxStatusDurationSeconds),
    );
    if (path != null) {
      setState(() {
        _mediaFile = File(path);
        _mediaType = 'video';
      });
    }
  }

  Future<void> _openProductSelector() async {
    final settings = HiveStorage.watchAndBuySettings;
    final result = await EligibleProductsSheet.show(
      context,
      maxSelections: settings.maxProductsPerStatus,
      requirePrimary: false,
      initialSelections: _selectedProducts,
    );

    if (result != null) {
      setState(() {
        _selectedProducts = result.selectedProducts;
      });
    }
  }

  Future<void> _submit() async {
    final text = _textController.text.trim();
    if (text.isEmpty && _mediaFile == null) {
      showCustomSnackbar(
        context: context,
        message: 'Please enter text or upload media for status',
        isError: true,
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final attachmentIds = _selectedProducts.map((p) => p.id).toList();

      await _repo.createStatus(
        text: text.isNotEmpty ? text : null,
        media: _mediaFile,
        source: 'seller',
        profileId: widget.profileId,
        attachmentIds: attachmentIds.isNotEmpty ? attachmentIds : null,
      );

      if (mounted) {
        showCustomSnackbar(context: context, message: 'Status posted successfully!');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        showCustomSnackbar(
          context: context,
          message: e.toString().replaceAll('Exception:', '').trim(),
          isError: true,
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return CustomScaffold(
      title: 'Add Status',
      showAppbar: true,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Notice banner
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.primaryColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.primaryColor.withOpacity(0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.timer_outlined, color: AppColors.primaryColor, size: 22),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Statuses remain visible to buyers for 24 hours before expiring.',
                        style: TextStyle(
                          fontSize: 12,
                          color: isDark ? Colors.white70 : Colors.black87,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // Status Text
              CustomTextField(
                controller: _textController,
                label: 'Status Message',
                hint: 'What do you want to share with your customers?',
                maxLines: 4,
                maxLength: 500,
              ),

              const SizedBox(height: 20),

              // Media Picker Option
              Text(
                'Media (Optional Image / Video)',
                style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: isDark ? Colors.white : Colors.black87),
              ),
              const SizedBox(height: 8),
              _buildMediaPreview(isDark),

              const SizedBox(height: 24),

              // Tagged Products Section
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Tagged Products (${_selectedProducts.length}/${HiveStorage.watchAndBuySettings.maxProductsPerStatus})',
                    style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: isDark ? Colors.white : Colors.black87),
                  ),
                  TextButton.icon(
                    onPressed: _openProductSelector,
                    icon: const Icon(Icons.add, size: 18),
                    label: Text(_selectedProducts.isEmpty ? 'Select Products' : 'Edit Tags'),
                  ),
                ],
              ),
              const SizedBox(height: 6),

              if (_selectedProducts.isNotEmpty)
                ListView.separated(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: _selectedProducts.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final item = _selectedProducts[index];

                    return Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF2A2A2A) : Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
                      ),
                      child: Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: item.productImage != null && item.productImage!.isNotEmpty
                                ? CustomNetworkImage(
                                    imageUrl: item.productImage!,
                                    width: 40,
                                    height: 40,
                                    fit: BoxFit.cover,
                                  )
                                : Container(
                                    width: 40,
                                    height: 40,
                                    color: Colors.grey.shade300,
                                    child: const Icon(Icons.image, size: 18),
                                  ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              item.productTitle,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, size: 18),
                            onPressed: () {
                              setState(() {
                                _selectedProducts.removeAt(index);
                              });
                            },
                          ),
                        ],
                      ),
                    );
                  },
                ),

              const SizedBox(height: 36),

              // Submit Button
              PrimaryButton(
                text: 'Post Status',
                isLoading: _isLoading,
                onPressed: _isLoading ? null : _submit,
              ),

              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMediaPreview(bool isDark) {
    if (_mediaFile == null) {
      return Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _pickImage,
              icon: const Icon(Icons.photo_library_outlined, size: 20),
              label: const Text('Add Photo'),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _pickVideo,
              icon: const Icon(Icons.videocam_outlined, size: 20),
              label: const Text('Add Video'),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],
      );
    }

    final isVideo = _mediaType == 'video';
    final fileName = _mediaFile!.path.split('/').last;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF242424) : Colors.grey.shade100,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Top: Media Thumbnail / Icon Box & Details
          Row(
            children: [
              if (isVideo)
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: Colors.black87,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(
                    Icons.videocam_outlined,
                    color: Colors.white,
                    size: 26,
                  ),
                )
              else
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: SizedBox(
                    width: 50,
                    height: 50,
                    child: Image.file(_mediaFile!, fit: BoxFit.cover),
                  ),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      fileName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                    const SizedBox(height: 4),
                    InkWell(
                      onTap: () {
                        if (isVideo) {
                          WatchAndBuyMediaViewerPage.showVideo(
                            context,
                            file: _mediaFile,
                            title: 'Watch Video Preview',
                          );
                        } else {
                          WatchAndBuyMediaViewerPage.showImage(
                            context,
                            file: _mediaFile,
                            title: 'Image Preview',
                          );
                        }
                      },
                      borderRadius: BorderRadius.circular(4),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 2),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              isVideo ? Icons.play_circle_fill : Icons.fullscreen,
                              size: 16,
                              color: AppColors.primaryColor,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              isVideo ? 'Watch Video Preview' : 'View Image Preview',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                                color: AppColors.primaryColor,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 10),
          Divider(height: 1, color: isDark ? Colors.white10 : Colors.black12),
          const SizedBox(height: 4),

          // Bottom: Action buttons below image and text
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton.icon(
                onPressed: isVideo ? _pickVideo : _pickImage,
                icon: const Icon(Icons.refresh, size: 16),
                label: Text(isVideo ? 'Change Video' : 'Change Photo', style: const TextStyle(fontSize: 12)),
              ),
              const SizedBox(width: 8),
              TextButton.icon(
                onPressed: _removeMedia,
                icon: const Icon(Icons.delete_outline, size: 16, color: Colors.redAccent),
                label: const Text('Remove', style: TextStyle(color: Colors.redAccent, fontSize: 12)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
