import SwiftUI
import WebKit
import AVFoundation

/// Full-screen, interactive live view of one cloud phone.
///
/// The video stream itself runs inside an embedded web view (the streaming
/// provider has no native iOS SDK), but everything around it is native:
/// the navigation keys, keyboard, camera picker and sound toggle drive the
/// page through `window.vhonixLive`, and the page reports its state back
/// through the "vhonix" message handler.
struct LiveScreenView: View {
    let deviceId: Int
    let title: String

    @Environment(\.dismiss) private var dismiss
    @StateObject private var model = LiveScreenModel()

    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()

            VStack(spacing: 0) {
                topBar

                ZStack {
                    if let url = model.url {
                        LiveWebView(url: url, model: model)
                    }

                    if model.state != "live" {
                        overlay
                    }
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)

                controlBar
            }
        }
        .preferredColorScheme(.dark)
        .statusBarHidden()
        .persistentSystemOverlays(.hidden)
        .task { await model.open(deviceId: deviceId) }
        .onDisappear { model.teardown() }
    }

    private var topBar: some View {
        HStack(spacing: 12) {
            Button {
                model.teardown()
                dismiss()
            } label: {
                Image(systemName: "xmark").font(.headline).frame(width: 36, height: 36)
                    .background(Color.white.opacity(0.12)).clipShape(Circle())
            }

            VStack(alignment: .leading, spacing: 1) {
                Text(title).font(.subheadline.weight(.semibold)).lineLimit(1)
                HStack(spacing: 5) {
                    Circle().fill(model.state == "live" ? Color.green : (model.state == "error" ? Color.red : Color.orange))
                        .frame(width: 7, height: 7)
                    Text(model.stateLabel).font(.caption2).foregroundStyle(.white.opacity(0.7))
                }
            }

            Spacer()

            Button {
                model.reconnect()
            } label: {
                Image(systemName: "arrow.triangle.2.circlepath").frame(width: 36, height: 36)
            }
        }
        .foregroundStyle(.white)
        .padding(.horizontal, 14)
        .padding(.vertical, 6)
    }

    private var overlay: some View {
        VStack(spacing: 14) {
            if model.state == "error" || model.loadError != nil {
                Image(systemName: "exclamationmark.triangle").font(.largeTitle).foregroundStyle(.orange)
                Text(model.loadError ?? model.error ?? "The connection dropped.")
                    .multilineTextAlignment(.center).foregroundStyle(.white.opacity(0.85))
                Button("Try again") {
                    if model.url == nil {
                        Task { await model.open(deviceId: deviceId) }
                    } else {
                        model.reconnect()
                    }
                }
                .buttonStyle(.borderedProminent)
            } else if model.state == "idle" && model.url != nil && model.hasConnectedOnce {
                Text("Disconnected").foregroundStyle(.white.opacity(0.8))
                Button("Reconnect") { model.reconnect() }.buttonStyle(.borderedProminent)
            } else {
                ProgressView().tint(.white).scaleEffect(1.3)
                Text(model.hint?.nonEmpty ?? "Connecting to your phone…").foregroundStyle(.white.opacity(0.7))
            }
        }
        .padding(30)
        .allowsHitTesting(true)
    }

    private var controlBar: some View {
        HStack {
            toolButton("chevron.backward", "Back") { model.call("pressBack()") }
            toolButton("circle", "Home") { model.call("pressHome()") }
            toolButton("square", "Recents") { model.call("pressRecents()") }
            toolButton("keyboard", "Keyboard") { model.call("showKeyboard()") }

            Menu {
                if model.cameras.isEmpty {
                    Text(model.cameraError ?? "Tap \"Find cameras\" to list this iPhone's cameras.")
                }
                ForEach(model.cameras, id: \.id) { cam in
                    Button {
                        model.chooseCamera(cam.id)
                    } label: {
                        if cam.id == model.selectedCamera {
                            Label(cam.label, systemImage: "checkmark")
                        } else {
                            Text(cam.label)
                        }
                    }
                }
                Divider()
                Button {
                    model.call("loadCameras()")
                } label: {
                    Label("Find cameras", systemImage: "arrow.clockwise")
                }
            } label: {
                toolLabel("camera", "Camera")
            }

            toolButton(model.muted ? "speaker.slash" : "speaker.wave.2", "Sound") {
                model.muted.toggle()
                model.call("toggleSound()")
            }
        }
        .padding(.horizontal, 8)
        .padding(.top, 8)
        .padding(.bottom, 4)
        .background(Color.black)
        .disabled(model.state != "live")
        .opacity(model.state == "live" ? 1 : 0.45)
    }

    private func toolButton(_ icon: String, _ label: String, action: @escaping () -> Void) -> some View {
        Button(action: action) { toolLabel(icon, label) }
    }

    private func toolLabel(_ icon: String, _ label: String) -> some View {
        VStack(spacing: 3) {
            Image(systemName: icon).font(.system(size: 19, weight: .medium))
            Text(label).font(.system(size: 10))
        }
        .foregroundStyle(.white)
        .frame(maxWidth: .infinity, minHeight: 46)
        .contentShape(Rectangle())
    }
}

