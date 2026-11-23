import SwiftUI

struct OrdersView: View {
    @State private var orders: [Order] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        LoadingView(isLoading: loading && orders.isEmpty, error: orders.isEmpty ? error : nil, retry: load) {
            Screen {
                if orders.isEmpty {
                    EmptyStateView(icon: "doc.text.fill", title: "No orders yet", message: "Everything you buy shows up here.", colors: TileColors.slate)
                        .glassCard()
                }
                ForEach(orders) { order in
                    NavigationLink {
                        OrderDetailView(orderId: order.id)
                    } label: {
                        HStack(spacing: 14) {
                            IconTile(symbol: productIcon(for: order.productType), colors: productColors(for: order.productType), size: 42)
                            VStack(alignment: .leading, spacing: 4) {
                                Text(order.product ?? "Order").font(Typo.rounded(.subheadline, .bold)).foregroundStyle(Palette.text).lineLimit(1)
                                Text("\(order.reference ?? "#\(order.id)") · \(Format.when(order.createdAt))")
                                    .font(.caption).foregroundStyle(Palette.text3).lineLimit(1)
                            }
                            Spacer(minLength: 6)
                            VStack(alignment: .trailing, spacing: 6) {
                                Text(Format.money(order.total)).font(Typo.rounded(.subheadline, .bold)).foregroundStyle(Palette.text)
                                StatusPill.forStatus(order.status)
                            }
                        }
                        .glassCard(padding: 14)
                    }
                    .buttonStyle(.pressable)
                }
            }
        }
        .navigationTitle("Orders")
        .refreshable { await load() }
        .task { await load() }
    }

    private func load() async {
        do {
            let r: OrdersResponse = try await APIClient.shared.get("orders")
            withAnimation { orders = r.orders }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

func productIcon(for productType: String?) -> String {
    switch productType {
    case "email_account": return "envelope.fill"
    case "phone_number": return "phone.fill"
    case "cloud_number": return "antenna.radiowaves.left.and.right"
    case "proxy": return "network"
    default: return "iphone.gen3"
    }
}

func productColors(for productType: String?) -> (Color, Color) {
    switch productType {
    case "email_account": return TileColors.cyan
    case "phone_number": return TileColors.green
    case "cloud_number": return TileColors.pink
    case "proxy": return TileColors.orange
    default: return TileColors.indigo
    }
}

struct OrderDetailView: View {
    let orderId: Int

    @EnvironmentObject private var app: AppState
    @State private var order: Order?
    @State private var loading = true
    @State private var error: String?

    private var waiting: Bool {
        ["pending_payment", "paid", "provisioning"].contains(order?.status ?? "")
    }

    var body: some View {
        LoadingView(isLoading: loading && order == nil, error: order == nil ? error : nil, retry: load) {
            if let order {
                Screen {
                    VStack(spacing: 14) {
                        IconTile(symbol: productIcon(for: order.productType), colors: productColors(for: order.productType), size: 60)
                        Text(order.product ?? "Order").font(Typo.rounded(.title2, .bold)).foregroundStyle(Palette.text).multilineTextAlignment(.center)
                        Text(Format.money(order.total)).font(.system(size: 36, weight: .bold, design: .rounded)).foregroundStyle(Theme.brandText)
                        StatusPill.forStatus(order.status)
                    }
                    .frame(maxWidth: .infinity)
                    .glassCard(padding: 22, highlight: true)

                    VStack(spacing: 12) {
                        InfoRow(label: "Order", value: order.reference ?? "#\(order.id)")
                        InfoRow(label: "Quantity", value: "\(order.quantity ?? 1)")
                        InfoRow(label: "Placed", value: Format.when(order.createdAt))
                        if let paid = order.paidAt { InfoRow(label: "Paid", value: Format.when(paid)) }
                        if let e = order.error {
                            NoteView(text: e, icon: "exclamationmark.triangle.fill", tint: Palette.danger)
                        }
                        if let e = order.proxyError?.nonEmpty {
                            NoteView(text: "Proxy: \(e)", icon: "network", tint: Palette.warning)
                        }
                    }
                    .glassCard(padding: 18)

                    if let p = order.payment, order.status == "pending_payment" {
                        SectionTitle(title: "Complete your payment")
                        CryptoPaymentCard(
                            network: p.network, currency: p.currency, address: p.address,
                            amountCrypto: p.amountCrypto, amountUsd: p.amountUsd, expiresAt: p.expiresAt,
                            txHash: p.txHash, status: p.status
                        ) { hash in
                            if await app.act("orders/\(order.id)/payment", body: ["tx_hash": hash]) != nil {
                                await load()
                            }
                        }
                    }

                    if let devices = order.devices, !devices.isEmpty {
                        SectionTitle(title: "Cloud phones")
                        ForEach(devices) { d in DeviceCard(device: d) }
                    }
                    if let items = order.emailAccounts, !items.isEmpty {
                        SectionTitle(title: "Email accounts")
                        ForEach(items) { EmailAccountCard(account: $0, refresh: nil) }
                    }
                    if let items = order.phoneNumbers, !items.isEmpty {
                        SectionTitle(title: "Phone numbers")
                        ForEach(items) { PhoneNumberCard(number: $0, refresh: nil) }
                    }
                    if let items = order.cloudNumbers, !items.isEmpty {
                        SectionTitle(title: "Cloud numbers")
                        VStack(spacing: 10) {
                            ForEach(items) { n in
                                CopyRow(label: n.countryName ?? "Number", value: n.number ?? Format.status(n.purchaseStatus), monospaced: true)
                            }
                        }
                        .glassCard()
                    }
                    if let items = order.proxies, !items.isEmpty {
                        SectionTitle(title: "Proxies")
                        VStack(spacing: 10) {
                            ForEach(items) { p in
                                CopyRow(label: p.title, value: p.host.map { "\($0):\(p.port.map { String($0) } ?? "")" } ?? Format.status(p.purchaseStatus), monospaced: true)
                            }
                        }
                        .glassCard()
                    }
                }
            }
        }
        .navigationTitle("Order")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await load() }
        .task { await load() }
        .task(id: waiting) {
            guard waiting else { return }
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 20_000_000_000)
                if Task.isCancelled { break }
                await load()
            }
        }
    }

    private func load() async {
        do {
            let r: OrderResponse = try await APIClient.shared.get("orders/\(orderId)")
            withAnimation { order = r.order }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}
