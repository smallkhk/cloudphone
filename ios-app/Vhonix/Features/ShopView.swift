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

    private struct ModelGroup: Identifiable {
        let name: String
        let skus: [Sku]
        var id: String { name }
    }

    private var groups: [ModelGroup] {
        let plans = (catalog?.plans ?? []).filter { android.isEmpty || $0.androidVersion == android }
        var order: [String] = []
        var byName: [String: [Sku]] = [:]
        for p in plans {
            if byName[p.name] == nil { order.append(p.name) }
            byName[p.name, default: []].append(p)
        }
        return order.map { ModelGroup(name: $0, skus: byName[$0] ?? []) }
    }

    var body: some View {
        LoadingView(isLoading: loading && catalog == nil, error: catalog == nil ? error : nil, retry: load) {
            Screen(backdrop: .pink) {
                HStack(spacing: 8) {
                    VStack(alignment: .leading, spacing: 6) {
                        Text("Get a cloud phone")
                            .font(Typo.rounded(.title2, .bold))
                            .foregroundStyle(Palette.text)
                        Text("Real Android devices, ready in minutes. Pay from your wallet.")
                            .font(.subheadline)
                            .foregroundStyle(Palette.text2)
                    }
                    Spacer(minLength: 0)
                    ArtImage(name: "art-shop", size: 128)
                        .padding(.vertical, -20)
                        .padding(.trailing, -10)
                }
                .glassCard(padding: 18, highlight: true, tint: Palette.pink)
                .entrance(0)

                balanceBanner.entrance(1)

                if versions.count > 1 {
                    PillPicker(options: [PickOption(id: "", title: "All")] + versions.map { PickOption(id: $0, title: "Android \($0)") },
                               selection: $android, scrollable: true)
                }

                if groups.isEmpty {
                    EmptyStateView(icon: "bag.fill", title: "No phones available right now", message: "Check back in a little while.")
                        .glassCard()
                }

                ForEach(Array(groups.enumerated()), id: \.element.id) { index, group in
                    ModelCard(name: group.name, skus: group.skus) { buying = $0 }
                        .entrance(index + 2)
                }
            }
        }
        .navigationTitle("Shop")
        .refreshable { await load() }
        .task { await load() }
        .sheet(item: $buying) { sku in
            if let catalog {
                BuyPlanView(sku: sku, catalog: catalog) { await load() }
            }
        }
    }

    private var balanceBanner: some View {
        HStack(spacing: 14) {
            IconTile(symbol: "creditcard.fill", colors: TileColors.green, size: 42)
            VStack(alignment: .leading, spacing: 2) {
                Text("Wallet balance").font(.caption).foregroundStyle(Palette.text3)
                Text(Format.money(catalog?.balance)).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                    .contentTransition(.numericText())
            }
            Spacer()
            Button("Add funds") {
                Haptics.tap()
                app.tab = .wallet
            }
            .buttonStyle(.chip(Palette.accentText, filled: true))
        }
        .glassCard(highlight: true)
    }

    private func load() async {
        if catalog == nil, let saved: Catalog = APIClient.shared.cached("catalog") {
            catalog = saved
            loading = false
        }
        do {
            let c: Catalog = try await APIClient.shared.get("catalog")
            withAnimation(.easeOut(duration: 0.25)) { catalog = c }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

/// One phone model with its plan options.
private struct ModelCard: View {
    let name: String
    let skus: [Sku]
    let select: (Sku) -> Void

    private var first: Sku? { skus.first }
    private var highEnd: Bool { first?.tier == "high_end" || first?.tier == "high-end" || (first?.tier?.contains("high") ?? false) }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 14) {
                ZStack {
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .fill(highEnd ? Theme.tile(TileColors.gold.0, TileColors.gold.1) : Theme.brand)
                        .frame(width: 52, height: 52)
                    Image(systemName: "iphone.gen3").font(.system(size: 24, weight: .semibold)).foregroundStyle(.white)
                }
                .shadow(color: (highEnd ? TileColors.gold.0 : Palette.indigo).opacity(0.4), radius: 10, y: 4)

                VStack(alignment: .leading, spacing: 4) {
                    Text(name).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                    HStack(spacing: 6) {
                        if let v = first?.androidVersion { StatusPill(text: "Android \(v)", color: Palette.accentText) }
                        if highEnd { StatusPill(text: "Pro", color: TileColors.gold.0) }
                    }
                }
                Spacer()
                if let from = skus.map(\.price).min() {
                    VStack(alignment: .trailing, spacing: 0) {
                        Text("from").font(.caption2).foregroundStyle(Palette.text3)
                        Text(Format.money(from)).font(Typo.rounded(.headline, .bold)).foregroundStyle(Theme.brandText)
                    }
                }
            }

            VStack(spacing: 8) {
                ForEach(skus) { sku in
                    Button {
                        Haptics.tap()
                        select(sku)
                    } label: {
                        HStack {
                            Image(systemName: "clock").font(.footnote).foregroundStyle(Palette.text3)
                            Text(sku.durationLabel ?? "Plan").font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                            Spacer()
                            if sku.soldOut == true {
                                StatusPill(text: "Sold out", color: Palette.danger)
                            } else {
                                Text(Format.money(sku.price)).font(Typo.rounded(.body, .bold)).foregroundStyle(Palette.text)
                                Image(systemName: "chevron.right").font(.caption.weight(.bold)).foregroundStyle(Palette.text3)
                            }
                        }
                        .padding(.horizontal, 14)
                        .frame(minHeight: 50)
                        .background(RoundedRectangle(cornerRadius: 14, style: .continuous).fill(Palette.surface))
                        .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).strokeBorder(Palette.stroke, lineWidth: 1))
                    }
                    .buttonStyle(.pressable)
                    .disabled(sku.soldOut == true)
                }
            }
        }
        .glassCard(padding: 16)
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
    @State private var proxyProduct = ""
    @State private var proxyRegion = ""
    @State private var host = ""
    @State private var port = ""
    @State private var account = ""
    @State private var password = ""
    @State private var protocolName = "socks5"
    @State private var mode = "proxy"
    @State private var busy = false

    private var proxyPrice: Double {
        proxyMode == "vmos" ? (catalog.proxyProducts.first { String($0.id) == proxyProduct }?.price ?? 0) : 0
    }

    private var total: Double { (sku.price + proxyPrice) * Double(quantity) }
    private var short: Bool { catalog.balance <= 0 || catalog.balance < total }

    private var canSubmit: Bool {
        switch proxyMode {
        case "custom": return !host.isEmpty && Int(port) != nil
        case "vmos": return !proxyProduct.isEmpty && !proxyRegion.isEmpty
        default: return true
        }
    }

    private var proxyModes: [PickOption] {
        var o = [PickOption(id: "", title: "None"), PickOption(id: "custom", title: "My own")]
        if !catalog.proxyProducts.isEmpty { o.append(PickOption(id: "vmos", title: "Buy one")) }
        return o
    }

    var body: some View {
        SheetScaffold(title: "Checkout") {
            HStack(spacing: 14) {
                IconTile(symbol: "iphone.gen3", colors: TileColors.indigo, size: 54)
                VStack(alignment: .leading, spacing: 4) {
                    Text(sku.name).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                    Text([sku.durationLabel, sku.androidVersion.map { "Android \($0)" }].compactMap { $0 }.joined(separator: " · "))
                        .font(.subheadline).foregroundStyle(Palette.text2)
                }
                Spacer()
                Text(Format.money(sku.price)).font(Typo.rounded(.headline, .bold)).foregroundStyle(Theme.brandText)
            }
            .glassCard(highlight: true)

            VStack(alignment: .leading, spacing: 16) {
                HStack {
                    Text("Quantity").font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                    Spacer()
                    QuantityStepper(value: $quantity)
                }
                CardDivider()
                SelectRow(label: "Region",
                          options: [PickOption(id: "", title: "Default", leading: "🌐")] + catalog.regions.map { PickOption(id: $0.code, title: $0.name, subtitle: $0.code, leading: Format.flag($0.code)) },
                          selection: $region, placeholder: "Default")
                CardDivider()
                ToggleRow(title: "Auto-renew", subtitle: "Keep the phone when the plan ends", isOn: $autoRenew)
            }
            .glassCard(padding: 18)

            VStack(alignment: .leading, spacing: 16) {
                Text("Proxy").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                PillPicker(options: proxyModes, selection: $proxyMode)

                if proxyMode == "custom" {
                    ProxyFields(host: $host, port: $port, account: $account, password: $password, protocolName: $protocolName, mode: $mode)
                        .transition(.opacity)
                } else if proxyMode == "vmos" {
                    SelectRow(label: "Package",
                              options: catalog.proxyProducts.map { PickOption(id: String($0.id), title: $0.name, subtitle: Format.money($0.price) + " per phone") },
                              selection: $proxyProduct, searchable: false)
                    SelectRow(label: "Proxy region",
                              options: catalog.proxyRegions.map { PickOption(id: $0.code, title: $0.name, subtitle: $0.code, leading: Format.flag($0.code)) },
                              selection: $proxyRegion)
                }
            }
            .animation(.easeInOut(duration: 0.25), value: proxyMode)
            .glassCard(padding: 18)

            VStack(spacing: 12) {
                InfoRow(label: "\(quantity) × \(Format.money(sku.price))", value: Format.money(sku.price * Double(quantity)))
                if proxyPrice > 0 {
                    InfoRow(label: "Proxy × \(quantity)", value: Format.money(proxyPrice * Double(quantity)))
                }
                CardDivider()
                HStack(alignment: .firstTextBaseline) {
                    Text("Total").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                    Spacer()
                    Text(Format.money(total)).font(Typo.rounded(.title, .bold)).foregroundStyle(Palette.text)
                        .contentTransition(.numericText())
                        .animation(.spring(), value: total)
                }
                InfoRow(label: "Wallet balance", value: Format.money(catalog.balance), valueColor: short ? Palette.warning : Palette.success)
            }
            .glassCard(padding: 18)

            if short {
                Button {
                    dismiss()
                    app.tab = .wallet
                } label: { Label("Add funds to your wallet", systemImage: "plus.circle.fill") }
                .buttonStyle(.primary)
                NoteView(text: "Your balance doesn't cover this yet. Top up, then come back — it only takes a few minutes.", icon: "creditcard")
            } else {
                Button {
                    Task { await buy() }
                } label: {
                    HStack(spacing: 10) {
                        if busy { ProgressView().tint(.white) }
                        Text("Pay \(Format.money(total)) from wallet")
                    }
                }
                .buttonStyle(.primary)
                .disabled(busy || !canSubmit)
                NoteView(text: "Your phone starts setting up right away and appears under Phones.", icon: "bolt.fill")
            }
        }
        .onAppear {
            if catalog.proxyProducts.count == 1 { proxyProduct = String(catalog.proxyProducts[0].id) }
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
            body["proxy_good_id"] = Int(proxyProduct) ?? 0
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
