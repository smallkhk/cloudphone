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
                List {
                    screenSection(data.device)
                    actionsSection
                    controlsSection(data)

                    if let rows = data.details?.rows, !rows.isEmpty {
                        Section("Device info") {
                            ForEach(rows, id: \.self) { row in
                                CopyRow(label: row.label, value: row.value)
                            }
                        }
                    }

                    if !data.tasks.isEmpty {
                        Section("Recent activity") {
                            ForEach(data.tasks) { task in
                                HStack {
                                    VStack(alignment: .leading) {
                                        Text(Format.status(task.type)).font(.subheadline)
                                        Text(Format.relative(task.createdAt)).font(.caption).foregroundStyle(.secondary)
                                    }
                                    Spacer()
                                    StatusPill.forStatus(task.status)
                                }
                            }
                        }
                    }
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
        }
        .confirmationDialog("Reset this phone? This erases all apps and data on it.", isPresented: $confirmReset, titleVisibility: .visible) {
            Button("Erase and reset", role: .destructive) { Task { await run("reset") } }
        }
        .confirmationDialog("Give this phone a brand-new device identity? It restarts and can take a few minutes.", isPresented: $confirmNewDevice, titleVisibility: .visible) {
            Button("New identity, keep data") { Task { await run("new-device", ["wipe_data": false]) } }
            Button("New identity and wipe data", role: .destructive) { Task { await run("new-device", ["wipe_data": true]) } }
        }
    }

    private func screenSection(_ device: Device) -> some View {
        Section {
            VStack(spacing: 14) {
                Button {
                    showLive = true
                } label: {
                    ZStack {
                        RoundedRectangle(cornerRadius: 18).fill(Color.black)
                        if let url = device.screenshotUrl.flatMap(URL.init(string:)) {
                            AsyncImage(url: url) { image in
                                image.resizable().scaledToFit()
                            } placeholder: {
                                ProgressView().tint(.white)
                            }
                            .clipShape(RoundedRectangle(cornerRadius: 18))
                            .opacity(0.55)
                        }
                        VStack(spacing: 8) {
                            Image(systemName: "play.circle.fill").font(.system(size: 54))
                            Text("Open live screen").font(.headline)
                        }
                        .foregroundStyle(.white)
                    }
                    .frame(height: 300)
                }
                .buttonStyle(.plain)

                HStack {
                    StatusPill.forStatus(device.expired == true ? "expired" : device.status)
                    Spacer()
                    if let expires = device.expiresAt {
                        Text("Expires \(Format.when(expires))").font(.caption).foregroundStyle(.secondary)
                    }
                }
            }
            .listRowInsets(EdgeInsets(top: 12, leading: 12, bottom: 12, trailing: 12))
        }
    }

    private var actionsSection: some View {
        Section("Power") {
            AsyncButton {
                await run("screenshot")
            } label: {
                Label("Refresh screenshot", systemImage: "camera.viewfinder")
            }
            Button { confirmRestart = true } label: { Label("Restart", systemImage: "arrow.clockwise") }
            Button { confirmNewDevice = true } label: { Label("New device identity", systemImage: "person.crop.rectangle.badge.plus") }
            Button(role: .destructive) { confirmReset = true } label: { Label("Reset (erase everything)", systemImage: "trash") }
        }
    }

    private func controlsSection(_ data: DeviceDetailResponse) -> some View {
        Section("Settings") {
            NavigationLink {
                SimSettingsView(deviceId: deviceId, countries: data.countries, current: data.details?.simCountry, reload: load)
            } label: { Label("Phone number & SIM", systemImage: "simcard") }

            NavigationLink {
                GpsSettingsView(deviceId: deviceId, latitude: data.details?.latitude, longitude: data.details?.longitude, reload: load)
            } label: { Label("GPS location", systemImage: "location") }

            NavigationLink {
                LocaleSettingsView(deviceId: deviceId, timezone: data.details?.timezone, reload: load)
            } label: { Label("Language & timezone", systemImage: "globe") }

            NavigationLink {
                DeviceProxyView(deviceId: deviceId)
            } label: { Label("Proxy", systemImage: "network") }

            NavigationLink {
                AppsView(deviceId: deviceId, apps: data.apps, reload: load)
            } label: { Label("Apps (\(data.apps.count))", systemImage: "square.grid.3x3") }

            NavigationLink {
                AdbView(deviceId: deviceId)
            } label: { Label("ADB", systemImage: "terminal") }
        }
    }

    private func run(_ action: String, _ body: [String: Any] = [:]) async {
        if await app.act("\(path)/\(action)", body: body) != nil {
            await load()
        }
    }

    private func load() async {
        do {
            data = try await APIClient.shared.get(path)
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}