struct LiveCamera: Hashable {
    let id: String
    let label: String
}

@MainActor
final class LiveScreenModel: ObservableObject {
    @Published var url: URL?
    @Published var loadError: String?
    @Published var state = "connecting"
    @Published var error: String?
    @Published var hint: String?
    @Published var cameras: [LiveCamera] = []
    @Published var selectedCamera = ""
    @Published var cameraError: String?
    @Published var muted = true
    @Published var hasConnectedOnce = false

    weak var webView: WKWebView?
    private var deviceId: Int?

    var stateLabel: String {
        switch state {
        case "live": return "Live"
        case "error": return "Connection problem"
        case "idle": return hasConnectedOnce ? "Disconnected" : "Starting…"
        default: return "Connecting…"
        }
    }

    func open(deviceId: Int) async {
        self.deviceId = deviceId
        loadError = nil
        state = "connecting"

        do {
            let link: LiveLink = try await APIClient.shared.send("POST", "devices/\(deviceId)/live")
            url = URL(string: link.url)
        } catch {
            loadError = (error as? APIError)?.message ?? error.localizedDescription
            state = "error"
        }
    }

    /// Calls a method on the page's live-screen controller.
    func call(_ js: String) {
        let script = "(function(){ var v = window.vhonixLive; if (v) { v.\(js); } })();"
        webView?.evaluateJavaScript(script, completionHandler: nil)
    }

    func chooseCamera(_ id: String) {
        selectedCamera = id
        let escaped = id.replacingOccurrences(of: "\\", with: "\\\\").replacingOccurrences(of: "'", with: "\\'")
        call("chooseCamera('\(escaped)')")
    }

    func reconnect() {
        error = nil
        state = "connecting"
        if webView != nil {
            call("disconnect()")
            call("connect()")
        } else if let deviceId {
            Task { await open(deviceId: deviceId) }
        }
    }

    func teardown() {
        call("destroy()")
    }

    func receive(_ body: Any) {
        guard let msg = body as? [String: Any], let type = msg["type"] as? String else { return }

        switch type {
        case "state":
            state = msg["state"] as? String ?? state
            error = (msg["error"] as? String)?.nonEmpty
            hint = (msg["hint"] as? String)?.nonEmpty
            if state == "live" { hasConnectedOnce = true }
        case "cameras":
            let list = msg["cameras"] as? [[String: Any]] ?? []
            cameras = list.compactMap { item in
                guard let id = item["id"] as? String else { return nil }
                return LiveCamera(id: id, label: (item["label"] as? String)?.nonEmpty ?? "Camera")
            }
            if let selected = msg["selected"] as? String { selectedCamera = selected }
            cameraError = (msg["error"] as? String)?.nonEmpty
        default:
            break
        }
    }
}

struct LiveWebView: UIViewRepresentable {
    let url: URL
    let model: LiveScreenModel

    func makeCoordinator() -> Coordinator { Coordinator(model: model) }

