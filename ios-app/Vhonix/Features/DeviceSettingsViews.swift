import SwiftUI

struct SimSettingsView: View {
    let deviceId: Int
    let countries: [Country]
    let current: String?
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var code = ""

    var body: some View {
        Form {
            Section {
                Picker("Country", selection: $code) {
                    Text("Choose").tag("")
                    ForEach(countries) { c in
                        Text("\(Format.flag(c.code)) \(c.name)").tag(c.code)
                    }
                }
                .pickerStyle(.navigationLink)
            } footer: {
                Text("Gives the phone a new phone number, SIM and carrier from this country. The phone restarts.")
            }

            Section {
                AsyncButton("Apply new SIM") {
                    if await app.act("devices/\(deviceId)/sim", body: ["country_code": code]) != nil {
                        await reload()
                        dismiss()
                    }
                }
                .disabled(code.isEmpty)
            }
        }
        .navigationTitle("Phone number & SIM")
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

    var body: some View {
        Form {
            Section {
                TextField("Latitude (e.g. 40.7128)", text: $lat).keyboardType(.numbersAndPunctuation)
                TextField("Longitude (e.g. -74.0060)", text: $lon).keyboardType(.numbersAndPunctuation)
            } footer: {
                Text("Tip: in Google Maps, press and hold a spot to copy its coordinates.")
            }

            Section {
                AsyncButton("Set location") {
                    guard let la = Double(lat.replacingOccurrences(of: ",", with: ".")),
                          let lo = Double(lon.replacingOccurrences(of: ",", with: ".")) else {
                        app.show("Enter numbers for latitude and longitude.", error: true)
                        return
                    }
                    if await app.act("devices/\(deviceId)/gps", body: ["latitude": la, "longitude": lo]) != nil {
                        await reload()
                        dismiss()
                    }
                }
                .disabled(lat.isEmpty || lon.isEmpty)
            }
        }
        .navigationTitle("GPS location")
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
        ("", "Don't change"), ("en", "English"), ("es", "Spanish"), ("pt", "Portuguese"), ("fr", "French"),
        ("de", "German"), ("it", "Italian"), ("ru", "Russian"), ("ar", "Arabic"), ("tr", "Turkish"),
        ("zh", "Chinese"), ("ja", "Japanese"), ("ko", "Korean"), ("vi", "Vietnamese"), ("th", "Thai"),
        ("id", "Indonesian"), ("ms", "Malay"), ("hi", "Hindi"), ("nl", "Dutch"), ("pl", "Polish"),
    ]

    var body: some View {
        Form {
            Section("Timezone") {
                Picker("Timezone", selection: $zone) {
                    Text("Don't change").tag("")
                    ForEach(TimeZone.knownTimeZoneIdentifiers, id: \.self) { Text($0).tag($0) }
                }
                .pickerStyle(.navigationLink)
            }

            Section {
                Picker("Language", selection: $language) {
                    ForEach(Self.languages, id: \.0) { Text($0.1).tag($0.0) }
                }
                TextField("Region code (optional, e.g. US)", text: $region)
                    .textInputAutocapitalization(.characters)
                    .autocorrectionDisabled()
            } header: {
                Text("Language")
            }

            Section {
                AsyncButton("Apply") {
                    var body: [String: Any] = [:]
                    if !zone.isEmpty { body["timezone"] = zone }
                    if !language.isEmpty { body["language"] = language }
                    if let r = region.nonEmpty { body["language_country"] = r.uppercased() }
                    if await app.act("devices/\(deviceId)/locale", body: body) != nil {
                        await reload()
                        dismiss()
                    }
                }
                .disabled(zone.isEmpty && language.isEmpty)
            }
        }
        .navigationTitle("Language & timezone")
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
    var passwordPlaceholder = "Password (optional)"

    var body: some View {
        TextField("Host / IP", text: $host)
            .textInputAutocapitalization(.never).autocorrectionDisabled().keyboardType(.URL)
        TextField("Port", text: $port).keyboardType(.numberPad)
        TextField("Username (optional)", text: $account)
            .textInputAutocapitalization(.never).autocorrectionDisabled()
        SecureField(passwordPlaceholder, text: $password)
        Picker("Protocol", selection: $protocolName) {
            Text("SOCKS5").tag("socks5")
            Text("HTTP / HTTPS").tag("http-relay")
        }
        Picker("Mode", selection: $mode) {
            Text("Proxy").tag("proxy")
            Text("VPN").tag("vpn")
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

    private var body_: [String: Any] {
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

    var body: some View {
        Form {
            Section {
                ProxyFields(host: $host, port: $port, account: $account, password: $password, protocolName: $protocolName, mode: $mode)
            } footer: {
                Text("Test first — a proxy that doesn't work leaves the phone without internet until you clear it.")
            }

            Section {
                AsyncButton("Test proxy") {
                    await app.act("devices/\(deviceId)/proxy/test", body: body_)
                }
                AsyncButton("Apply to this phone") {
                    await app.act("devices/\(deviceId)/proxy", body: body_)
                }
            }
            .disabled(host.isEmpty || Int(port) == nil)

            Section {
                Button("Clear proxy", role: .destructive) { confirmClear = true }
            } footer: {
                Text("Puts the phone back on its normal connection.")
            }
        }
        .navigationTitle("Proxy")
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
    @State private var uninstallTarget: InstalledApp?

    var body: some View {
        List {
            Section {
                TextField("Link to an .apk file", text: $apkURL)
                    .textInputAutocapitalization(.never).autocorrectionDisabled().keyboardType(.URL)
                TextField("Package name (optional)", text: $packageName)
                    .textInputAutocapitalization(.never).autocorrectionDisabled()
                AsyncButton("Install app") {
                    var body: [String: Any] = ["apk_url": apkURL.trimmingCharacters(in: .whitespaces)]
                    if let p = packageName.nonEmpty { body["package_name"] = p }
                    if await app.act("devices/\(deviceId)/apps/install", body: body) != nil {
                        apkURL = ""
                        packageName = ""
                    }
                }
                .disabled(URL(string: apkURL)?.scheme == nil)
            } header: {
                Text("Install from a link")
            }

            Section {
                if apps.isEmpty {
                    Text("No apps listed yet. Pull down or tap Refresh.").foregroundStyle(.secondary)
                }
                ForEach(apps) { item in
                    Menu {
                        Button { Task { await act(item, "start") } } label: { Label("Open", systemImage: "play") }
                        Button { Task { await act(item, "stop") } } label: { Label("Stop", systemImage: "stop") }
                        Button { Task { await act(item, "restart") } } label: { Label("Restart", systemImage: "arrow.clockwise") }
                        Button(role: .destructive) { uninstallTarget = item } label: { Label("Uninstall", systemImage: "trash") }
                    } label: {
                        VStack(alignment: .leading, spacing: 2) {
                            Text(item.name).foregroundStyle(.primary)
                            Text(item.packageName + (item.version.map { " · v\($0)" } ?? ""))
                                .font(.caption).foregroundStyle(.secondary)
                        }
                    }
                }
            } header: {
                HStack {
                    Text("Installed apps")
                    Spacer()
                    AsyncButton("Refresh") { await refresh() }.font(.caption)
                }
            }
        }
        .navigationTitle("Apps")
        .refreshable { await refresh() }
        .confirmationDialog("Uninstall \(uninstallTarget?.name ?? "this app")?", isPresented: Binding(
            get: { uninstallTarget != nil }, set: { if !$0 { uninstallTarget = nil } }
        ), titleVisibility: .visible) {
            Button("Uninstall", role: .destructive) {
                if let target = uninstallTarget { Task { await act(target, "uninstall") } }
            }
        }
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
        Form {
            Section {
                AsyncButton("Enable ADB") { await toggle(true) }
                AsyncButton("Disable ADB", role: .destructive) { await toggle(false) }
            } footer: {
                Text("ADB lets a computer control the phone with developer tools. Leave it off unless you need it.")
            }

            if let lastMessage {
                Section("Result") {
                    CopyRow(label: "Message", value: lastMessage, monospaced: true)
                }
            }
        }
        .navigationTitle("ADB")
    }

    private func toggle(_ enable: Bool) async {
        if let result = await app.act("devices/\(deviceId)/adb", body: ["enable": enable]) {
            lastMessage = result.message
        }
    }
}
