import SwiftUI

struct CloudNumbersView: View {
    @StateObject private var store = ProductStore<CloudNumber>(type: "cloud-numbers")
    @EnvironmentObject private var app: AppState
    @State private var country = ""
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?
    @State private var binding: CloudNumber?
    @State private var releasing: CloudNumber?

    private var countries: [PickOption] {
        var seen = Set<String>()
        let list = store.products.compactMap { s -> PickOption? in
            guard let code = s.countryCode, seen.insert(code).inserted else { return nil }
            return PickOption(id: code, title: s.countryName ?? code, subtitle: code, leading: Format.flag(code))
        }
        return [PickOption(id: "", title: "All countries", leading: "🌍")] + list.sorted { $0.title < $1.title }
    }

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            Screen {
                ProductHero(icon: "antenna.radiowaves.left.and.right", colors: TileColors.pink, title: "Cloud numbers",
                            text: "Rent a real number that stays yours. Bind it to a cloud phone to receive SMS there.")

                if !store.owned.isEmpty {
                    SectionTitle(title: "My numbers")
                    ForEach(store.owned) { number in card(number) }
                }

                SectionTitle(title: "Rent a number")
                SelectRow(label: "Country", options: countries, selection: $country, placeholder: "All countries")
                    .glassCard(padding: 14)

                ProductCatalogSection(title: "Plans", products: store.products.filter { country.isEmpty || $0.countryCode == country }) { buying = $0 }
            }
        }
        .navigationTitle("Cloud numbers")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await store.load() }
        .task { await store.load() }
        .sheet(item: $buying) { sku in
            BuyProductView(sku: sku, maxQuantity: 1) { id in if let id { payOrder = OrderRoute(id: id) } }
        }
        .sheet(item: $binding) { number in
            BindNumberView(number: number, devices: store.devices) { await store.load() }
        }
        .confirmationDialog("Release \(releasing?.number ?? "this number")?",
                            isPresented: Binding(get: { releasing != nil }, set: { if !$0 { releasing = nil } }),
                            titleVisibility: .visible) {
            Button("Release number", role: .destructive) {
                if let n = releasing { Task { await act(n, "release") } }
            }
        } message: {
            Text("It stops working right away and the current period isn't refunded.")
        }
        .navigationDestination(unwrapping: $payOrder) { route in OrderDetailView(orderId: route.id) }
    }

    private func card(_ n: CloudNumber) -> some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack {
                Text("\(Format.flag(n.countryCode)) \(n.countryName ?? n.product ?? "Cloud number")").font(.caption.weight(.semibold)).foregroundStyle(Palette.text3)
                Spacer()
                StatusPill.forStatus(n.ready ? (n.status ?? "active") : (n.purchaseStatus ?? "processing"))
            }

            Text(n.number ?? "Setting up…")
                .font(.system(.title, design: .rounded, weight: .bold))
                .foregroundStyle(Palette.text)
                .textSelection(.enabled)

            if n.ready {
                VStack(spacing: 10) {
                    if let exp = n.expiresAt { InfoRow(label: "Expires", value: Format.when(exp)) }
                    InfoRow(label: "Bound to", value: n.boundPadCode ?? "Not bound", valueColor: n.boundPadCode == nil ? Palette.text3 : Palette.text)
                    if let state = n.bindState?.nonEmpty { InfoRow(label: "Binding", value: Format.status(state)) }
                }

                ToggleRow(title: "Auto-renew", isOn: Binding(get: { n.autoRenew }, set: { _ in Task { await act(n, "auto-renew") } }))

                HStack(spacing: 8) {
                    Button { binding = n } label: { Label("Bind", systemImage: "link") }
                        .buttonStyle(.chip(Palette.accentText, filled: true))
                    AsyncButton { await act(n, "bind-status") } label: { Text("Status") }
                        .buttonStyle(.chip(Palette.text))
                    AsyncButton { await act(n, "sms") } label: { Label("SMS", systemImage: "message.fill") }
                        .buttonStyle(.chip(Palette.text))
                    Spacer()
                    Button { releasing = n } label: { Image(systemName: "trash") }
                        .buttonStyle(.chip(Palette.danger))
                        .accessibilityLabel("Release number")
                }

                if !n.sms.isEmpty {
                    VStack(alignment: .leading, spacing: 8) {
                        Text("Messages").font(.caption.weight(.semibold)).foregroundStyle(Palette.text3)
                        ForEach(Array(n.sms.enumerated()), id: \.offset) { _, m in
                            VStack(alignment: .leading, spacing: 4) {
                                HStack {
                                    Text(m.from ?? "Unknown").font(Typo.rounded(.caption, .bold)).foregroundStyle(Palette.accentText)
                                    Spacer()
                                    Text(m.at ?? "").font(.caption2).foregroundStyle(Palette.text3)
                                }
                                Text(m.text ?? "").font(.subheadline).foregroundStyle(Palette.text).textSelection(.enabled)
                            }
                            .padding(12)
                            .background(RoundedRectangle(cornerRadius: 14, style: .continuous).fill(Palette.surface))
                        }
                    }
                }
            } else {
                NoteView(text: "Your number is being set up — usually a minute or two. Pull down to refresh.", icon: "hourglass", tint: Palette.warning)
            }
        }
        .glassCard(padding: 16)
    }

    private func act(_ n: CloudNumber, _ action: String) async {
        _ = await app.act("cloud-numbers/\(n.id)/\(action)")
        await store.load()
    }
}

struct BindNumberView: View {
    let number: CloudNumber
    let devices: [DeviceRef]
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var padCode = ""
    @State private var acknowledged = false

    var body: some View {
        SheetScaffold(title: "Bind to a phone") {
            VStack(alignment: .leading, spacing: 6) {
                Text("Number").font(.caption).foregroundStyle(Palette.text3)
                Text(number.number ?? "—").font(Typo.rounded(.title2, .bold)).foregroundStyle(Palette.text)
            }
            .glassCard(highlight: true)

            VStack(alignment: .leading, spacing: 16) {
                if devices.isEmpty {
                    NoteView(text: "You don't have a ready cloud phone to bind to yet.", icon: "iphone.slash", tint: Palette.warning)
                } else {
                    DevicePicker(devices: devices, padCode: $padCode)
                }
                ToggleRow(title: "I understand the phone restarts", subtitle: "Binding needs a quick restart", isOn: $acknowledged)
            }
            .glassCard(padding: 18)

            AsyncButton {
                if await app.act("cloud-numbers/\(number.id)/bind", body: ["pad_code": padCode, "restart_acknowledged": true]) != nil {
                    await reload()
                    dismiss()
                }
            } label: { Text("Bind number") }
            .buttonStyle(.primary)
            .disabled(padCode.isEmpty || !acknowledged)
        }
    }
}
