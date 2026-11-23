import SwiftUI

/// One phone model (name + Android version + spec) with its plans, duplicates
/// collapsed to the cheapest per duration.
struct PhoneModel: Identifiable, Hashable {
    let id: String
    let name: String
    let android: String?
    let spec: String?
    let tier: String?
    let plans: [Sku]

    var isPro: Bool { (tier ?? "").contains("high") }
    var cheapest: Sku? { plans.filter { $0.soldOut != true }.min { $0.price < $1.price } ?? plans.first }
    var bestPerDay: Double? { plans.filter { $0.soldOut != true }.compactMap(PhoneModel.perDay).min() }
    var soldOut: Bool { plans.allSatisfy { $0.soldOut == true } }
    var androidNumber: Double { Double(android ?? "") ?? 0 }

    static func days(_ sku: Sku) -> Double? {
        guard let m = sku.durationMinutes, m > 0 else { return nil }
        return Double(m) / 1440
    }

    static func perDay(_ sku: Sku) -> Double? {
        guard let d = days(sku), d > 0 else { return nil }
        return sku.price / d
    }

    static func build(from skus: [Sku]) -> [PhoneModel] {
        var order: [String] = []
        var groups: [String: [Sku]] = [:]
        for s in skus {
            let key = [s.name, s.androidVersion ?? "", s.model ?? ""].joined(separator: "|")
            if groups[key] == nil { order.append(key) }
            groups[key, default: []].append(s)
        }
        return order.compactMap { key in
            guard let list = groups[key], let first = list.first else { return nil }
            // Same duration listed twice → keep the cheapest available one.
            var byDuration: [Int: Sku] = [:]
            for s in list {
                let d = s.durationMinutes ?? 0
                if let current = byDuration[d] {
                    let better = (current.soldOut == true && s.soldOut != true) || (s.soldOut == current.soldOut && s.price < current.price)
                    if better { byDuration[d] = s }
                } else {
                    byDuration[d] = s
                }
            }
            let plans = byDuration.values.sorted { ($0.durationMinutes ?? 0) < ($1.durationMinutes ?? 0) }
            return PhoneModel(id: key, name: first.name, android: first.androidVersion, spec: first.model, tier: first.tier, plans: plans)
        }
    }
}

enum ShopSort: String, CaseIterable, Identifiable {
    case recommended = "Recommended"
    case priceLow = "Price: low to high"
    case priceHigh = "Price: high to low"
    case newest = "Newest Android"
    case name = "Name A–Z"

    var id: String { rawValue }
}

struct ShopView: View {
    @EnvironmentObject private var app: AppState
    @State private var catalog: Catalog?
    @State private var loading = true
    @State private var error: String?
    @State private var android = ""
    @State private var tier = ""
    @State private var query = ""
    @State private var sort: ShopSort = .recommended
    @State private var buying: PhoneModel?

    private var allModels: [PhoneModel] { PhoneModel.build(from: catalog?.plans ?? []) }

    private var versions: [String] {
        Array(Set(allModels.compactMap(\.android))).sorted { $0.localizedStandardCompare($1) == .orderedDescending }
    }

    private var hasBothTiers: Bool {
        let models = allModels.filter { android.isEmpty || $0.android == android }
        return models.contains { $0.isPro } && models.contains { !$0.isPro }
    }

    private var filtered: [PhoneModel] {
        let q = query.trimmingCharacters(in: .whitespaces)
        let list = allModels.filter { m in
            (android.isEmpty || m.android == android)
                && (tier.isEmpty || (tier == "pro" ? m.isPro : !m.isPro))
                && (q.isEmpty || m.name.localizedCaseInsensitiveContains(q) || (m.spec?.localizedCaseInsensitiveContains(q) ?? false))
        }
        let price: (PhoneModel) -> Double = { $0.cheapest?.price ?? .greatestFiniteMagnitude }
        switch sort {
        case .recommended:
            // In stock first, then newest Android, Pro before Standard, then cheapest.
            return list.sorted {
                if $0.soldOut != $1.soldOut { return !$0.soldOut }
                if $0.androidNumber != $1.androidNumber { return $0.androidNumber > $1.androidNumber }
                if $0.isPro != $1.isPro { return $0.isPro }
                return price($0) < price($1)
            }
        case .priceLow: return list.sorted { price($0) < price($1) }
        case .priceHigh: return list.sorted { price($0) > price($1) }
        case .newest: return list.sorted { $0.androidNumber != $1.androidNumber ? $0.androidNumber > $1.androidNumber : price($0) < price($1) }
        case .name: return list.sorted { $0.name.localizedCaseInsensitiveCompare($1.name) == .orderedAscending }
        }
    }

    private var filtersActive: Bool { !android.isEmpty || !tier.isEmpty || !query.isEmpty }

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

                filterBar

