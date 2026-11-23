import SwiftUI

struct ProxiesView: View {
    @StateObject private var store = ProductStore<CustomerProxy>(type: "proxies")
    @EnvironmentObject private var app: AppState
    @State private var region = ""
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?
    @State private var attaching: CustomerProxy?
    @State private var editing: CustomerProxy?
    @State private var adding = false
    @State private var deleting: CustomerProxy?

    private var regions: [PickOption] {
        var seen = Set<String>()
        return store.products.compactMap { s -> PickOption? in
            guard let code = s.countryCode, seen.insert(code).inserted else { return nil }
            return PickOption(id: code, title: s.countryName ?? code, subtitle: code, leading: Format.flag(code))
        }
        .sorted { $0.title < $1.title }
    }

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            Screen {
                ProductHero(icon: "network", colors: TileColors.orange, title: "Proxies",
                            text: "Residential IPs you can move between your phones — or add your own.")

                SectionTitle(title: "Your proxies", trailing: AnyView(
                    Button { adding = true } label: { Label("Add own", systemImage: "plus") }
                        .buttonStyle(.chip(Palette.accentText))
                ))

                if store.owned.isEmpty {
                    EmptyStateView(icon: "network", title: "No proxies yet", message: "Buy one below or add your own.", colors: TileColors.orange)
                        .glassCard()
                }
                ForEach(store.owned) { proxy in card(proxy) }

                SectionTitle(title: "Buy a residential proxy")
                SelectRow(label: "Region", options: regions, selection: $region, placeholder: "Choose a region")
                    .glassCard(padding: 14)

                if !region.isEmpty {
                    ProductCatalogSection(title: "Plans", products: store.products.filter { $0.countryCode == region }) { buying = $0 }
                }
            }
        }
        .navigationTitle("Proxies")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await store.load() }
        .task { await store.load() }
        .sheet(item: $buying) { sku in
            BuyProductView(sku: sku) { id in if let id { payOrder = OrderRoute(id: id) } }
        }
        .sheet(item: $attaching) { proxy in
            AttachProxyView(proxy: proxy, devices: store.devices) { await store.load() }
        }
        .sheet(item: $editing) { proxy in
            ProxyEditorView(existing: proxy) { await store.load() }
        }
        .sheet(isPresented: $adding) {
            ProxyEditorView(existing: nil) { await store.load() }
        }
        .confirmationDialog("Remove \(deleting?.title ?? "this proxy")?", isPresented: Binding(get: { deleting != nil }, set: { if !$0 { deleting = nil } }), titleVisibility: .visible) {
            Button("Remove", role: .destructive) {
                if let p = deleting { Task { await act(p, "", method: "DELETE") } }
            }
        }
        .navigationDestination(unwrapping: $payOrder) { route in OrderDetailView(orderId: route.id) }
    }

    private func card(_ p: CustomerProxy) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 12) {
                Text(p.isCustom ? "🔧" : Format.flag(p.countryCode)).font(.title2)
                    .frame(width: 44, height: 44)
                    .background(Circle().fill(Palette.surfaceStrong))
                VStack(alignment: .leading, spacing: 2) {
                    Text(p.title).font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text).lineLimit(1)
                    Text(p.isCustom ? "Your own proxy" : (p.countryName ?? p.product ?? "Residential proxy"))
                        .font(.caption).foregroundStyle(Palette.text3)
                }
                Spacer()
                if p.attachedPadCode != nil {
                    StatusPill(text: "In use", color: Palette.success, pulse: true)
                } else if !p.delivered {
                    StatusPill.forStatus(p.purchaseStatus ?? "processing")
                } else {
                    StatusPill(text: "Idle", color: Palette.text2)
                }
            }

            if let host = p.host {
                CopyRow(label: "Address", value: "\(host):\(p.port.map { String($0) } ?? "")", monospaced: true)
            }
            if let account = p.account?.nonEmpty {
                CopyRow(label: "Username", value: account, monospaced: true)
            }
            if let attached = p.attachedPadCode {
                InfoRow(label: "On phone", value: attached)
            }
            if let remarks = p.remarks?.nonEmpty {
                NoteView(text: remarks, icon: "note.text")
            }

            if p.delivered {
                HStack(spacing: 8) {
                    if p.attachedPadCode == nil {
                        Button { attaching = p } label: { Label("Attach", systemImage: "link") }
                            .buttonStyle(.chip(Palette.accentText, filled: true))
                    } else {
                        AsyncButton { await act(p, "detach") } label: { Label("Detach", systemImage: "link.badge.plus") }
                            .buttonStyle(.chip(Palette.warning))
                    }
                    AsyncButton { await act(p, "test") } label: { Label("Test", systemImage: "bolt.horizontal") }
                        .buttonStyle(.chip(Palette.text))
                    Spacer()
                    if p.isCustom {
                        Button { editing = p } label: { Image(systemName: "pencil") }
                            .buttonStyle(.chip(Palette.text))
                            .accessibilityLabel("Edit proxy")
                        Button { deleting = p } label: { Image(systemName: "trash") }
                            .buttonStyle(.chip(Palette.danger))
                            .accessibilityLabel("Remove proxy")
                    }
                }
            } else {
                NoteView(text: "Being set up — usually ready within a few minutes.", icon: "hourglass", tint: Palette.warning)
            }
        }
        .glassCard(padding: 16)
    }

    private func act(_ p: CustomerProxy, _ action: String, method: String = "POST") async {
        let path = action.isEmpty ? "proxies/\(p.id)" : "proxies/\(p.id)/\(action)"
        if await app.act(path, method: method) != nil, action != "test" {
            await store.load()
        }
    }
}

