import SwiftUI
import WebKit

/// More → Virtual camera: set the video link ahead of time and watch a live
/// preview of exactly what the cloud phone's camera (and microphone) will get.
struct VirtualCameraView: View {
    @State private var link = VirtualCamera.url
    @State private var enabled = VirtualCamera.enabled
    @State private var audio = VirtualCamera.audio
    @State private var problem: String?
    @State private var saved = false
    @StateObject private var preview = VirtualCameraPreview()

    private var hasLink: Bool { !VirtualCamera.url.isEmpty }

    var body: some View {
        Screen(backdrop: .violet) {
            HStack(spacing: 14) {
                IconTile(symbol: "video.fill", colors: TileColors.pink, size: 50)
                VStack(alignment: .leading, spacing: 4) {
                    Text("Virtual camera").font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                    Text("Your video link shows up as the camera on your cloud phones.")
                        .font(.subheadline).foregroundStyle(Palette.text2)
                }
                Spacer(minLength: 0)
            }
            .glassCard(padding: 18, highlight: true, tint: Palette.pink)
            .entrance(0)

            previewCard.entrance(1)
            linkCard.entrance(2)
            optionsCard.entrance(3)

            NoteView(text: "Links that work: your camera share link (live.php?s=…), HLS (.m3u8), MP4, WebRTC (WHEP) and MJPEG, starting with https://. Your video server must allow cross-origin access (CORS), otherwise the preview shows a warning. RTMP/RTSP links can't play on iPhone — use the HLS or WebRTC link of the same stream.",
                     icon: "lightbulb")
                .padding(.horizontal, 4)
        }
        .navigationTitle("Virtual camera")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear { if hasLink { preview.start() } }
        .onDisappear { preview.stop() }
    }

    // MARK: Preview

    private var previewCard: some View {
        VStack(alignment: .leading, spacing: 14) {
            ZStack {
                RoundedRectangle(cornerRadius: 16, style: .continuous).fill(Color.black)
                if preview.running {
                    VirtualCameraPreviewView(model: preview)
                        .id(preview.session)
                        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
                } else {
                    VStack(spacing: 10) {
                        Image(systemName: "video.slash").font(.system(size: 30, weight: .semibold)).foregroundStyle(Palette.text3)
                        Text(hasLink ? "Preview stopped" : "Paste a link below, then tap Save & test")
                            .font(.subheadline).foregroundStyle(Palette.text2)
                            .multilineTextAlignment(.center)
                    }
                    .padding(20)
                }
            }
            .aspectRatio(16 / 9, contentMode: .fit)
            .overlay(RoundedRectangle(cornerRadius: 16, style: .continuous).strokeBorder(Palette.stroke, lineWidth: 1))

            HStack(spacing: 8) {
                Circle().fill(statusColor).frame(width: 8, height: 8)
                Text(statusText)
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(preview.status == "error" ? Palette.danger : Palette.text2)
                    .fixedSize(horizontal: false, vertical: true)
                Spacer(minLength: 0)
            }
            .accessibilityElement(children: .combine)

            HStack(spacing: 10) {
                Button {
                    Haptics.tap()
                    preview.running ? preview.stop() : preview.start()
                } label: {
                    Label(preview.running ? "Stop" : "Test", systemImage: preview.running ? "stop.fill" : "play.fill")
                }
                .buttonStyle(.glass)
                .disabled(!hasLink)

                Button {
                    Haptics.tap()
                    preview.setSound(!preview.soundOn)
                } label: {
                    Label(preview.soundOn ? "Sound on" : "Hear sound",
                          systemImage: preview.soundOn ? "speaker.wave.2.fill" : "speaker.slash.fill")
                }
                .buttonStyle(.glass)
                .disabled(!preview.running || !audio)
            }
        }
        .glassCard(padding: 16)
    }

    private var statusText: String {
        if !preview.running { return hasLink ? "Not testing" : "No link yet" }
        switch preview.status {
        case "live": return audio ? "Playing — this is what your cloud phone's camera and microphone get" : "Playing — this is what your cloud phone's camera gets"
        case "error": return preview.message ?? "Can't play this link"
        default: return preview.message ?? "Connecting to your video…"
        }
    }

    private var statusColor: Color {
        guard preview.running else { return Palette.text3 }
        switch preview.status {
        case "live": return Palette.success
        case "error": return Palette.danger
        default: return Palette.warning
        }
    }

    // MARK: Link

    private var linkCard: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(alignment: .bottom, spacing: 10) {
                GlassField(label: "Video link", text: $link, prompt: "https://…", keyboard: .URL)
                Button {
                    if let s = UIPasteboard.general.string?.trimmingCharacters(in: .whitespacesAndNewlines), !s.isEmpty {
                        Haptics.tap()
                        link = s
                    }
                } label: {
                    Image(systemName: "doc.on.clipboard").font(.system(size: 17, weight: .semibold))
                        .frame(width: 50, height: 50)
                        .background(RoundedRectangle(cornerRadius: 14, style: .continuous).fill(Palette.surfaceStrong))
                        .foregroundStyle(Palette.text)
                }
                .accessibilityLabel("Paste link")
            }

            if let problem {
                NoteView(text: problem, icon: "exclamationmark.triangle.fill", tint: Palette.danger)
            }

            Button {
                save()
            } label: {
                Label(saved ? "Saved" : "Save & test", systemImage: saved ? "checkmark" : "play.circle.fill")
            }
            .buttonStyle(.primary)

