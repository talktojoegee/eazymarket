import 'dart:async';
import 'dart:isolate';

class AppIsolate {
  AppIsolate._();

  static Future<R> run<R>(FutureOr<R> Function() computation) async {
    return await Isolate.run(computation);
  }

  static Future<T> parse<T>(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJson,
  ) async {
    return await Isolate.run(() => fromJson(json));
  }

  static Future<List<T>> parseList<T>(
    List<dynamic> list,
    T Function(Map<String, dynamic>) fromJson,
  ) async {
    return await Isolate.run(() {
      final result = <T>[];
      for (final item in list) {
        if (item is Map<String, dynamic>) {
          result.add(fromJson(item));
        } else if (item is Map) {
          result.add(fromJson(Map<String, dynamic>.from(item)));
        }
      }
      return result;
    });
  }
}
