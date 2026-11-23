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
    @State private var showLinkPrompt = false
    @State private var linkDraft = ""
    @State private var linkError: String?

    var body: some View {
        ZStack {
            Color.black.ignoresSafeArea()

            VStack(spacing: 10) {
                topBar

                ZStack {
                    if let url = model.url {
                        LiveWebView(url: url, model: model)
                            .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    }

                    if model.state != "live" {
                        overlay
                    }
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .padding(.horizontal, 6)

                controlBar
            }
        }
        .preferredColorScheme(.dark)
        .statusBarHidden()
        .persistentSystemOverlays(.hidden)
        .task { await model.open(deviceId: deviceId) }
        .onDisappear { model.teardown() }
        .onChange(of: model.state) { state in
            if state == "live" { Haptics.success() } else if state == "error" { Haptics.error() }
        }
        .alert("Virtual camera", isPresented: $showLinkPrompt) {
            TextField("https://…", text: $linkDraft)
                .textInputAutocapitalization(.never)
                .autocorrectionDisabled()
                .keyboardType(.URL)
            Button("Save") {
                if let problem = model.saveVirtualCamera(linkDraft) { linkError = problem }
            }
            if !model.virtualCamURL.isEmpty {
                Button("Remove link", role: .destructive) { _ = model.saveVirtualCamera("") }
            }
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("Paste your video link. Apps on the cloud phone will see this video as the camera. Works with HLS (.m3u8), MP4, WebRTC (WHEP) and MJPEG links that start with https://.")
        }
    }

    private var topBar: some View {
        HStack(spacing: 12) {
            Button {
                Haptics.tap()
                model.teardown()
                dismiss()
            } label: {
                Image(systemName: "chevron.down")
                    .font(.system(size: 16, weight: .bold))
                    .frame(width: 44, height: 44)
                    .background(Circle().fill(Color.white.opacity(0.12)))
            }
            .accessibilityLabel("Close live screen")

            VStack(alignment: .leading, spacing: 2) {
                Text(title).font(Typo.rounded(.subheadline, .bold)).lineLimit(1)
                HStack(spacing: 6) {
                    Circle()
                        .fill(model.state == "live" ? Palette.success : (model.state == "error" ? Palette.danger : Palette.warning))
                        .frame(width: 7, height: 7)
                        .shadow(color: model.state == "live" ? Palette.success : .clear, radius: 4)
                    Text(model.stateLabel).font(.caption2.weight(.semibold)).foregroundStyle(Palette.text2)
                }
            }

            Spacer()

            Button {
                Haptics.tap()
                model.reconnect()
            } label: {
                Image(systemName: "arrow.triangle.2.circlepath")
                    .font(.system(size: 15, weight: .semibold))
                    .frame(width: 44, height: 44)
                    .background(Circle().fill(Color.white.opacity(0.12)))
            }
            .accessibilityLabel("Reconnect")
        }
        .foregroundStyle(.white)
        .padding(.horizontal, 12)
        .padding(.top, 4)
        .alert("Can't use that link", isPresented: Binding(get: { linkError != nil }, set: { if !$0 { linkError = nil } })) {
            Button("OK", role: .cancel) {}
        } message: {
            Text(linkError ?? "")
        }
    }

    private var overlay: some View {
        VStack(spacing: 16) {
            if model.state == "error" || model.loadError != nil {
                IconTile(symbol: "wifi.exclamationmark", colors: TileColors.orange, size: 64)
                Text(model.loadError ?? model.error ?? "The connection dropped.")
                    .font(.subheadline)
                    .multilineTextAlignment(.center)
                    .foregroundStyle(Palette.text2)
                Button("Try again") {
                    if model.url == nil {
                        Task { await model.open(deviceId: deviceId) }
                    } else {
                        model.reconnect()
                    }
                }
                .buttonStyle(.primary)
                .frame(maxWidth: 220)
            } else if model.state == "idle" && model.url != nil && model.hasConnectedOnce {
                IconTile(symbol: "pause.fill", colors: TileColors.slate, size: 64)
                Text("Disconnected").font(Typo.rounded(.headline, .semibold)).foregroundStyle(Palette.text2)
                Button("Reconnect") { model.reconnect() }
                    .buttonStyle(.primary)
                    .frame(maxWidth: 220)
            } else {
                ZStack {
                    Circle().fill(Theme.brand).frame(width: 110, height: 110).blur(radius: 34).opacity(0.5)
                    BrandMark(size: 64, showName: false)
                }
                ProgressView().tint(.white)
                Text(model.hint?.nonEmpty ?? "Connecting to your phone…")
                    .font(.subheadline).foregroundStyle(Palette.text2)
            }
        }
        .padding(30)
    }

    private var controlBar: some View {
        HStack(spacing: 2) {
            toolButton("chevron.backward", "Back") { model.call("pressBack()") }
            toolButton("circle", "Home") { model.call("pressHome()") }
            toolButton("square.on.square", "Recents") { model.call("pressRecents()") }
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
                    model.findCameras()
                } label: {
                    Label("Find cameras", systemImage: "arrow.clockwise")
                }
                Section {
                    Button {
                        linkDraft = model.virtualCamURL
                        showLinkPrompt = true
                    } label: {
                        Label(model.virtualCamURL.isEmpty ? "Set up virtual camera…" : "Change virtual camera link…",
                              systemImage: "video.badge.plus")
                    }
                    if !model.virtualCamURL.isEmpty, let status = model.virtualCamStatus {
                        Text(status)
                    }
                }
            } label: {
                toolLabel("camera.fill", "Camera")
            }

            toolButton(model.muted ? "speaker.slash.fill" : "speaker.wave.2.fill", "Sound") {
                model.muted.toggle()
                model.call("toggleSound()")
            }
        }
        .padding(6)
        .background(
            Capsule().fill(.ultraThinMaterial)
                .overlay(Capsule().fill(Color.white.opacity(0.04)))
        )
        .overlay(Capsule().strokeBorder(Color.white.opacity(0.12), lineWidth: 1))
        .padding(.horizontal, 12)
        .padding(.bottom, 6)
        .disabled(model.state != "live")
        .opacity(model.state == "live" ? 1 : 0.45)
        .animation(.easeOut(duration: 0.25), value: model.state)
    }

    private func toolButton(_ icon: String, _ label: String, action: @escaping () -> Void) -> some View {
        Button {
            Haptics.tap()
            action()
        } label: { toolLabel(icon, label) }
        .buttonStyle(.pressable)
    }

    private func toolLabel(_ icon: String, _ label: String) -> some View {
        VStack(spacing: 3) {
            Image(systemName: icon).font(.system(size: 18, weight: .semibold))
            Text(label).font(Typo.rounded(.caption2, .medium))
        }
        .foregroundStyle(.white)
        .frame(maxWidth: .infinity, minHeight: 50)
        .contentShape(Rectangle())
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(label)
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
    @Published var virtualCamURL = VirtualCamera.url
    @Published var virtualCamStatus: String?

    weak var webView: WKWebView?
    let framePump = LocalFramePump()
    /// True once the loaded page has reported in — i.e. `window.vhonixLive` exists.
    var pageReady = false
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
        error = nil
        state = "connecting"
        pageReady = false
        // Dropping the old address removes the old web view, so the new link
        // always loads in a clean one (even if it happens to match the old URL).
        url = nil

        do {
            // Always a fresh link: the page address is signed and only valid for 10 minutes.
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

    /// Runs a script on the page as-is (not through `window.vhonixLive`).
    func run(_ js: String) {
        webView?.evaluateJavaScript(js, completionHandler: nil)
    }

    /// Lists cameras, asking iOS for the real cameras' names if needed.
    func findCameras() {
        run("window.vhonixVirtualCam && (window.vhonixVirtualCam.probeNext = true);")
        call("loadCameras()")
    }

    /// Saves (or with "" removes) the virtual camera link and switches to it.
    /// Returns why the link can't be used, or nil when saved.
    func saveVirtualCamera(_ link: String) -> String? {
        let clean = link.trimmingCharacters(in: .whitespacesAndNewlines)
        if let problem = VirtualCamera.problem(with: clean) { return problem }

        VirtualCamera.url = clean
        if !clean.isEmpty { VirtualCamera.enabled = true }
        virtualCamURL = clean
        virtualCamStatus = clean.isEmpty ? nil : "Virtual camera: waiting for an app to open the camera"
        run("window.vhonixVirtualCam && window.vhonixVirtualCam.setConfig(\(VirtualCamera.json(VirtualCamera.resolved())));")
        call("loadCameras()")

        if !clean.isEmpty {
            chooseCamera(VirtualCamera.deviceId)
        } else if selectedCamera == VirtualCamera.deviceId {
            chooseCamera("")
        }
        return nil
    }

    func chooseCamera(_ id: String) {
        selectedCamera = id
        let escaped = id.replacingOccurrences(of: "\\", with: "\\\\").replacingOccurrences(of: "'", with: "\\'")
        call("chooseCamera('\(escaped)')")
    }

    func reconnect() {
        error = nil
        state = "connecting"
        if webView != nil, pageReady, loadError == nil {
            call("disconnect()")
            call("connect()")
        } else {
            reload()
        }
    }

    /// Loads the page again from a brand-new signed link (the old one may have expired).
    func reload() {
        guard let deviceId else { return }
        Task { await open(deviceId: deviceId) }
    }

    func pageFailed(_ message: String) {
        pageReady = false
        state = "error"
        loadError = message
    }

    func teardown() {
        framePump.stop()
        call("destroy()")
    }

    func receive(_ body: Any) {
        guard let msg = body as? [String: Any], let type = msg["type"] as? String else { return }

        switch type {
        case "virtualCamPump":
            framePump.handle(msg, from: webView)
        case "state":
            pageReady = true
            state = msg["state"] as? String ?? state
            error = (msg["error"] as? String)?.nonEmpty
            hint = (msg["hint"] as? String)?.nonEmpty
            if state == "live" { hasConnectedOnce = true }
        case "virtualCam":
            let message = (msg["message"] as? String)?.nonEmpty
            switch msg["status"] as? String {
            case "live": virtualCamStatus = "Virtual camera: playing your video"
            case "connecting": virtualCamStatus = "Virtual camera: connecting…"
            case "error": virtualCamStatus = message ?? "Virtual camera: can't play the link"
            default: virtualCamStatus = message
            }
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
        // Adds the "Vhonix virtual camera" before the page's own scripts run.
        config.userContentController.addUserScript(WKUserScript(
            source: VirtualCamera.script(),
            injectionTime: .atDocumentStart,
            forMainFrameOnly: true))

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
            decisionHandler(Self.isOurs(origin.host) ? .grant : .deny)
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
            if let host = action.request.url?.host, !Self.isOurs(host), action.targetFrame?.isMainFrame == true {
                decisionHandler(.cancel)
                return
            }
            decisionHandler(.allow)
        }

        // The page itself failed (e.g. its 10-minute link expired → 403). That's
        // a "successful" load to WebKit, so catch the status code here.
        func webView(_ webView: WKWebView, decidePolicyFor navigationResponse: WKNavigationResponse,
                     decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void) {
            if navigationResponse.isForMainFrame,
               let status = (navigationResponse.response as? HTTPURLResponse)?.statusCode,
               !(200..<300).contains(status) {
                decisionHandler(.cancel)
                Task { @MainActor in
                    self.model.pageFailed("The live screen couldn't open (the link may have expired). Tap Try again.")
                }
                return
            }
            decisionHandler(.allow)
        }

        // iOS killed the web page (memory pressure, often while in the background).
        // Reloading the same address would hit an expired link, so fetch a new one.
        func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
            Task { @MainActor in
                self.model.pageReady = false
                self.model.reload()
            }
        }

        private func report(_ error: Error) {
            let ns = error as NSError
            if ns.code == NSURLErrorCancelled { return }
            // 102 = load stopped by our own policy decision above; already reported.
            if ns.domain == "WebKitErrorDomain", ns.code == 102 { return }
            Task { @MainActor in
                self.model.pageFailed("Couldn't load the live screen. Check your connection and try again.")
            }
        }

        /// vhonixcloud.com or one of its subdomains — not e.g. "evilvhonixcloud.com".
        nonisolated static func isOurs(_ host: String) -> Bool {
            let h = host.lowercased()
            return h == "vhonixcloud.com" || h.hasSuffix(".vhonixcloud.com")
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