    func makeUIView(context: Context) -> WKWebView {
        KeyboardFocusFix.apply()

        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true
        config.mediaTypesRequiringUserActionForPlayback = []
        config.userContentController.add(context.coordinator, name: "vhonix")

        let web = WKWebView(frame: .zero, configuration: config)
        web.isOpaque = false
        web.backgroundColor = .black
        web.scrollView.backgroundColor = .black
        web.scrollView.isScrollEnabled = false
        web.scrollView.bounces = false
        web.scrollView.contentInsetAdjustmentBehavior = .never
        web.scrollView.pinchGestureRecognizer?.isEnabled = false
        web.allowsBackForwardNavigationGestures = false
        web.allowsLinkPreview = false
        web.uiDelegate = context.coordinator
        web.navigationDelegate = context.coordinator

        model.webView = web
        web.load(URLRequest(url: url))
        context.coordinator.loadedURL = url

        return web
    }

    func updateUIView(_ web: WKWebView, context: Context) {
        if context.coordinator.loadedURL != url {
            context.coordinator.loadedURL = url
            web.load(URLRequest(url: url))
        }
    }

    static func dismantleUIView(_ web: WKWebView, coordinator: Coordinator) {
        web.evaluateJavaScript("window.vhonixLive && window.vhonixLive.destroy();", completionHandler: nil)
        web.configuration.userContentController.removeScriptMessageHandler(forName: "vhonix")
    }

    @MainActor
    final class Coordinator: NSObject, WKScriptMessageHandler, WKUIDelegate, WKNavigationDelegate {
        let model: LiveScreenModel
        var loadedURL: URL?

        init(model: LiveScreenModel) { self.model = model }

        func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
            let body = message.body
            Task { @MainActor in self.model.receive(body) }
        }

        // Lets apps on the cloud phone use this iPhone's camera and microphone
        // (iOS still asks the user once, the first time).
        func webView(_ webView: WKWebView, requestMediaCapturePermissionFor origin: WKSecurityOrigin,
                     initiatedByFrame frame: WKFrameInfo, type: WKMediaCaptureType,
                     decisionHandler: @escaping (WKPermissionDecision) -> Void) {
            decisionHandler(origin.host.hasSuffix("vhonixcloud.com") ? .grant : .deny)
        }

        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            report(error)
        }

        func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            report(error)
        }

        // Never leave the live page for another site inside this view.
        func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                     decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
            if let host = action.request.url?.host, !host.hasSuffix("vhonixcloud.com"), action.targetFrame?.isMainFrame == true {
                decisionHandler(.cancel)
                return
            }
            decisionHandler(.allow)
        }

        func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
            webView.reload()
        }

        private func report(_ error: Error) {
            if (error as NSError).code == NSURLErrorCancelled { return }
            Task { @MainActor in
                self.model.state = "error"
                self.model.loadError = "Couldn't load the live screen. Check your connection and try again."
            }
        }
    }
}

/// WKWebView only shows the keyboard for a focused input when the user tapped
/// it directly. The phone's keyboard button focuses the stream's hidden text
/// input from code, so tell WebKit to treat that focus as user-initiated.
enum KeyboardFocusFix {
    private static var applied = false

    static func apply() {
        guard !applied, let cls = NSClassFromString("WKContentView") else { return }
        applied = true

        let selector = sel_getUid("_elementDidFocus:userIsInteracting:blurPreviousNode:activityStateChanges:userObject:")
        guard let method = class_getInstanceMethod(cls, selector) else { return }

        typealias Original = @convention(c) (AnyObject, Selector, UnsafeRawPointer, Bool, Bool, UInt64, AnyObject?) -> Void
        let original = unsafeBitCast(method_getImplementation(method), to: Original.self)

        let replacement: @convention(block) (AnyObject, UnsafeRawPointer, Bool, Bool, UInt64, AnyObject?) -> Void = { me, info, _, blur, changes, userObject in
            original(me, selector, info, true, blur, changes, userObject)
        }

        method_setImplementation(method, imp_implementationWithBlock(replacement))
    }
}
