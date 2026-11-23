import SwiftUI

/// Shared header for a settings screen.
struct SettingsHeader: View {
    let icon: String
    var colors: (Color, Color) = TileColors.indigo
    let title: String
    let text: String

    var body: some View {
        HStack(alignment: .top, spacing: 14) {
            IconTile(symbol: icon, colors: colors, size: 48)
            VStack(alignment: .leading, spacing: 4) {
                Text(title).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                Text(text).font(.subheadline).foregroundStyle(Palette.text2).fixedSize(horizontal: false, vertical: true)
            }
        }
        .padding(.horizontal, 4)
        .padding(.top, 4)
    }
}

struct SimSettingsView: View {
    let deviceId: Int
    let countries: [Country]
    let current: String?
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var code = ""

    var body: some View {
        Screen {
            SettingsHeader(icon: "simcard.fill", colors: TileColors.green, title: "New number & SIM",
                           text: "Gives the phone a fresh phone number, SIM card and carrier from the country you pick.")

            VStack(alignment: .leading, spacing: 16) {
                SelectRow(label: "Country",
                          options: countries.map { PickOption(id: $0.code, title: $0.name, subtitle: $0.code, leading: Format.flag($0.code)) },
                          selection: $code, placeholder: "Choose a country")
                NoteView(text: "The phone restarts to apply the new SIM. Apps stay installed.", icon: "arrow.clockwise", tint: Palette.warning)
            }
            .glassCard(padding: 18)

            AsyncButton {
                if await app.act("devices/\(deviceId)/sim", body: ["country_code": code]) != nil {
                    await reload()
                    dismiss()
                }
            } label: { Text("Apply new SIM") }
            .buttonStyle(.primary)
            .disabled(code.isEmpty)
        }
        .navigationTitle("Phone number & SIM")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            if code.isEmpty, let current, countries.contains(where: { $0.code == current.uppercased() }) {
                code = current.uppercased()
            }
        }
    }
}

struct GpsSettingsView: View {
    let deviceId: Int
    let latitude: Double?
    let longitude: Double?
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var lat = ""
    @State private var lon = ""

    private struct Preset: Identifiable {
        let flag: String
        let city: String
        let lat: Double
        let lon: Double
        var id: String { city }
    }

    private static let presets: [Preset] = [
        Preset(flag: "🇺🇸", city: "New York", lat: 40.7128, lon: -74.0060),
        Preset(flag: "🇺🇸", city: "Los Angeles", lat: 34.0522, lon: -118.2437),
        Preset(flag: "🇬🇧", city: "London", lat: 51.5074, lon: -0.1278),
        Preset(flag: "🇩🇪", city: "Berlin", lat: 52.5200, lon: 13.4050),
        Preset(flag: "🇫🇷", city: "Paris", lat: 48.8566, lon: 2.3522),
        Preset(flag: "🇦🇪", city: "Dubai", lat: 25.2048, lon: 55.2708),
        Preset(flag: "🇸🇬", city: "Singapore", lat: 1.3521, lon: 103.8198),
        Preset(flag: "🇯🇵", city: "Tokyo", lat: 35.6762, lon: 139.6503),
        Preset(flag: "🇭🇰", city: "Hong Kong", lat: 22.3193, lon: 114.1694),
        Preset(flag: "🇧🇷", city: "São Paulo", lat: -23.5505, lon: -46.6333),
        Preset(flag: "🇦🇺", city: "Sydney", lat: -33.8688, lon: 151.2093),
        Preset(flag: "🇳🇬", city: "Lagos", lat: 6.5244, lon: 3.3792),
    ]

    var body: some View {
        Screen {
            SettingsHeader(icon: "location.fill", colors: TileColors.cyan, title: "GPS location",
                           text: "Apps on the phone will see this as its location.")

            VStack(alignment: .leading, spacing: 12) {
                Text("Quick pick").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
                ScrollView(.horizontal, showsIndicators: false) {
                    HStack(spacing: 8) {
                        ForEach(Self.presets) { p in
                            Button {
                                Haptics.select()
                                lat = String(p.lat)
                                lon = String(p.lon)
                            } label: {
                                Text("\(p.flag) \(p.city)")
                            }
                            .buttonStyle(.chip(String(p.lat) == lat && String(p.lon) == lon ? Palette.accentText : Palette.text))
                        }
                    }
                }

                HStack(spacing: 12) {
                    GlassField(label: "Latitude", text: $lat, prompt: "40.7128", keyboard: .numbersAndPunctuation)
                    GlassField(label: "Longitude", text: $lon, prompt: "-74.0060", keyboard: .numbersAndPunctuation)
                }
                NoteView(text: "Tip: in Google Maps, press and hold any spot to copy its coordinates.", icon: "lightbulb")
            }
            .glassCard(padding: 18)

            AsyncButton {
                guard let la = Double(lat.replacingOccurrences(of: ",", with: ".")),
                      let lo = Double(lon.replacingOccurrences(of: ",", with: ".")) else {
                    app.show("Enter numbers for latitude and longitude.", error: true)
                    return
                }
                if await app.act("devices/\(deviceId)/gps", body: ["latitude": la, "longitude": lo]) != nil {
                    await reload()
                    dismiss()
                }
            } label: { Text("Set location") }
            .buttonStyle(.primary)
            .disabled(lat.isEmpty || lon.isEmpty)
        }
        .navigationTitle("GPS location")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            if lat.isEmpty, let latitude { lat = String(latitude) }
            if lon.isEmpty, let longitude { lon = String(longitude) }
        }
    }
}

