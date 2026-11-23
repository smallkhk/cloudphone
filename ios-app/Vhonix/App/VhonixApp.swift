import SwiftUI
import AVFoundation

@main
struct VhonixApp: App {
    @StateObject private var app = AppState()

    init() {
        // Background audio session: keeps a live screen running when the app
        // goes to the background, without stopping music from other apps.
        try? AVAudioSession.sharedInstance().setCategory(.playback, mode: .default, options: [.mixWithOthers])
        try? AVAudioSession.sharedInstance().setActive(true)
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(app)
                .tint(Theme.accent)
        }
    }
}
