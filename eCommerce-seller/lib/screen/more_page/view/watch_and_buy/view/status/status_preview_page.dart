// ignore_for_file: deprecated_member_use

import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import 'package:hyper_commerce_seller/config/colors.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_alert_dialog.dart';
import 'package:hyper_commerce_seller/widgets/custom/custom_network_image.dart';
import '../../model/seller_status_model.dart';

class StatusPreviewPage extends StatefulWidget {
  final SellerStatusModel status;
  final VoidCallback? onDelete;

  const StatusPreviewPage({
    super.key,
    required this.status,
    this.onDelete,
  });

  @override
  State<StatusPreviewPage> createState() => _StatusPreviewPageState();
}

class _StatusPreviewPageState extends State<StatusPreviewPage> {
  VideoPlayerController? _videoController;
  bool _isPlaying = true;

  @override
  void initState() {
    super.initState();
    if (widget.status.isVideo && widget.status.mediaUrl != null) {
      _videoController = VideoPlayerController.networkUrl(Uri.parse(widget.status.mediaUrl!))
        ..initialize().then((_) {
          _videoController!.setLooping(true);
          _videoController!.play();
          if (mounted) setState(() {});
        });
    }
  }

  @override
  void dispose() {
    _videoController?.dispose();
    super.dispose();
  }

  void _togglePlayPause() {
    if (_videoController == null || !_videoController!.value.isInitialized) return;
    setState(() {
      if (_videoController!.value.isPlaying) {
        _videoController!.pause();
        _isPlaying = false;
      } else {
        _videoController!.play();
        _isPlaying = true;
      }
    });
  }

  void _confirmDelete() {
    showAppAlertDialog(
      context: context,
      title: 'Delete Status',
      message: 'Are you sure you want to remove this status post?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      isDestructive: true,
      onConfirm: () {
        widget.onDelete?.call();
        Navigator.pop(context); // pop status preview
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Background Content (Image / Video / Text)
          if (widget.status.isVideo)
            if (_videoController != null && _videoController!.value.isInitialized)
              GestureDetector(
                onTap: _togglePlayPause,
                child: Center(
                  child: AspectRatio(
                    aspectRatio: _videoController!.value.aspectRatio,
                    child: VideoPlayer(_videoController!),
                  ),
                ),
              )
            else
              const Center(child: CircularProgressIndicator(color: Colors.white))
          else if (widget.status.isImage && widget.status.mediaUrl != null)
            Center(
              child: CustomNetworkImage(
                imageUrl: widget.status.mediaUrl!,
                fit: BoxFit.contain,
              ),
            )
          else
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [Color(0xFF833AB4), Color(0xFFFD1D1D), Color(0xFFFCB045)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 28),
              alignment: Alignment.center,
              child: Text(
                widget.status.text ?? '',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  height: 1.4,
                ),
              ),
            ),

          // Play / Pause Icon overlay animation
          if (widget.status.isVideo && !_isPlaying && _videoController != null && _videoController!.value.isInitialized)
            Center(
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: const BoxDecoration(
                  color: Colors.black54,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.play_arrow, size: 48, color: Colors.white),
              ),
            ),

          // Top Header Bar with creator & expiration
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 8,
            right: 8,
            child: Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.white),
                  onPressed: () => Navigator.pop(context),
                ),
                const SizedBox(width: 4),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        widget.status.creator?.name ?? 'Seller Status',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      Text(
                        widget.status.timeRemainingFormatted,
                        style: const TextStyle(color: Colors.white70, fontSize: 11),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.delete_outline, color: Colors.white),
                  onPressed: _confirmDelete,
                ),
              ],
            ),
          ),

          // Bottom Caption & Tagged Products
          Positioned(
            bottom: MediaQuery.of(context).padding.bottom + 16,
            left: 16,
            right: 16,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (!widget.status.isTextOnly && widget.status.text != null && widget.status.text!.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      widget.status.text!,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                    ),
                  ),
                if (widget.status.products.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 52,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: widget.status.products.length,
                      separatorBuilder: (_, __) => const SizedBox(width: 8),
                      itemBuilder: (context, index) {
                        final product = widget.status.products[index];
                        return Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                          decoration: BoxDecoration(
                            color: Colors.black87,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.white24),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(4),
                                child: product.productImage != null && product.productImage!.isNotEmpty
                                    ? CustomNetworkImage(
                                        imageUrl: product.productImage!,
                                        width: 36,
                                        height: 36,
                                        fit: BoxFit.cover,
                                      )
                                    : Container(
                                        width: 36,
                                        height: 36,
                                        color: Colors.grey.shade700,
                                        child: const Icon(Icons.image, size: 16, color: Colors.white70),
                                      ),
                              ),
                              const SizedBox(width: 8),
                              ConstrainedBox(
                                constraints: const BoxConstraints(maxWidth: 140),
                                child: Text(
                                  product.productTitle,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
