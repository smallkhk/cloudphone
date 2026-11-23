import SwiftUI

struct CloudNumbersView: View {
    @StateObject private var store = ProductStore<CloudNumber>(type: "cloud-numbers")
    @EnvironmentObject private var app: AppState
    @State private var country = ""
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?
    @State private var binding: CloudNumber?
    @State private var releasing: CloudNumber?

    private var countries: [Country] {
        var seen = Set<String>()
        return store.products.compactMap { s in
            guard let code = s.countryCode, seen.insert(code).inserted else { return nil }
            return Country(code: code, name: s.countryName ?? code)
        }
    }

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            List {
                if !store.owned.isEmpty {
                    Section("My cloud numbers") {
                        ForEach(store.owned) { number in card(number) }
                    }
                }

                Section {
                    Picker("Country", selection: $country) {
                        Text("All countries").tag("")
                        ForEach(countries) { c in Text("\(Format.flag(c.code)) \(c.name)").tag(c.code) }
                    }
                } header: {
                    Text("Rent a number")
                } footer: {
                    Text("A real number that stays yours while it's renewed. Bind it to one of your cloud phones to receive SMS there.")
                }

                ProductCatalogSection(title: "Plans", products: store.products.filter { country.isEmpty || $0.countryCode == country }) { buying = $0 }
            }
        }
        .navigationTitle("Cloud numbers")
        .refreshable { await store.load() }
        .task { await store.load() }
        .sheet(item: $buying) { sku in
            BuyProductView(sku: sku, maxQuantity: 1) { id in if let id { payOrder = OrderRoute(id: id) } }
        }
        .sheet(item: $binding) { number in
            BindNumberView(number: number, devices: store.devices) { await store.load() }
        }
        .confirmationDialog("Release \(releasing?.number ?? "this number")? It stops working right away and the current period isn't refunded.",
                            isPresented: Binding(get: { releasing != nil }, set: { if !$0 { releasing = nil } }),
                            titleVisibility: .visible) {
            Button("Release number", role: .destructive) {
                if let n = releasing { Task { await act(n, "release") } }
            }
        }
        .navigationDestination(unwrapping: $payOrder) { route in OrderDetailView(orderId: route.id) }
    }

    private func card(_ n: CloudNumber) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("\(Format.flag(n.countryCode)) \(n.countryName ?? n.product ?? "Cloud number")").font(.caption).foregroundStyle(.secondary)
                Spacer()
                StatusPill.forStatus(n.ready ? (n.status ?? "active") : (n.purchaseStatus ?? "processing"))
            }

            CopyRow(label: "Number", value: n.number, monospaced: true)

            if n.ready {
                if let exp = n.expiresAt { LabeledContent("Expires", value: Format.when(exp)).font(.subheadline) }
                LabeledContent("Bound to", value: n.boundPadCode ?? "Not bound").font(.subheadline)
                if let state = n.bindState?.nonEmpty { LabeledContent("Binding", value: Format.status(state)).font(.subheadline) }

                Toggle("Auto-renew", isOn: Binding(get: { n.autoRenew }, set: { _ in Task { await act(n, "auto-renew") } }))
                    .font(.subheadline)

                HStack {
                    Button("Bind to phone") { binding = n }
                    Spacer()
                    AsyncButton("Bind status") { await act(n, "bind-status") }
                }
                .buttonStyle(.bordered).font(.caption)

                HStack {
                    AsyncButton("Check SMS") { await act(n, "sms") }
                    Spacer()
                    Button("Release", role: .destructive) { releasing = n }
                }
                .buttonStyle(.bordered).font(.caption)

                if !n.sms.isEmpty {
                    VStack(alignment: .leading, spacing: 8) {
                        Text("Messages").font(.caption.weight(.semibold)).foregroundStyle(.secondary)
                        ForEach(Array(n.sms.enumerated()), id: \.offset) { _, m in
                            VStack(alignment: .leading, spacing: 2) {
                                HStack {
                                    Text(m.from ?? "Unknown").font(.caption.weight(.semibold))
                                    Spacer()
                                    Text(m.at ?? "").font(.caption2).foregroundStyle(.secondary)
                                }
                                Text(m.text ?? "").font(.subheadline).textSelection(.enabled)
                            }
                            .padding(10)
                            .background(Color(.tertiarySystemFill))
                            .clipShape(RoundedRectangle(cornerRadius: 10))
                        }
                    }
                }
            } else {
                Text("Your number is being set up — this usually takes a minute or two. Pull down to refresh.")
                    .font(.footnote).foregroundStyle(.secondary)
            }
        }
        .padding(.vertical, 6)
        .buttonStyle(.borderless)
    }

    private func act(_ n: CloudNumber, _ action: String) async {
        if await app.act("cloud-numbers/\(n.id)/\(action)") != nil {
            await store.load()
        } else {
            await store.load()
        }
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
        NavigationStack {
            Form {
                Section {
                    LabeledContent("Number", value: number.number ?? "—")
                    if devices.isEmpty {
                        Text("You don't have a ready cloud phone to bind to yet.").foregroundStyle(.secondary)
                    } else {
                        DevicePicker(devices: devices, padCode: $padCode)
                    }
                }
                Section {
                    Toggle("I understand this restarts the phone", isOn: $acknowledged)
                }
                Section {
                    AsyncButton("Bind number") {
                        if await app.act("cloud-numbers/\(number.id)/bind", body: ["pad_code": padCode, "restart_acknowledged": true]) != nil {
                            await reload()
                            dismiss()
                        }
                    }
                    .disabled(padCode.isEmpty || !acknowledged)
                }
            }
            .navigationTitle("Bind to a phone")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar { ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } } }
        }
    }
}
