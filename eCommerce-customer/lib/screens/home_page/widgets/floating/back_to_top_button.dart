

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:hugeicons/hugeicons.dart';
import 'package:hyper_commerce/config/helper.dart';
import 'package:hyper_commerce/utils/extensions/translation_extension.dart';

class BackToTopButton extends StatelessWidget {
  final ValueListenable<bool> isVisibleListenable;
  final VoidCallback onTap;
  final double headerBottom;

  const BackToTopButton({
    super.key,
    required this.isVisibleListenable,
    required this.onTap,
    required this.headerBottom,
  });

  static const double _overlap = 8.0;

  @override
  Widget build(BuildContext context) {
    if (isTablet(context)) {
      return _buildTabletFab(context);
    }
    return _buildMobileButton(context);
  }

  Widget _buildTabletFab(BuildContext context) {
    final bool darkMode = isDarkMode(context);

    return PositionedDirectional(
      end: 24,
      bottom: 20,
      child: ValueListenableBuilder<bool>(
        valueListenable: isVisibleListenable,
        builder: (context, showBackToTop, _) {
          return AnimatedScale(
            duration: Duration(milliseconds: showBackToTop ? 240 : 180),
            curve: showBackToTop ? Curves.easeOutBack : Curves.easeInCubic,
            scale: showBackToTop ? 1.0 : 0.0,
            child: IgnorePointer(
              ignoring: !showBackToTop,
              child: AnimatedOpacity(
                duration: const Duration(milliseconds: 180),
                opacity: showBackToTop ? 1.0 : 0.0,
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: onTap,
                  child: Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: darkMode
                          ? Colors.white.withValues(alpha: 0.94)
                          : Colors.black.withValues(alpha: 0.90),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(
                            alpha: darkMode ? 0.35 : 0.25,
                          ),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    alignment: Alignment.center,
                    child: HugeIcon(
                      icon: HugeIcons.strokeRoundedCircleArrowUp02,
                      size: 22,
                      color: darkMode ? Colors.black : Colors.white,
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildMobileButton(BuildContext context) {
    final bool darkMode = isDarkMode(context);

    return Positioned(
      top: headerBottom - _overlap,
      left: 0,
      right: 0,
      bottom: 0,
      child: ValueListenableBuilder<bool>(
        valueListenable: isVisibleListenable,
        builder: (context, showBackToTop, _) {
          return ClipRect(
            child: AnimatedAlign(
              duration: Duration(
                milliseconds: showBackToTop ? 240 : 200,
              ),
              curve: showBackToTop
                  ? Curves.easeOutCubic
                  : Curves.easeInCubic,
              alignment: Alignment(
                0,
                showBackToTop ? -1.0 : -2.5,
              ),
              child: IgnorePointer(
                ignoring: !showBackToTop,
                child: Container(
                  color: Colors.transparent,
                  padding: const EdgeInsets.only(top: 8),
                  child: AnimatedOpacity(
                    duration: const Duration(milliseconds: 160),
                    opacity: showBackToTop ? 1.0 : 0.0,
                    child: GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTap: onTap,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: darkMode
                              ? Colors.white.withValues(alpha: 0.94)
                              : Colors.black.withValues(alpha: 0.90),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(
                                alpha: darkMode ? 0.28 : 0.18,
                              ),
                              blurRadius: 10,
                              offset: const Offset(0, 3),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              context.tr('backToTop'),
                              style: TextStyle(
                                color:
                                    darkMode ? Colors.black : Colors.white,
                                fontSize: 12,
                                height: 1,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(width: 5),
                            HugeIcon(
                              icon:
                                  HugeIcons.strokeRoundedCircleArrowUp02,
                              size: 17,
                              color:
                                  darkMode ? Colors.black : Colors.white,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}