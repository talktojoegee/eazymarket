import Flutter
import UIKit
import GoogleMaps
import Firebase
import FirebaseAuth
import UserNotifications

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  static var pendingAction: [String: Any]?
  private var actionChannel: FlutterMethodChannel?

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {

    if #available(iOS 10.0, *) {
      UNUserNotificationCenter.current().delegate = self as? UNUserNotificationCenterDelegate

      let rateAction = UNNotificationAction(
        identifier: "rate_now",
        title: "Rate now",
        options: [.foreground]
      )
      let deliveredCategory = UNNotificationCategory(
        identifier: "order_delivered_category",
        actions: [rateAction],
        intentIdentifiers: [],
        options: []
      )
      UNUserNotificationCenter.current().setNotificationCategories([deliveredCategory])
    }

    application.registerForRemoteNotifications()

    GMSServices.provideAPIKey("Your_Map_Key")

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)

    actionChannel = FlutterMethodChannel(
      name: "com.hypercommerce.customer/notification_actions",
      binaryMessenger: engineBridge.applicationRegistrar.messenger()
    )
    actionChannel?.setMethodCallHandler { (call: FlutterMethodCall, result: @escaping FlutterResult) in
      if call.method == "getPendingAction" {
        result(AppDelegate.pendingAction)
        AppDelegate.pendingAction = nil
      } else {
        result(FlutterMethodNotImplemented)
      }
    }
  }

  override func userNotificationCenter(
    _ center: UNUserNotificationCenter,
    didReceive response: UNNotificationResponse,
    withCompletionHandler completionHandler: @escaping () -> Void
  ) {
    if response.actionIdentifier == "rate_now" {
      let userInfo = response.notification.request.content.userInfo
      var dataDict: [String: Any] = [:]
      for (key, value) in userInfo {
        if let keyStr = key as? String {
          dataDict[keyStr] = value
        }
      }
      let actionPayload: [String: Any] = [
        "actionId": "rate_now",
        "data": dataDict
      ]
      AppDelegate.pendingAction = actionPayload
      actionChannel?.invokeMethod("onAction", arguments: actionPayload)
    }

    super.userNotificationCenter(center, didReceive: response, withCompletionHandler: completionHandler)
  }

  override func application(
    _ app: UIApplication,
    open url: URL,
    options: [UIApplication.OpenURLOptionsKey: Any] = [:]
  ) -> Bool {
    if Auth.auth().canHandle(url) {
      return true
    }
    return super.application(app, open: url, options: options)
  }
}