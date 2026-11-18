import UserNotifications

/// Notification Service Extension that downloads the remote image referenced
/// in the FCM payload and attaches it to the notification so iOS can render
/// the rich (image) notification in background / terminated state.
///
/// The backend must send the push with `mutable-content: 1` (FCM auto-injects
/// this when `notification.image` or `apns.fcm_options.image` is present) and
/// either:
///   - `apns.fcm_options.image = "<url>"`, or
///   - `data.image = "<url>"` (custom key used elsewhere in this app).
class NotificationService: UNNotificationServiceExtension {

    var contentHandler: ((UNNotificationContent) -> Void)?
    var bestAttemptContent: UNMutableNotificationContent?

    override func didReceive(
        _ request: UNNotificationRequest,
        withContentHandler contentHandler: @escaping (UNNotificationContent) -> Void
    ) {
        self.contentHandler = contentHandler
        bestAttemptContent = request.content.mutableCopy() as? UNMutableNotificationContent
        guard let bestAttempt = bestAttemptContent else {
            contentHandler(request.content)
            return
        }

        let userInfo = request.content.userInfo

        // Check if this is an order delivered notification
        var metadataDict: [String: Any] = [:]
        if let meta = userInfo["metadata"] as? [String: Any] {
            metadataDict = meta
        } else if let metaStr = userInfo["metadata"] as? String,
                  let data = metaStr.data(using: .utf8),
                  let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
            metadataDict = json
        }

        var hasRateNowInActions = false
        if let actions = metadataDict["actions"] as? [[String: Any]] {
            for a in actions {
                if a["action"] as? String == "rate_now" || (a["title"] as? String)?.lowercased() == "rate now" {
                    hasRateNowInActions = true
                    break
                }
            }
        }

        let rawType = (userInfo["type"] as? String)?.lowercased() ?? ""
        let metaType = (metadataDict["type"] as? String)?.lowercased() ?? ""
        let titleLower = bestAttempt.title.lowercased()
        let bodyLower = bestAttempt.body.lowercased()
        let fulfillmentStatus = ((userInfo["fulfillment_status"] ?? metadataDict["fulfillment_status"]) as? String)?.lowercased() ?? ""
        let customerStatus = ((userInfo["customer_status"] ?? metadataDict["status"] ?? metadataDict["customer_status"]) as? String)?.lowercased() ?? ""

        let isDelivered = hasRateNowInActions ||
            rawType == "order_delivered" ||
            rawType == "delivered" ||
            rawType == "delivery" ||
            metaType == "delivery" ||
            customerStatus == "delivered" ||
            fulfillmentStatus == "fulfilled" ||
            titleLower.contains("delivered") ||
            bodyLower.contains("delivered")

        let rawReviewGiven = userInfo["is_user_review_given"] ?? metadataDict["is_user_review_given"]
        let isUserReviewGiven = (rawReviewGiven as? Bool == true) ||
            (rawReviewGiven as? String == "true") ||
            (rawReviewGiven as? String == "1")

        if isDelivered && !isUserReviewGiven {
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
            bestAttempt.categoryIdentifier = "order_delivered_category"
        }

        // Try common locations for the image URL, in order of FCM convention.
        var imageUrlString: String?
        if let fcmOptions = userInfo["fcm_options"] as? [String: Any],
           let image = fcmOptions["image"] as? String {
            imageUrlString = image
        } else if let image = userInfo["image"] as? String {
            imageUrlString = image
        } else if let aps = userInfo["aps"] as? [String: Any],
                  let fcmOptions = aps["fcm_options"] as? [String: Any],
                  let image = fcmOptions["image"] as? String {
            imageUrlString = image
        }

        guard let urlString = imageUrlString,
              let url = URL(string: urlString) else {
            contentHandler(bestAttempt)
            return
        }

        URLSession.shared.downloadTask(with: url) { [weak self] tempURL, _, _ in
            defer {
                if let self = self, let bestAttempt = self.bestAttemptContent {
                    contentHandler(bestAttempt)
                }
            }

            guard let tempURL = tempURL else { return }

            // Preserve the file extension so iOS recognises the attachment type.
            let fileExtension = url.pathExtension.isEmpty ? "jpg" : url.pathExtension
            let destination = URL(fileURLWithPath: NSTemporaryDirectory())
                .appendingPathComponent(UUID().uuidString)
                .appendingPathExtension(fileExtension)

            do {
                try FileManager.default.moveItem(at: tempURL, to: destination)
                let attachment = try UNNotificationAttachment(
                    identifier: "image",
                    url: destination,
                    options: nil
                )
                self?.bestAttemptContent?.attachments = [attachment]
            } catch {
                // Silently fall back to a text-only notification if anything fails.
            }
        }.resume()
    }

    /// Called by iOS just before the extension is killed. We must hand back
    /// whatever we have so far (image may not have finished downloading).
    override func serviceExtensionTimeWillExpire() {
        if let contentHandler = contentHandler,
           let bestAttemptContent = bestAttemptContent {
            contentHandler(bestAttemptContent)
        }
    }
}
