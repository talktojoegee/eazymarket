import 'dart:ui';

class DropMenuController {
  static VoidCallback? _close;

  static void register(VoidCallback closeFn) {
    _close = closeFn;
  }

  static void closeAll() {
    _close?.call();
  }

  static void unregister() {
    _close = null;
  }
}