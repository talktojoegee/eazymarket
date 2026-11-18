package your.package.name

import android.content.Intent
import android.os.Bundle
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterFragmentActivity() {
    companion object {
        var pendingAction: Map<String, Any?>? = null
    }

    private var actionChannel: MethodChannel? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        actionChannel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "com.hypercommerce.customer/notification_actions")
        actionChannel?.setMethodCallHandler { call, result ->
            if (call.method == "getPendingAction") {
                result.success(pendingAction)
                pendingAction = null
            } else {
                result.notImplemented()
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        handleIntent(intent)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleIntent(intent)
    }

    private fun handleIntent(intent: Intent?) {
        if (intent == null) return
        val action = intent.action
        val actionIdExtra = intent.getStringExtra("action_id") ?: intent.getStringExtra("action")
        val effectiveActionId = if (action == "rate_now") "rate_now" else actionIdExtra

        if (effectiveActionId == "rate_now") {
            val dataMap = mutableMapOf<String, Any?>()
            val extras = intent.extras
            if (extras != null) {
                for (key in extras.keySet()) {
                    dataMap[key] = extras.get(key)
                }
            }
            val payload = mapOf(
                "actionId" to "rate_now",
                "data" to dataMap
            )
            pendingAction = payload
            actionChannel?.invokeMethod("onAction", payload)
        }
    }
}
