import Flutter
import UIKit
import FirebaseAuth

class SceneDelegate: FlutterSceneDelegate {
  override func scene(
    _ scene: UIScene,
    openURLContexts URLContexts: Set<UIOpenURLContext>
  ) {
    for context in URLContexts {
      _ = Auth.auth().canHandle(context.url)
    }
    super.scene(scene, openURLContexts: URLContexts)
  }
}
