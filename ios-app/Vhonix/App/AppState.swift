import SwiftUI

enum MainTab: Hashable {
    case devices, shop, wallet, more
}

struct Banner: Identifiable, Equatable {
    let id = UUID()
    let text: String
    let isError: Bool
}

@MainActor
final class AppState: ObservableObject {
    enum Phase { case signedOut, signedIn }

    @Published var phase: Phase
    @Published var user: User?
    @Published var tab: MainTab = .devices
    @Published var banner: Banner?

    private var bannerTask: Task<Void, Never>?

    init() {
        let api = APIClient.shared

        phase = api.token == nil ? .signedOut : .signedIn

        api.onUnauthorized = { [weak self] in
            self?.forgetSignIn()
        }
    }

    // MARK: Sign in

    func didSignIn(token: String, user: User?) {
        APIClient.shared.token = token
        self.user = user
        tab = .devices
        phase = .signedIn
    }

    func refreshUser() async {
        if let me: MeResponse = try? await APIClient.shared.get("me") {
            user = me.user
        }
    }

    func signOut() async {
        if APIClient.shared.token != nil {
            _ = try? await APIClient.shared.action("auth/logout")
        }
        forgetSignIn()
    }

    private func forgetSignIn() {
        APIClient.shared.token = nil
        user = nil
        if phase == .signedIn { phase = .signedOut }
    }

    // MARK: Messages

    func show(_ text: String, error: Bool = false) {
        bannerTask?.cancel()
        error ? Haptics.error() : Haptics.success()
        withAnimation(.spring(response: 0.45, dampingFraction: 0.8)) { banner = Banner(text: text, isError: error) }

        bannerTask = Task { [weak self] in
            try? await Task.sleep(nanoseconds: UInt64(error ? 6 : 4) * 1_000_000_000)
            guard !Task.isCancelled else { return }
            withAnimation(.easeInOut(duration: 0.3)) { self?.banner = nil }
        }
    }

    func show(_ error: Error) {
        show((error as? APIError)?.message ?? error.localizedDescription, error: true)
    }

    /// Runs one of the website's actions and shows its message. Returns the result on success.
    @discardableResult
    func act(_ path: String, method: String = "POST", body: [String: Any] = [:]) async -> ActionResult? {
        do {
            let result = try await APIClient.shared.action(path, method: method, body: body)
            if let message = result.message { show(message, error: !result.ok) }
            return result.ok ? result : nil
        } catch {
            show(error)
            return nil
        }
    }
}