struct LocaleSettingsView: View {
    let deviceId: Int
    let timezone: String?
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var zone = ""
    @State private var language = ""
    @State private var region = ""

    private static let languages: [(String, String)] = [
        ("en", "English"), ("es", "Spanish"), ("pt", "Portuguese"), ("fr", "French"),
        ("de", "German"), ("it", "Italian"), ("ru", "Russian"), ("ar", "Arabic"), ("tr", "Turkish"),
        ("zh", "Chinese"), ("ja", "Japanese"), ("ko", "Korean"), ("vi", "Vietnamese"), ("th", "Thai"),
        ("id", "Indonesian"), ("ms", "Malay"), ("hi", "Hindi"), ("nl", "Dutch"), ("pl", "Polish"),
    ]

    // Built once, not on every redraw (~400 timezones).
    private static let zones: [PickOption] = [PickOption(id: "", title: "Don't change")] + TimeZone.knownTimeZoneIdentifiers.map { id in
        PickOption(id: id, title: id.replacingOccurrences(of: "_", with: " "), subtitle: TimeZone(identifier: id)?.abbreviation())
    }
    private var zones: [PickOption] { Self.zones }

    private var languageOptions: [PickOption] {
        [PickOption(id: "", title: "Don't change")] + Self.languages.map { PickOption(id: $0.0, title: $0.1, subtitle: $0.0) }
    }

    var body: some View {
        Screen {
            SettingsHeader(icon: "globe", colors: TileColors.indigo, title: "Language & timezone",
                           text: "Match the phone's clock and language to the country you're working in.")

            VStack(alignment: .leading, spacing: 16) {
                SelectRow(label: "Timezone", options: zones, selection: $zone, placeholder: "Don't change")
                SelectRow(label: "Language", options: languageOptions, selection: $language, placeholder: "Don't change")
                GlassField(label: "Region code (optional)", text: $region, prompt: "e.g. US", capitalization: .characters)
            }
            .glassCard(padding: 18)

            AsyncButton {
                var body: [String: Any] = [:]
                if !zone.isEmpty { body["timezone"] = zone }
                if !language.isEmpty { body["language"] = language }
                if let r = region.nonEmpty { body["language_country"] = r.uppercased() }
                if await app.act("devices/\(deviceId)/locale", body: body) != nil {
                    await reload()
                    dismiss()
                }
            } label: { Text("Apply") }
            .buttonStyle(.primary)
            .disabled(zone.isEmpty && language.isEmpty)
        }
        .navigationTitle("Language & timezone")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            if zone.isEmpty, let timezone, TimeZone(identifier: timezone) != nil { zone = timezone }
        }
    }
}

/// The proxy form fields, shared by the device panel, checkout and "My proxies".
struct ProxyFields: View {
    @Binding var host: String
    @Binding var port: String
    @Binding var account: String
    @Binding var password: String
    @Binding var protocolName: String
    @Binding var mode: String
    var passwordPlaceholder = "Optional"

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(alignment: .bottom, spacing: 12) {
                GlassField(label: "Host / IP", text: $host, prompt: "proxy.example.com", keyboard: .URL)
                GlassField(label: "Port", text: $port, prompt: "1080", keyboard: .numberPad)
                    .frame(width: 104)
            }
            GlassField(label: "Username", text: $account, prompt: "Optional")
            GlassField(label: "Password", text: $password, prompt: passwordPlaceholder, secure: true)

            VStack(alignment: .leading, spacing: 7) {
                Text("Protocol").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
                PillPicker(options: [PickOption(id: "socks5", title: "SOCKS5"), PickOption(id: "http-relay", title: "HTTP / HTTPS")],
                           selection: $protocolName)
            }
            VStack(alignment: .leading, spacing: 7) {
                Text("Mode").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
                PillPicker(options: [PickOption(id: "proxy", title: "Proxy"), PickOption(id: "vpn", title: "VPN")],
                           selection: $mode)
            }
        }
    }
}