struct AttachProxyView: View {
    let proxy: CustomerProxy
    let devices: [DeviceRef]
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var padCode = ""

    var body: some View {
        SheetScaffold(title: "Attach to a phone") {
            VStack(alignment: .leading, spacing: 6) {
                Text("Proxy").font(.caption).foregroundStyle(Palette.text3)
                Text(proxy.title).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
            }
            .glassCard(highlight: true)

            Group {
                if devices.isEmpty {
                    NoteView(text: "You don't have a ready cloud phone yet.", icon: "iphone.slash", tint: Palette.warning)
                } else {
                    DevicePicker(devices: devices, padCode: $padCode)
                }
            }
            .glassCard(padding: 18)

            AsyncButton {
                if await app.act("proxies/\(proxy.id)/attach", body: ["pad_code": padCode]) != nil {
                    await reload()
                    dismiss()
                }
            } label: { Text("Attach proxy") }
            .buttonStyle(.primary)
            .disabled(padCode.isEmpty)
        }
    }
}

struct ProxyEditorView: View {
    let existing: CustomerProxy?
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var label = ""
    @State private var host = ""
    @State private var port = ""
    @State private var account = ""
    @State private var password = ""
    @State private var protocolName = "socks5"
    @State private var mode = "proxy"
    @State private var remarks = ""
    @State private var loaded = false

    var body: some View {
        SheetScaffold(title: existing == nil ? "Add proxy" : "Edit proxy") {
            GlassField(label: "Nickname (optional)", text: $label, prompt: "e.g. US residential 1", capitalization: .sentences)
                .glassCard(padding: 18)

            ProxyFields(host: $host, port: $port, account: $account, password: $password, protocolName: $protocolName, mode: $mode,
                        passwordPlaceholder: existing?.hasPassword == true ? "Leave blank to keep" : "Optional")
                .glassCard(padding: 18)

            GlassField(label: "Remarks (optional)", text: $remarks, prompt: "Anything to remember", capitalization: .sentences)
                .glassCard(padding: 18)

            AsyncButton { await save() } label: { Text(existing == nil ? "Add proxy" : "Save changes") }
                .buttonStyle(.primary)
                .disabled(host.isEmpty || Int(port) == nil)
        }
        .onAppear {
            guard let p = existing, !loaded else { return }
            loaded = true
            label = p.label ?? ""
            host = p.host ?? ""
            port = p.port.map { String($0) } ?? ""
            account = p.account ?? ""
            protocolName = p.proxyName ?? "socks5"
            mode = p.proxyType ?? "proxy"
            remarks = p.remarks ?? ""
        }
    }

    private func save() async {
        var body: [String: Any] = [
            "host": host.trimmingCharacters(in: .whitespaces),
            "port": Int(port) ?? 0,
            "proxy_name": protocolName,
            "proxy_type": mode,
        ]
        if let v = label.nonEmpty { body["label"] = v }
        if let v = account.nonEmpty { body["account"] = v }
        if let v = password.nonEmpty { body["password"] = v }
        if let v = remarks.nonEmpty { body["remarks"] = v }

        let path = existing.map { "proxies/\($0.id)" } ?? "proxies"
        if await app.act(path, method: existing == nil ? "POST" : "PUT", body: body) != nil {
            await reload()
            dismiss()
        }
    }
}
