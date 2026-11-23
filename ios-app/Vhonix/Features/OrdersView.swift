import SwiftUI

struct OrdersView: View {
    @State private var orders: [Order] = []
    @State private var loading = true
    @State private var error: String?

    var body: some View {
        LoadingView(isLoading: loading && orders.isEmpty, error: orders.isEmpty ? error : nil, retry: load) {
            List {
                if orders.isEmpty {
                    EmptyStateView(icon: "list.bullet.rectangle", title: "No orders yet")
                }
                ForEach(orders) { order in
                    NavigationLink {
                        OrderDetailView(orderId: order.id)
                    } label: {
                        HStack {
                            VStack(alignment: .leading, spacing: 3) {
                                Text(order.product ?? "Order").font(.subheadline.weight(.semibold))
                                Text("\(order.reference ?? "#\(order.id)") · \(Format.when(order.createdAt))")
                                    .font(.caption).foregroundStyle(.secondary)
                            }
                            Spacer()
                            VStack(alignment: .trailing, spacing: 4) {
                                Text(Format.money(order.total)).font(.subheadline)
                                StatusPill.forStatus(order.status)
                            }
                        }
                    }
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
            orders = r.orders
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
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
                List {
                    Section {
                        LabeledContent("Product", value: order.product ?? "—")
                        LabeledContent("Quantity", value: "\(order.quantity ?? 1)")
                        LabeledContent("Total", value: Format.money(order.total))
                        LabeledContent("Status") { StatusPill.forStatus(order.status) }
                        LabeledContent("Placed", value: Format.when(order.createdAt))
                        if let e = order.error { Text(e).foregroundStyle(.red).font(.footnote) }
                        if let e = order.proxyError?.nonEmpty { Text("Proxy: \(e)").foregroundStyle(.orange).font(.footnote) }
                    } header: {
                        Text(order.reference ?? "Order #\(order.id)")
                    }

                    if let p = order.payment, order.status == "pending_payment" {
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
                        Section("Cloud phones") {
                            ForEach(devices) { d in DeviceRow(device: d) }
                        }
                    }
                    if let items = order.emailAccounts, !items.isEmpty {
                        Section("Email accounts") { ForEach(items) { EmailAccountCard(account: $0, refresh: nil) } }
                    }
                    if let items = order.phoneNumbers, !items.isEmpty {
                        Section("Phone numbers") { ForEach(items) { PhoneNumberCard(number: $0, refresh: nil) } }
                    }
                    if let items = order.cloudNumbers, !items.isEmpty {
                        Section("Cloud numbers") {
                            ForEach(items) { n in
                                CopyRow(label: n.countryName ?? "Number", value: n.number ?? Format.status(n.purchaseStatus), monospaced: true)
                            }
                        }
                    }
                    if let items = order.proxies, !items.isEmpty {
                        Section("Proxies") {
                            ForEach(items) { p in
                                CopyRow(label: p.title, value: p.host.map { "\($0):\(p.port.map { String($0) } ?? "")" } ?? Format.status(p.purchaseStatus), monospaced: true)
                            }
                        }
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
            order = r.order
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}
