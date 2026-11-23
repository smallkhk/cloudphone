import SwiftUI

struct DeviceDetailView: View {
    let deviceId: Int
    let title: String

    @EnvironmentObject private var app: AppState
    @State private var data: DeviceDetailResponse?
    @State private var loading = true
    @State private var error: String?
    @State private var showLive = false
    @State private var confirmRestart = false
    @State private var confirmReset = false
    @State private var confirmNewDevice = false

    private var path: String { "devices/\(deviceId)" }

    var body: some View {
        LoadingView(isLoading: loading && data == nil, error: data == nil ? error : nil, retry: load) {
            if let data {
                Screen {
                    hero(data.device)
                    quickActions
                    settings(data)
                    if let rows = data.details?.rows, !rows.isEmpty { info(rows) }
                    if !data.tasks.isEmpty { activity(data.tasks) }
                }
            }
        }
        .navigationTitle(data?.device.name ?? title)
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await load() }
        .task { await load() }
        .fullScreenCover(isPresented: $showLive) {
            LiveScreenView(deviceId: deviceId, title: data?.device.name ?? title)
        }
        .confirmationDialog("Restart this phone?", isPresented: $confirmRestart, titleVisibility: .visible) {
            Button("Restart") { Task { await run("restart") } }
        } message: {
            Text("Apps close and the phone is back in about a minute.")
        }
        .confirmationDialog("Erase this phone?", isPresented: $confirmReset, titleVisibility: .visible) {
            Button("Erase and reset", role: .destructive) { Task { await run("reset") } }
        } message: {
            Text("All apps and data on it are deleted. This can't be undone.")
        }
        .confirmationDialog("New device identity", isPresented: $confirmNewDevice, titleVisibility: .visible) {
            Button("New identity, keep data") { Task { await run("new-device", ["wipe_data": false]) } }
            Button("New identity and wipe data", role: .destructive) { Task { await run("new-device", ["wipe_data": true]) } }
        } message: {
            Text("The phone gets a brand-new model identity and restarts. This can take a few minutes.")
        }
    }

    // MARK: Sections

    private func hero(_ device: Device) -> some View {
        VStack(spacing: 18) {
            ZStack {
                Ellipse().fill(Theme.brand).frame(width: 220, height: 260).blur(radius: 60).opacity(0.35)

                PhoneThumb(url: device.screenshotUrl)
                    .frame(width: 168, height: 300)
                    .overlay(alignment: .bottom) {
                        Capsule().fill(Color.white.opacity(0.35)).frame(width: 54, height: 4).padding(.bottom, 8)
                    }
            }
            .frame(maxWidth: .infinity)
            .onTapGesture { openLive() }

            HStack(spacing: 8) {
                StatusPill.forStatus(device.expired == true ? "expired" : device.status)
                if let v = device.androidVersion { StatusPill(text: "Android \(v)", color: Palette.accentText) }
                if let exp = Format.date(device.expiresAt) {
                    StatusPill(text: "Until \(exp.formatted(date: .abbreviated, time: .omitted))", color: Palette.text2)
                }
            }

            Button { openLive() } label: {
                Label("Open live screen", systemImage: "play.fill")
            }
            .buttonStyle(.primary)
        }
        .glassCard(padding: 18, highlight: true)
    }

    private var quickActions: some View {
        HStack(spacing: 10) {
            QuickAction(icon: "camera.viewfinder", title: "Screenshot", colors: TileColors.cyan) { await run("screenshot") }
            QuickAction(icon: "arrow.clockwise", title: "Restart", colors: TileColors.green) { confirmRestart = true }
            QuickAction(icon: "person.crop.rectangle.badge.plus", title: "New ID", colors: TileColors.pink) { confirmNewDevice = true }
            QuickAction(icon: "trash.fill", title: "Reset", colors: TileColors.red) { confirmReset = true }
        }
    }

    private func settings(_ data: DeviceDetailResponse) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            SectionTitle(title: "Controls")
            VStack(spacing: 4) {
                link("simcard.fill", TileColors.green, "Phone number & SIM", data.details?.simCountry.map { "Now: \($0.uppercased())" } ?? "New number and carrier") {
                    SimSettingsView(deviceId: deviceId, countries: data.countries, current: data.details?.simCountry, reload: load)
                }
                CardDivider()
                link("location.fill", TileColors.cyan, "GPS location", "Set where the phone thinks it is") {
                    GpsSettingsView(deviceId: deviceId, latitude: data.details?.latitude, longitude: data.details?.longitude, reload: load)
                }
                CardDivider()
                link("globe", TileColors.indigo, "Language & timezone", data.details?.timezone ?? "Region settings") {
                    LocaleSettingsView(deviceId: deviceId, timezone: data.details?.timezone, reload: load)
                }
                CardDivider()
                link("network", TileColors.orange, "Proxy", "Route traffic through a proxy") {
                    DeviceProxyView(deviceId: deviceId)
                }
                CardDivider()
                link("square.grid.3x3.fill", TileColors.pink, "Apps", "\(data.apps.count) installed") {
                    AppsView(deviceId: deviceId, apps: data.apps, reload: load)
                }
                CardDivider()
                link("terminal.fill", TileColors.slate, "ADB", "Developer connection") {
                    AdbView(deviceId: deviceId)
                }
            }
            .glassCard(padding: 12)
        }
    }

    private func link<D: View>(_ icon: String, _ colors: (Color, Color), _ title: String, _ subtitle: String, @ViewBuilder destination: @escaping () -> D) -> some View {
        NavigationLink {
            destination()
        } label: {
            MenuRow(icon: icon, colors: colors, title: title, subtitle: subtitle)
        }
        .buttonStyle(.pressable)
    }

    private func info(_ rows: [InfoRowData]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            SectionTitle(title: "Device info")
            VStack(spacing: 10) {
                ForEach(Array(rows.enumerated()), id: \.offset) { index, row in
                    if index > 0 { CardDivider() }
                    CopyRow(label: row.label, value: row.value)
                }
            }
            .glassCard()
        }
    }

    private func activity(_ tasks: [TaskItem]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            SectionTitle(title: "Recent activity")
            VStack(spacing: 0) {
                ForEach(Array(tasks.enumerated()), id: \.element.id) { index, task in
                    HStack(alignment: .top, spacing: 14) {
                        VStack(spacing: 0) {
                            Circle().fill(StatusPill.color(for: task.status)).frame(width: 10, height: 10).padding(.top, 5)
                            if index < tasks.count - 1 {
                                Rectangle().fill(Palette.stroke).frame(width: 2).frame(maxHeight: .infinity)
                            }
                        }
                        .frame(width: 12)
                        VStack(alignment: .leading, spacing: 3) {
                            Text(Format.status(task.type)).font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.text)
                            Text(Format.relative(task.createdAt)).font(.caption).foregroundStyle(Palette.text3)
                        }
                        Spacer()
                        StatusPill.forStatus(task.status)
                    }
                    .padding(.bottom, index < tasks.count - 1 ? 14 : 0)
                }
            }
            .glassCard()
        }
    }

    // MARK: Actions

    private func openLive() {
        Haptics.medium()
        showLive = true
    }

    private func run(_ action: String, _ body: [String: Any] = [:]) async {
        if await app.act("\(path)/\(action)", body: body) != nil {
            await load()
        }
    }

    private func load() async {
        do {
            let response: DeviceDetailResponse = try await APIClient.shared.get(path)
            withAnimation(.easeOut(duration: 0.25)) { data = response }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

struct QuickAction: View {
    let icon: String
    let title: String
    var colors: (Color, Color) = TileColors.indigo
    let action: () async -> Void

    @State private var running = false

    var body: some View {
        Button {
            guard !running else { return }
            Haptics.tap()
            running = true
            Task {
                await action()
                running = false
            }
        } label: {
            VStack(spacing: 8) {
                ZStack {
                    IconTile(symbol: icon, colors: colors, size: 46)
                    if running { ProgressView().tint(.white) }
                }
                Text(title).font(Typo.rounded(.caption, .semibold)).foregroundStyle(Palette.text2).lineLimit(1).minimumScaleFactor(0.8)
            }
            .frame(maxWidth: .infinity, minHeight: 92)
            .glassCard(padding: 8, radius: 18)
        }
        .buttonStyle(.pressable)
        .accessibilityLabel(title)
    }
}