            if hasLink {
                Button(role: .destructive) {
                    Haptics.tap()
                    link = ""
                    VirtualCamera.url = ""
                    problem = nil
                    preview.stop()
                } label: {
                    Text("Remove link").font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.danger)
                        .frame(maxWidth: .infinity, minHeight: 44)
                }
            }
        }
        .glassCard(padding: 18)
    }

    private func save() {
        UIApplication.shared.sendAction(#selector(UIResponder.resignFirstResponder), to: nil, from: nil, for: nil)
        let clean = link.trimmingCharacters(in: .whitespacesAndNewlines)
        if clean.isEmpty {
            problem = "Paste your video link first."
            Haptics.error()
            return
        }
        if let issue = VirtualCamera.problem(with: clean) {
            problem = issue
            Haptics.error()
            return
        }
        problem = nil
        link = clean
        VirtualCamera.url = clean
        Haptics.success()
        preview.start()
        withAnimation { saved = true }
        Task {
            try? await Task.sleep(nanoseconds: 1_500_000_000)
            withAnimation { saved = false }
        }
    }

    // MARK: Options

    private var optionsCard: some View {
        VStack(alignment: .leading, spacing: 12) {
            ToggleRow(title: "Use on my cloud phones",
                      subtitle: "Shows \"Vhonix virtual camera\" in the live screen's Camera menu",
                      isOn: $enabled)
            CardDivider()
            ToggleRow(title: "Send the video's sound",
                      subtitle: "Apps on the cloud phone hear it as the microphone",
                      isOn: $audio)
        }
        .glassCard(padding: 18)
        .onChange(of: enabled) { VirtualCamera.enabled = $0 }
        .onChange(of: audio) { on in
            VirtualCamera.audio = on
            if preview.running { preview.start() }
        }
    }
}

// MARK: - Preview engine

/// Runs the same virtual-camera script the live screen uses, in a small web
/// view, and shows its output — so the preview is a true test of the link.
@MainActor
final class VirtualCameraPreview: ObservableObject {
    @Published var running = false
    @Published var session = UUID()
    @Published var status = "connecting"
    @Published var message: String?
    @Published var soundOn = false

    weak var webView: WKWebView?

    func start() {
        guard !VirtualCamera.url.isEmpty else { return }
        status = "connecting"
        message = nil
        soundOn = false
        session = UUID()
        running = true
    }

    func stop() {
        webView?.evaluateJavaScript("window.vhonixVirtualCam && window.vhonixVirtualCam.stopPipeline();", completionHandler: nil)
        running = false
        soundOn = false
    }

    func setSound(_ on: Bool) {
        soundOn = on
        webView?.evaluateJavaScript("window.setSound && window.setSound(\(on));", completionHandler: nil)
    }

    func receive(_ body: Any) {
        guard let msg = body as? [String: Any], msg["type"] as? String == "virtualCam" else { return }
        status = msg["status"] as? String ?? status
        message = (msg["message"] as? String)?.nonEmpty
    }

    static let page = """
    <!doctype html><html><head>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <style>html,body{margin:0;height:100%;background:#000;overflow:hidden}video{width:100%;height:100%;object-fit:contain;background:#000}</style>
    </head><body><video id="v" autoplay playsinline muted></video><script>
    (function () {
      var cam = window.vhonixVirtualCam, v = document.getElementById('v');
      if (!cam) return;
      var tracks = [cam.track()];
      if (cam.audio) { var a = cam.audioTrack(); if (a) tracks.push(a); }
      v.srcObject = new MediaStream(tracks);
      var p = v.play(); if (p && p.catch) p.catch(function () {});
      window.setSound = function (on) {
        v.muted = !on;
        if (cam.actx && cam.actx.resume) cam.actx.resume();
        var q = v.play(); if (q && q.catch) q.catch(function () {});
      };
    })();
    </script></body></html>
    """
}

struct VirtualCameraPreviewView: UIViewRepresentable {
    let model: VirtualCameraPreview

    func makeCoordinator() -> Coordinator { Coordinator(model: model) }

    func makeUIView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true
        config.mediaTypesRequiringUserActionForPlayback = []
        config.userContentController.add(context.coordinator, name: "vhonix")
        // Always on for the preview, so it can be tested even while switched off for the phones.
        var settings = VirtualCamera.current
        settings.enabled = true
        config.userContentController.addUserScript(WKUserScript(
            source: VirtualCamera.script(settings), injectionTime: .atDocumentStart, forMainFrameOnly: true))

        let web = WKWebView(frame: .zero, configuration: config)
        web.isOpaque = false
        web.backgroundColor = .black
        web.scrollView.isScrollEnabled = false
        web.scrollView.bounces = false
        web.isUserInteractionEnabled = false
        model.webView = web
        // Same web address as the live screen, so the video server's CORS rules apply exactly as they will there.
        web.loadHTMLString(VirtualCameraPreview.page, baseURL: Config.siteURL)
        return web
    }

    func updateUIView(_ web: WKWebView, context: Context) {}

    static func dismantleUIView(_ web: WKWebView, coordinator: Coordinator) {
        web.evaluateJavaScript("window.vhonixVirtualCam && window.vhonixVirtualCam.stopPipeline();", completionHandler: nil)
        web.configuration.userContentController.removeScriptMessageHandler(forName: "vhonix")
    }

    @MainActor
    final class Coordinator: NSObject, WKScriptMessageHandler {
        let model: VirtualCameraPreview
        init(model: VirtualCameraPreview) { self.model = model }

        func userContentController(_ controller: WKUserContentController, didReceive message: WKScriptMessage) {
            let body = message.body
            Task { @MainActor in self.model.receive(body) }
        }
    }
}