                if filtered.isEmpty {
                    EmptyStateView(icon: "magnifyingglass", title: allModels.isEmpty ? "No phones available right now" : "No phones match",
                                   message: allModels.isEmpty ? "Check back in a little while." : "Try another Android version or tier.",
                                   actionTitle: filtersActive ? "Clear filters" : nil, colors: TileColors.pink) { clearFilters() }
                        .glassCard()
                } else {
                    LazyVGrid(columns: [GridItem(.adaptive(minimum: 300), spacing: 12)], spacing: 12) {
                        ForEach(Array(filtered.enumerated()), id: \.element.id) { index, model in
                            Button {
                                Haptics.tap()
                                buying = model
                            } label: {
                                ModelTile(model: model)
                            }
                            .buttonStyle(.pressable)
                            .disabled(model.soldOut)
                            .entrance(min(index, 6) + 2)
                        }
                    }
                }
            }
        }
        .navigationTitle("Shop")
        .refreshable { await load() }
        .task { await load() }
        .onChange(of: android) { _ in
            if !hasBothTiers { tier = "" }
        }
        .sheet(item: $buying) { model in
            if let catalog {
                BuyPlanView(model: model, catalog: catalog) { await load() }
            }
        }
    }

    private var filterBar: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 10) {
                HStack(spacing: 8) {
                    Image(systemName: "magnifyingglass").foregroundStyle(Palette.text3)
                    TextField("", text: $query, prompt: Text("Search models").foregroundColor(Palette.text3))
                        .foregroundStyle(Palette.text)
                        .autocorrectionDisabled()
                        .textInputAutocapitalization(.never)
                    if !query.isEmpty {
                        Button { query = "" } label: {
                            Image(systemName: "xmark.circle.fill").foregroundStyle(Palette.text3)
                        }
                        .accessibilityLabel("Clear search")
                    }
                }
                .padding(.horizontal, 14)
                .frame(minHeight: 46)
                .background(Capsule().fill(Palette.surface))
                .overlay(Capsule().strokeBorder(Palette.stroke, lineWidth: 1))

                Menu {
                    Picker("Sort by", selection: $sort) {
                        ForEach(ShopSort.allCases) { Text($0.rawValue).tag($0) }
                    }
                } label: {
                    HStack(spacing: 6) {
                        Image(systemName: "arrow.up.arrow.down")
                        Text(sort == .recommended ? "Sort" : sort.rawValue).lineLimit(1)
                    }
                    .font(Typo.rounded(.subheadline, .semibold))
                    .foregroundStyle(sort == .recommended ? Palette.text : Palette.accentText)
                    .padding(.horizontal, 14)
                    .frame(minHeight: 46)
                    .background(Capsule().fill(Palette.surface))
                    .overlay(Capsule().strokeBorder(Palette.stroke, lineWidth: 1))
                }
                .accessibilityLabel("Sort phones")
            }

            if versions.count > 1 {
                PillPicker(options: [PickOption(id: "", title: "All Android")] + versions.map { PickOption(id: $0, title: "Android \($0)") },
                           selection: $android, scrollable: true)
            }

            if hasBothTiers {
                PillPicker(options: [PickOption(id: "", title: "All tiers"), PickOption(id: "standard", title: "Standard"), PickOption(id: "pro", title: "Pro")],
                           selection: $tier)
            }

            HStack {
                Text("\(filtered.count) phone\(filtered.count == 1 ? "" : "s")")
                    .font(.footnote.weight(.semibold))
                    .foregroundStyle(Palette.text3)
                Spacer()
                if filtersActive {
                    Button("Clear filters") { clearFilters() }
                        .font(.footnote.weight(.semibold))
                        .foregroundStyle(Palette.accentText)
                        .frame(minHeight: 44)
                }
            }
            .padding(.horizontal, 4)
        }
        .entrance(2)
    }

    private func clearFilters() {
        withAnimation(.spring(response: 0.35, dampingFraction: 0.85)) {
            android = ""
            tier = ""
            query = ""
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

/// One phone in the shop grid — no durations here; those are picked at checkout.
private struct ModelTile: View {
    let model: PhoneModel

    var body: some View {
        HStack(spacing: 14) {
            ZStack {
                RoundedRectangle(cornerRadius: 18, style: .continuous)
                    .fill(model.isPro ? Theme.tile(TileColors.gold.0, TileColors.gold.1) : Theme.tile(Palette.indigo, Palette.violet))
                    .frame(width: 74, height: 92)
                Image("art-phone")
                    .resizable()
                    .scaledToFit()
                    .frame(width: 104, height: 104)
                    .accessibilityHidden(true)
            }
            .frame(width: 74, height: 92)

            VStack(alignment: .leading, spacing: 6) {
                Text(model.name)
                    .font(Typo.rounded(.headline, .bold))
                    .foregroundStyle(Palette.text)
                    .lineLimit(2)
                HStack(spacing: 6) {
                    if let v = model.android { StatusPill(text: "Android \(v)", color: Palette.accentText) }
                    StatusPill(text: model.isPro ? "Pro" : "Standard", color: model.isPro ? TileColors.gold.0 : Palette.text2)
                }
                HStack(alignment: .firstTextBaseline, spacing: 6) {
                    if model.soldOut {
                        Text("Sold out").font(Typo.rounded(.subheadline, .bold)).foregroundStyle(Palette.danger)
                    } else {
                        Text("from").font(.caption).foregroundStyle(Palette.text3)
                        Text(Format.money(model.cheapest?.price)).font(Typo.rounded(.title3, .bold)).foregroundStyle(Theme.brandText)
                    }
                }
                Text(subtitle).font(.caption).foregroundStyle(Palette.text3).lineLimit(1)
            }

            Spacer(minLength: 0)
            Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Palette.text3)
        }
        .glassCard(padding: 14, tint: model.isPro ? TileColors.gold.0 : nil)
        .accessibilityElement(children: .combine)
    }

    private var subtitle: String {
        var parts = ["\(model.plans.count) plan\(model.plans.count == 1 ? "" : "s")"]
        if let d = model.bestPerDay { parts.append("as low as \(Format.money(d))/day") }
        return parts.joined(separator: " · ")
    }
}

struct BuyPlanView: View {
    let model: PhoneModel
    let catalog: Catalog
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss

    @State private var selectedId: Int?
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

    /// The chosen plan (defaults to the cheapest available).
    private var sku: Sku {
        model.plans.first { $0.id == selectedId } ?? model.cheapest ?? model.plans[0]
    }

    /// Highest per-day rate among the plans — the baseline for "Save X%".
    private var worstPerDay: Double? { model.plans.compactMap(PhoneModel.perDay).max() }

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
                IconTile(symbol: "iphone.gen3", colors: model.isPro ? TileColors.gold : TileColors.indigo, size: 54)
                VStack(alignment: .leading, spacing: 6) {
                    Text(model.name).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                    HStack(spacing: 6) {
                        if let v = model.android { StatusPill(text: "Android \(v)", color: Palette.accentText) }
                        StatusPill(text: model.isPro ? "Pro" : "Standard", color: model.isPro ? TileColors.gold.0 : Palette.text2)
                    }
                }
                Spacer()
            }
            .glassCard(highlight: true)

            VStack(alignment: .leading, spacing: 12) {
                Text("Choose a plan").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                LazyVGrid(columns: [GridItem(.adaptive(minimum: 140), spacing: 10)], spacing: 10) {
                    ForEach(model.plans) { plan in
                        planOption(plan)
                    }
                }
            }
            .glassCard(padding: 18)

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
                .disabled(busy || !canSubmit || sku.soldOut == true)
                NoteView(text: "Your phone starts setting up right away and appears under Phones.", icon: "bolt.fill")
            }
        }
        .onAppear {
            if selectedId == nil { selectedId = model.cheapest?.id }
            if catalog.proxyProducts.count == 1 { proxyProduct = String(catalog.proxyProducts[0].id) }
        }
    }

    private func planOption(_ plan: Sku) -> some View {
        let selected = plan.id == sku.id
        let perDay = PhoneModel.perDay(plan)
        let saving: Int? = {
            guard let p = perDay, let worst = worstPerDay, worst > 0 else { return nil }
            let pct = Int(((worst - p) / worst * 100).rounded())
            return pct >= 5 ? pct : nil
        }()

        return Button {
            Haptics.select()
            withAnimation(.spring(response: 0.3, dampingFraction: 0.8)) { selectedId = plan.id }
        } label: {
            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text(plan.durationLabel ?? "Plan").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                    Spacer(minLength: 4)
                    Image(systemName: selected ? "checkmark.circle.fill" : "circle")
                        .foregroundStyle(selected ? Palette.accentText : Palette.text3)
                }
                Text(Format.money(plan.price)).font(Typo.rounded(.title3, .bold)).foregroundStyle(selected ? AnyShapeStyle(Theme.brandText) : AnyShapeStyle(Palette.text))
                HStack(spacing: 6) {
                    if let perDay { Text("\(Format.money(perDay))/day").font(.caption).foregroundStyle(Palette.text3) }
                    if let saving {
                        Text("Save \(saving)%")
                            .font(Typo.rounded(.caption2, .bold))
                            .foregroundStyle(Palette.success)
                            .padding(.horizontal, 6).padding(.vertical, 2)
                            .background(Capsule().fill(Palette.success.opacity(0.15)))
                    }
                }
                if plan.soldOut == true {
                    Text("Sold out").font(.caption.weight(.semibold)).foregroundStyle(Palette.danger)
                }
            }
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(RoundedRectangle(cornerRadius: 16, style: .continuous).fill(selected ? Palette.indigo.opacity(0.22) : Palette.surface))
            .overlay(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .strokeBorder(selected ? AnyShapeStyle(Theme.brand) : AnyShapeStyle(Palette.stroke), lineWidth: selected ? 2 : 1)
            )
            .contentShape(Rectangle())
        }
        .buttonStyle(.pressable)
        .disabled(plan.soldOut == true)
        .accessibilityAddTraits(selected ? .isSelected : [])
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
