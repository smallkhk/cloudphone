import SwiftUI

struct ShopView: View {
    @EnvironmentObject private var app: AppState
    @State private var catalog: Catalog?
    @State private var loading = true
    @State private var error: String?
    @State private var android = ""
    @State private var buying: Sku?

    private var versions: [String] {
        Array(Set((catalog?.plans ?? []).compactMap(\.androidVersion))).sorted { $0.localizedStandardCompare($1) == .orderedDescending }
    }

    private var groups: [(String, [Sku])] {
        let plans = (catalog?.plans ?? []).filter { android.isEmpty || $0.androidVersion == android }
        var order: [String] = []
        var byName: [String: [Sku]] = [:]
        for p in plans {
            if byName[p.name] == nil { order.append(p.name) }
            byName[p.name, default: []].append(p)
        }
        return order.map { ($0, byName[$0] ?? []) }
    }

    var body: some View {
        LoadingView(isLoading: loading && catalog == nil, error: catalog == nil ? error : nil, retry: load) {
            List {
                Section {
                    HStack {
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Wallet balance").font(.caption).foregroundStyle(.secondary)
                            Text(Format.money(catalog?.balance)).font(.title3.bold())
                        }
                        Spacer()
                        Button("Add funds") { app.tab = .wallet }.buttonStyle(.bordered)
                    }
                }

                if versions.count > 1 {
                    Section {
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack {
                                pill("All", value: "")
                                ForEach(versions, id: \.self) { pill("Android \($0)", value: $0) }
                            }
                        }
                    }
                    .listRowInsets(EdgeInsets(top: 8, leading: 12, bottom: 8, trailing: 12))
                }

                if groups.isEmpty {
                    EmptyStateView(icon: "bag", title: "No plans available right now")
                }

                ForEach(groups, id: \.0) { name, skus in
                    Section {
                        ForEach(skus) { sku in
                            Button { buying = sku } label: { PlanRow(sku: sku) }
                                .disabled(sku.soldOut == true)
                        }
                    } header: {
                        Text(name)
                    }
                }
            }
        }
        .navigationTitle("Cloud phones")
        .refreshable { await load() }
        .task { await load() }
        .sheet(item: $buying) { sku in
            if let catalog {
                BuyPlanView(sku: sku, catalog: catalog) { await load() }
            }
        }
    }

    private func pill(_ title: String, value: String) -> some View {
        Button(title) { android = value }
            .font(.subheadline.weight(.medium))
            .padding(.horizontal, 12).padding(.vertical, 7)
            .background(android == value ? Theme.accent : Color(.tertiarySystemFill))
            .foregroundStyle(android == value ? Color.white : Color.primary)
            .clipShape(Capsule())
            .buttonStyle(.plain)
    }

    private func load() async {
        do {
            catalog = try await APIClient.shared.get("catalog")
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

struct PlanRow: View {
    let sku: Sku

    var body: some View {
        HStack {
            VStack(alignment: .leading, spacing: 3) {
                Text(sku.durationLabel ?? sku.name).font(.headline).foregroundStyle(.primary)
                HStack(spacing: 6) {
                    if let v = sku.androidVersion { Text("Android \(v)") }
                    if let m = sku.model?.nonEmpty { Text("· \(m)") }
                }
                .font(.caption).foregroundStyle(.secondary)
            }
            Spacer()
            if sku.soldOut == true {
                StatusPill(text: "Sold out", color: .red)
            } else {
                Text(Format.money(sku.price)).font(.headline).foregroundStyle(Theme.accent)
            }
        }
        .padding(.vertical, 2)
    }
}

struct BuyPlanView: View {
    let sku: Sku
    let catalog: Catalog
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss

    @State private var quantity = 1
    @State private var region = ""
    @State private var autoRenew = false
    @State private var proxyMode = ""
    @State private var proxyProduct = 0
    @State private var proxyRegion = ""
    @State private var host = ""
    @State private var port = ""
    @State private var account = ""
    @State private var password = ""
    @State private var protocolName = "socks5"
    @State private var mode = "proxy"
    @State private var busy = false

    private var proxyPrice: Double {
        proxyMode == "vmos" ? (catalog.proxyProducts.first { $0.id == proxyProduct }?.price ?? 0) : 0
    }

    private var total: Double { (sku.price + proxyPrice) * Double(quantity) }

    private var canSubmit: Bool {
        switch proxyMode {
        case "custom": return !host.isEmpty && Int(port) != nil
        case "vmos": return proxyProduct != 0 && !proxyRegion.isEmpty
        default: return true
        }
    }

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    LabeledContent("Plan", value: sku.name)
                    if let d = sku.durationLabel { LabeledContent("Duration", value: d) }
                    if let v = sku.androidVersion { LabeledContent("Android", value: v) }
                    LabeledContent("Price", value: Format.money(sku.price))
                }

                Section {
                    Stepper("Quantity: \(quantity)", value: $quantity, in: 1...20)
                    Picker("Region", selection: $region) {
                        Text("Default").tag("")
                        ForEach(catalog.regions) { r in Text("\(Format.flag(r.code)) \(r.name)").tag(r.code) }
                    }
                    Toggle("Auto-renew", isOn: $autoRenew)
                }

                Section {
                    Picker("Proxy", selection: $proxyMode) {
                        Text("No proxy").tag("")
                        Text("Use my own proxy").tag("custom")
                        if !catalog.proxyProducts.isEmpty { Text("Buy a residential proxy").tag("vmos") }
                    }

                    if proxyMode == "custom" {
                        ProxyFields(host: $host, port: $port, account: $account, password: $password, protocolName: $protocolName, mode: $mode)
                    } else if proxyMode == "vmos" {
                        Picker("Package", selection: $proxyProduct) {
                            Text("Choose").tag(0)
                            ForEach(catalog.proxyProducts) { p in Text("\(p.name) · \(Format.money(p.price))").tag(p.id) }
                        }
                        Picker("Proxy region", selection: $proxyRegion) {
                            Text("Choose").tag("")
                            ForEach(catalog.proxyRegions) { r in Text("\(Format.flag(r.code)) \(r.name)").tag(r.code) }
                        }
                    }
                } header: {
                    Text("Proxy (optional)")
                }

                Section {
                    LabeledContent("Total", value: Format.money(total)).font(.headline)
                    LabeledContent("Wallet balance", value: Format.money(catalog.balance))
                } footer: {
                    Text("Paid from your wallet balance. Your phone is set up straight away and appears under Phones.")
                }

                Section {
                    if catalog.balance <= 0 || catalog.balance < total {
                        Button("Add funds to your wallet") {
                            dismiss()
                            app.tab = .wallet
                        }
                        .buttonStyle(.primary)
                        .listRowBackground(Color.clear)
                    } else {
                        Button {
                            Task { await buy() }
                        } label: {
                            if busy { ProgressView().tint(.white) } else { Text("Buy now · \(Format.money(total))") }
                        }
                        .buttonStyle(.primary)
                        .disabled(busy || !canSubmit)
                        .listRowBackground(Color.clear)
                    }
                }
            }
            .navigationTitle("Buy cloud phone")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
            }
            .onAppear {
                if catalog.proxyProducts.count == 1 { proxyProduct = catalog.proxyProducts[0].id }
            }
        }
    }

    private func buy() async {
        busy = true
        defer { busy = false }

        var body: [String: Any] = [
            "sku_id": sku.id,
            "quantity": quantity,
            "auto_renew": autoRenew,
            "pay_with_balance": true,
        ]
        if !region.isEmpty { body["country_code"] = region }

        if proxyMode == "custom" {
            body["proxy_mode"] = "custom"
            body["proxy_ip"] = host.trimmingCharacters(in: .whitespaces)
            body["proxy_port"] = Int(port) ?? 0
            body["proxy_name"] = protocolName
            body["proxy_type"] = mode
            if let a = account.nonEmpty { body["proxy_account"] = a }
            if let p = password.nonEmpty { body["proxy_password"] = p }
        } else if proxyMode == "vmos" {
            body["proxy_mode"] = "vmos"
            body["proxy_good_id"] = proxyProduct
            body["proxy_country"] = proxyRegion
        }

        if await app.act("orders", body: body) != nil {
            await app.refreshUser()
            await reload()
            dismiss()
            app.tab = .devices
        }
    }
}
