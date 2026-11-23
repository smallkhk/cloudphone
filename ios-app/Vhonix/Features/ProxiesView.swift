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

    private var regions: [Country] {
        var seen = Set<String>()
        return store.products.compactMap { s in
            guard let code = s.countryCode, seen.insert(code).inserted else { return nil }
            return Country(code: code, name: s.countryName ?? code)
        }
        .sorted { $0.name < $1.name }
    }

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            List {
                Section {
                    if store.owned.isEmpty {
                        Text("No proxies yet. Buy one below or add your own.").foregroundStyle(.secondary)
                    }
                    ForEach(store.owned) { proxy in row(proxy) }
                    Button { adding = true } label: { Label("Add my own proxy", systemImage: "plus") }
                } header: {
                    Text("Your proxies")
                }

                Section {
                    Picker("Region", selection: $region) {
                        Text("Choose a region").tag("")
                        ForEach(regions) { r in Text("\(Format.flag(r.code)) \(r.name)").tag(r.code) }
                    }
                } header: {
                    Text("Buy a residential proxy")
                }

                if !region.isEmpty {
                    ProductCatalogSection(title: "Plans", products: store.products.filter { $0.countryCode == region }) { buying = $0 }
                }
            }
        }
        .navigationTitle("Proxies")
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

    private func row(_ p: CustomerProxy) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                VStack(alignment: .leading, spacing: 2) {
                    Text(p.title).font(.subheadline.weight(.semibold))
                    Text(p.isCustom ? "Your own proxy" : "\(Format.flag(p.countryCode)) \(p.countryName ?? p.product ?? "Residential proxy")")
                        .font(.caption).foregroundStyle(.secondary)
                }
                Spacer()
                if p.attachedPadCode != nil {
                    StatusPill(text: "Attached", color: .green)
                } else if !p.delivered {
                    StatusPill.forStatus(p.purchaseStatus ?? "processing")
                }
            }

            if let host = p.host {
                CopyRow(label: "Address", value: "\(host):\(p.port.map { String($0) } ?? "")", monospaced: true)
            }
            if let account = p.account?.nonEmpty {
                CopyRow(label: "Username", value: account, monospaced: true)
            }
            if let attached = p.attachedPadCode {
                LabeledContent("On phone", value: attached).font(.subheadline)
            }
            if let remarks = p.remarks?.nonEmpty {
                Text(remarks).font(.footnote).foregroundStyle(.secondary)
            }

            if p.delivered {
                HStack {
                    if p.attachedPadCode == nil {
                        Button("Attach") { attaching = p }
                    } else {
                        AsyncButton("Detach") { await act(p, "detach") }
                    }
                    AsyncButton("Test") { await act(p, "test") }
                    Spacer()
                    if p.isCustom {
                        Button("Edit") { editing = p }
                        Button("Remove", role: .destructive) { deleting = p }
                    }
                }
                .buttonStyle(.bordered).font(.caption)
            } else {
                Text("Being set up — usually ready within a few minutes.").font(.footnote).foregroundStyle(.secondary)
            }
        }
        .padding(.vertical, 6)
        .buttonStyle(.borderless)
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
        NavigationStack {
            Form {
                Section {
                    LabeledContent("Proxy", value: proxy.title)
                    if devices.isEmpty {
                        Text("You don't have a ready cloud phone yet.").foregroundStyle(.secondary)
                    } else {
                        DevicePicker(devices: devices, padCode: $padCode)
                    }
                }
                Section {
                    AsyncButton("Attach proxy") {
                        if await app.act("proxies/\(proxy.id)/attach", body: ["pad_code": padCode]) != nil {
                            await reload()
                            dismiss()
                        }
                    }
                    .disabled(padCode.isEmpty)
                }
            }
            .navigationTitle("Attach to a phone")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } } }
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

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    TextField("Nickname (optional)", text: $label)
                }
                Section {
                    ProxyFields(host: $host, port: $port, account: $account, password: $password, protocolName: $protocolName, mode: $mode,
                                passwordPlaceholder: existing?.hasPassword == true ? "Password (leave blank to keep)" : "Password (optional)")
                }
                Section("Notes") {
                    TextField("Remarks (optional)", text: $remarks, axis: .vertical).lineLimit(2...5)
                }
                Section {
                    AsyncButton(existing == nil ? "Add proxy" : "Save changes") { await save() }
                        .disabled(host.isEmpty || Int(port) == nil)
                }
            }
            .navigationTitle(existing == nil ? "Add proxy" : "Edit proxy")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } } }
            .onAppear {
                guard let p = existing, host.isEmpty else { return }
                label = p.label ?? ""
                host = p.host ?? ""
                port = p.port.map { String($0) } ?? ""
                account = p.account ?? ""
                protocolName = p.proxyName ?? "socks5"
                mode = p.proxyType ?? "proxy"
                remarks = p.remarks ?? ""
            }
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
