import SwiftUI

struct DevicesView: View {
    @EnvironmentObject private var app: AppState
    @State private var devices: [Device] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        LoadingView(isLoading: loading && devices.isEmpty, error: devices.isEmpty ? error : nil, retry: load) {
            if devices.isEmpty {
                VStack(spacing: 18) {
                    EmptyStateView(icon: "iphone.slash", title: "No cloud phones yet", message: "Buy one from the Shop and it'll appear here.")
                    Button("Go to Shop") { app.tab = .shop }.buttonStyle(.borderedProminent)
                }
                .frame(maxHeight: .infinity)
            } else {
                List(devices) { device in
                    NavigationLink(value: device) {
                        DeviceRow(device: device)
                    }
                    .disabled(!device.ready)
                }
                .listStyle(.insetGrouped)
            }
        }
        .navigationTitle("Cloud phones")
        .navigationDestination(for: Device.self) { device in
            DeviceDetailView(deviceId: device.id, title: device.name)
        }
        .refreshable { await load() }
        .task { await load() }
        .task(id: devices.contains { !$0.ready }) {
            // A phone that's still being set up appears on its own — same as the website.
            guard devices.contains(where: { !$0.ready }) else { return }
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 15_000_000_000)
                if Task.isCancelled { break }
                await load()
            }
        }
    }

    private func load() async {
        do {
            let response: DevicesResponse = try await APIClient.shared.get("devices")
            devices = response.devices
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

struct DeviceRow: View {
    let device: Device

    var body: some View {
        HStack(spacing: 14) {
            ZStack {
                RoundedRectangle(cornerRadius: 10).fill(Theme.gradient.opacity(0.15))
                if device.ready {
                    Image(systemName: "iphone").font(.title2).foregroundStyle(Theme.accent)
                } else {
                    ProgressView()
                }
            }
            .frame(width: 46, height: 46)

            VStack(alignment: .leading, spacing: 4) {
                Text(device.name).font(.headline)
                HStack(spacing: 6) {
                    if device.ready {
                        StatusPill.forStatus(device.expired == true ? "expired" : device.status)
                    } else {
                        StatusPill(text: "Setting up…", color: .orange)
                    }
                    if let v = device.androidVersion {
                        Text("Android \(v)").font(.caption).foregroundStyle(.secondary)
                    }
                }
                if let expires = device.expiresAt {
                    Text("Expires \(Format.when(expires))").font(.caption2).foregroundStyle(.secondary)
                }
            }
        }
        .padding(.vertical, 4)
    }
}