struct DeviceProxyView: View {
    let deviceId: Int

    @EnvironmentObject private var app: AppState
    @State private var host = ""
    @State private var port = ""
    @State private var account = ""
    @State private var password = ""
    @State private var protocolName = "socks5"
    @State private var mode = "proxy"
    @State private var confirmClear = false

    private var payload: [String: Any] {
        var b: [String: Any] = [
            "ip": host.trimmingCharacters(in: .whitespaces),
            "port": Int(port) ?? 0,
            "proxy_name": protocolName,
            "proxy_type": mode,
        ]
        if let a = account.nonEmpty { b["account"] = a }
        if let p = password.nonEmpty { b["password"] = p }
        return b
    }

    private var valid: Bool { !host.isEmpty && Int(port) != nil }

    var body: some View {
        Screen {
            SettingsHeader(icon: "network", colors: TileColors.orange, title: "Proxy",
                           text: "Send this phone's internet traffic through your own proxy.")

            ProxyFields(host: $host, port: $port, account: $account, password: $password, protocolName: $protocolName, mode: $mode)
                .glassCard(padding: 18)

            NoteView(text: "Test first — a proxy that doesn't work leaves the phone offline until you clear it.",
                     icon: "exclamationmark.triangle", tint: Palette.warning)

            HStack(spacing: 12) {
                AsyncButton {
                    await app.act("devices/\(deviceId)/proxy/test", body: payload)
                } label: { Label("Test", systemImage: "bolt.horizontal") }
                .buttonStyle(.glass(Palette.accentText))

                AsyncButton {
                    await app.act("devices/\(deviceId)/proxy", body: payload)
                } label: { Text("Apply") }
                .buttonStyle(.primary)
            }
            .disabled(!valid)

            Button(role: .destructive) { confirmClear = true } label: {
                Label("Clear proxy from this phone", systemImage: "xmark.circle")
            }
            .buttonStyle(.glass(Palette.danger))
        }
        .navigationTitle("Proxy")
        .navigationBarTitleDisplayMode(.inline)
        .confirmationDialog("Remove the proxy from this phone?", isPresented: $confirmClear, titleVisibility: .visible) {
            Button("Clear proxy", role: .destructive) {
                Task { await app.act("devices/\(deviceId)/proxy", method: "DELETE") }
            }
        }
    }
}

struct AppsView: View {
    let deviceId: Int
    let apps: [InstalledApp]
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @State private var apkURL = ""
    @State private var packageName = ""
    @State private var query = ""
    @State private var uninstallTarget: InstalledApp?

    private var filtered: [InstalledApp] {
        let q = query.trimmingCharacters(in: .whitespaces)
        return q.isEmpty ? apps : apps.filter { $0.name.localizedCaseInsensitiveContains(q) || $0.packageName.localizedCaseInsensitiveContains(q) }
    }

