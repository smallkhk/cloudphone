import SwiftUI

struct DevicesView: View {
    @EnvironmentObject private var app: AppState
    @State private var devices: [Device] = []
    @State private var loading = true
    @State private var error: String?

    private var greeting: String {
        let h = Calendar.current.component(.hour, from: Date())
        let part = h < 12 ? "Good morning" : (h < 18 ? "Good afternoon" : "Good evening")
        if let first = app.user?.name.split(separator: " ").first { return "\(part), \(first)" }
        return part
    }

    var body: some View {
        LoadingView(isLoading: loading && devices.isEmpty, error: devices.isEmpty ? error : nil, retry: load) {
            Screen(animatedBackground: true) {
                Text(greeting)
                    .font(.subheadline.weight(.medium))
                    .foregroundStyle(Palette.text2)
                    .padding(.horizontal, 4)

                if devices.isEmpty {
                    EmptyStateView(icon: "iphone.gen3", title: "No cloud phones yet",
                                   message: "Get a real Android phone in the cloud — ready in minutes, controlled right here.",
                                   actionTitle: "Browse phones", colors: TileColors.indigo) { app.tab = .shop }
                        .glassCard()
                        .padding(.top, 20)
                } else {
                    stats

                    SectionTitle(title: "Your phones", subtitle: "\(devices.count) device\(devices.count == 1 ? "" : "s")")

                    ForEach(devices) { device in
                        if device.ready {
                            NavigationLink(value: device) { DeviceCard(device: device) }
                                .buttonStyle(.pressable)
                        } else {
                            DeviceCard(device: device)
                        }
                    }

                    Button { app.tab = .shop } label: {
                        HStack(spacing: 14) {
                            IconTile(symbol: "plus", colors: TileColors.indigo, size: 40)
                            VStack(alignment: .leading, spacing: 2) {
                                Text("Add another phone").font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                                Text("Pick a model and region in the Shop").font(.caption).foregroundStyle(Palette.text3)
                            }
                            Spacer()
                            Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Palette.text3)
                        }
                        .glassCard()
                    }
                    .buttonStyle(.pressable)
                }
            }
        }
        .navigationTitle("Phones")
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

    private var stats: some View {
        let ready = devices.filter { $0.ready && $0.expired != true }.count
        let setting = devices.filter { !$0.ready }.count
        let expiring = devices.filter { d in
            guard let date = Format.date(d.expiresAt) else { return false }
            return date.timeIntervalSinceNow < 3 * 86400 && date > Date()
        }.count

        return HStack(spacing: 10) {
            StatTile(value: "\(ready)", label: "Ready", icon: "checkmark.seal.fill", color: Palette.success)
            StatTile(value: "\(setting)", label: "Setting up", icon: "hourglass", color: Palette.warning)
            StatTile(value: "\(expiring)", label: "Expiring soon", icon: "clock.fill", color: expiring > 0 ? Palette.danger : Palette.accentText)
        }
    }

    private func load() async {
        do {
            let response: DevicesResponse = try await APIClient.shared.get("devices")
            withAnimation(.spring(response: 0.4, dampingFraction: 0.85)) { devices = response.devices }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

struct StatTile: View {
    let value: String
    let label: String
    let icon: String
    let color: Color

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Image(systemName: icon).font(.system(size: 15, weight: .semibold)).foregroundStyle(color)
            Text(value).font(Typo.rounded(.title2, .bold)).foregroundStyle(Palette.text)
            Text(label).font(.caption).foregroundStyle(Palette.text3).lineLimit(1).minimumScaleFactor(0.8)
        }
        .glassCard(padding: 14, radius: 18)
        .accessibilityElement(children: .combine)
    }
}

/// Phone card: live thumbnail + status.
struct DeviceCard: View {
    let device: Device

    private var statusText: String {
        if !device.ready { return "Setting up" }
        if device.expired == true { return "Expired" }
        return Format.status(device.status)
    }

    var body: some View {
        HStack(spacing: 16) {
            PhoneThumb(url: device.screenshotUrl, ready: device.ready)
                .frame(width: 62, height: 100)

            VStack(alignment: .leading, spacing: 8) {
                Text(device.name)
                    .font(Typo.rounded(.headline, .bold))
                    .foregroundStyle(Palette.text)
                    .lineLimit(1)

                HStack(spacing: 6) {
                    if device.ready {
                        StatusPill.forStatus(device.expired == true ? "expired" : device.status)
                    } else {
                        StatusPill(text: "Setting up…", color: Palette.warning, pulse: true)
                    }
                }

                HStack(spacing: 12) {
                    if let v = device.androidVersion {
                        Label("Android \(v)", systemImage: "cpu").labelStyle(CompactLabel())
                    }
                    if let exp = Format.date(device.expiresAt) {
                        Label(exp.formatted(.relative(presentation: .named)), systemImage: "clock").labelStyle(CompactLabel())
                    }
                }
            }

            Spacer(minLength: 0)

            if device.ready {
                Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Palette.text3)
            }
        }
        .glassCard(padding: 14)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(device.name), \(statusText)")
    }
}

struct CompactLabel: LabelStyle {
    func makeBody(configuration: Configuration) -> some View {
        HStack(spacing: 4) {
            configuration.icon.font(.caption2)
            configuration.title.font(.caption)
        }
        .foregroundStyle(Palette.text2)
    }
}

/// A small phone-shaped frame showing the latest screenshot.
struct PhoneThumb: View {
    let url: String?
    var ready = true

    var body: some View {
        RoundedRectangle(cornerRadius: 12, style: .continuous)
            .fill(LinearGradient(colors: [Color(hex: 0x1B1D2B), Color(hex: 0x0C0D14)], startPoint: .top, endPoint: .bottom))
            .overlay {
                if let url = url.flatMap(URL.init(string:)) {
                    AsyncImage(url: url) { phase in
                        if let image = phase.image {
                            image.resizable().scaledToFill()
                        } else {
                            Image(systemName: "iphone.gen3").foregroundStyle(Palette.text3)
                        }
                    }
                } else if ready {
                    Image(systemName: "iphone.gen3").font(.title3).foregroundStyle(Palette.text3)
                } else {
                    ProgressView().tint(Palette.accentText)
                }
            }
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: 12, style: .continuous).strokeBorder(Color.white.opacity(0.16), lineWidth: 1))
            .shadow(color: .black.opacity(0.4), radius: 8, y: 4)
            .accessibilityHidden(true)
    }
}
