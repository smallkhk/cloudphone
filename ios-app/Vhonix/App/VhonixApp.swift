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
        Appearance.apply()
        // Big image cache so phone screenshots don't re-download every time.
        URLCache.shared = URLCache(memoryCapacity: 64 * 1024 * 1024, diskCapacity: 512 * 1024 * 1024)
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environmentObject(app)
                .tint(Palette.accentText)
                .preferredColorScheme(.dark)
        }
    }
}