    var body: some View {
        Screen {
            VStack(alignment: .leading, spacing: 14) {
                HStack(spacing: 12) {
                    IconTile(symbol: "arrow.down.app.fill", colors: TileColors.pink, size: 40)
                    VStack(alignment: .leading, spacing: 2) {
                        Text("Install from a link").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                        Text("Paste a direct link to an .apk file").font(.caption).foregroundStyle(Palette.text3)
                    }
                }
                GlassField(label: "APK link", text: $apkURL, prompt: "https://…/app.apk", keyboard: .URL)
                GlassField(label: "Package name (optional)", text: $packageName, prompt: "com.example.app")
                AsyncButton {
                    var body: [String: Any] = ["apk_url": apkURL.trimmingCharacters(in: .whitespaces)]
                    if let p = packageName.nonEmpty { body["package_name"] = p }
                    if await app.act("devices/\(deviceId)/apps/install", body: body) != nil {
                        apkURL = ""
                        packageName = ""
                    }
                } label: { Text("Install app") }
                .buttonStyle(.primary)
                .disabled(URL(string: apkURL)?.scheme == nil)
            }
            .glassCard(padding: 18)

            SectionTitle(title: "Installed", subtitle: "\(apps.count) apps",
                         trailing: AnyView(AsyncButton { await refresh() } label: { Label("Refresh", systemImage: "arrow.clockwise") }
                            .buttonStyle(.chip(Palette.accentText))))

            if apps.count > 6 {
                HStack(spacing: 10) {
                    Image(systemName: "magnifyingglass").foregroundStyle(Palette.text3)
                    TextField("", text: $query, prompt: Text("Search apps").foregroundColor(Palette.text3))
                        .foregroundStyle(Palette.text)
                        .autocorrectionDisabled()
                        .textInputAutocapitalization(.never)
                }
                .padding(.horizontal, 14)
                .frame(minHeight: 46)
                .background(Capsule().fill(Palette.surface))
                .overlay(Capsule().strokeBorder(Palette.stroke, lineWidth: 1))
            }

            if apps.isEmpty {
                EmptyStateView(icon: "square.grid.3x3.fill", title: "No apps listed yet", message: "Tap Refresh to load what's installed.", colors: TileColors.pink)
                    .glassCard()
            } else {
                VStack(spacing: 4) {
                    ForEach(Array(filtered.enumerated()), id: \.element.id) { index, item in
                        if index > 0 { CardDivider() }
                        appRow(item)
                    }
                }
                .glassCard(padding: 12)
            }
        }
        .navigationTitle("Apps")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await refresh() }
        .confirmationDialog("Uninstall \(uninstallTarget?.name ?? "this app")?", isPresented: Binding(
            get: { uninstallTarget != nil }, set: { if !$0 { uninstallTarget = nil } }
        ), titleVisibility: .visible) {
            Button("Uninstall", role: .destructive) {
                if let target = uninstallTarget { Task { await act(target, "uninstall") } }
            }
        }
    }

    private func appRow(_ item: InstalledApp) -> some View {
        HStack(spacing: 12) {
            Text(String(item.name.prefix(1)).uppercased())
                .font(Typo.rounded(.headline, .bold))
                .foregroundStyle(.white)
                .frame(width: 40, height: 40)
                .background(RoundedRectangle(cornerRadius: 11, style: .continuous).fill(Theme.tile(appColor(item).0, appColor(item).1)))
            VStack(alignment: .leading, spacing: 2) {
                Text(item.name).font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text).lineLimit(1)
                Text(item.packageName + (item.version.map { " · v\($0)" } ?? ""))
                    .font(.caption).foregroundStyle(Palette.text3).lineLimit(1)
            }
            Spacer()
            Menu {
                Button { Task { await act(item, "start") } } label: { Label("Open", systemImage: "play") }
                Button { Task { await act(item, "stop") } } label: { Label("Stop", systemImage: "stop") }
                Button { Task { await act(item, "restart") } } label: { Label("Restart", systemImage: "arrow.clockwise") }
                Divider()
                Button(role: .destructive) { uninstallTarget = item } label: { Label("Uninstall", systemImage: "trash") }
            } label: {
                Image(systemName: "ellipsis")
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(Palette.text2)
                    .frame(width: 44, height: 44)
                    .background(Circle().fill(Palette.surfaceStrong).frame(width: 34, height: 34))
            }
            .accessibilityLabel("Actions for \(item.name)")
        }
        .frame(minHeight: 50)
    }

    private func appColor(_ item: InstalledApp) -> (Color, Color) {
        let all = [TileColors.indigo, TileColors.cyan, TileColors.green, TileColors.orange, TileColors.pink, TileColors.gold]
        return all[abs(item.packageName.hashValue) % all.count]
    }

    private func act(_ item: InstalledApp, _ action: String) async {
        if await app.act("devices/\(deviceId)/apps/action", body: ["package_name": item.packageName, "action": action]) != nil,
           action == "uninstall" {
            await refresh()
        }
    }

    private func refresh() async {
        _ = await app.act("devices/\(deviceId)/apps/refresh")
        await reload()
    }
}

struct AdbView: View {
    let deviceId: Int

    @EnvironmentObject private var app: AppState
    @State private var lastMessage: String?

    var body: some View {
        Screen {
            SettingsHeader(icon: "terminal.fill", colors: TileColors.slate, title: "ADB",
                           text: "Lets a computer control this phone with Android developer tools. Leave it off unless you need it.")

            HStack(spacing: 12) {
                AsyncButton { await toggle(false) } label: { Text("Disable") }
                    .buttonStyle(.glass(Palette.danger))
                AsyncButton { await toggle(true) } label: { Text("Enable ADB") }
                    .buttonStyle(.primary)
            }

            if let lastMessage {
                VStack(alignment: .leading, spacing: 10) {
                    Text("Result").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
                    CopyRow(label: "Connection", value: lastMessage, monospaced: true)
                }
                .glassCard(padding: 18, highlight: true)
                .transition(.move(edge: .bottom).combined(with: .opacity))
            }
        }
        .navigationTitle("ADB")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func toggle(_ enable: Bool) async {
        if let result = await app.act("devices/\(deviceId)/adb", body: ["enable": enable]) {
            withAnimation(.spring()) { lastMessage = result.message }
        }
    }
}
