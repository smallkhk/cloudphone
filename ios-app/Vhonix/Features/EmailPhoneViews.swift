import SwiftUI

struct EmailAccountsView: View {
    @StateObject private var store = ProductStore<EmailAccount>(type: "email-accounts")
    @EnvironmentObject private var app: AppState
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            List {
                if !store.owned.isEmpty {
                    Section("My email accounts") {
                        ForEach(store.owned) { account in
                            EmailAccountCard(account: account) {
                                if await app.act("email-accounts/\(account.id)/refresh") != nil { await store.load() }
                            }
                        }
                    }
                }
                ProductCatalogSection(title: "Buy an email account", products: store.products) { buying = $0 }
            }
        }
        .navigationTitle("Email accounts")
        .refreshable { await store.load() }
        .task { await store.load() }
        .sheet(item: $buying) { sku in
            BuyProductView(sku: sku) { id in if let id { payOrder = OrderRoute(id: id) } }
        }
        .navigationDestination(unwrapping: $payOrder) { route in OrderDetailView(orderId: route.id) }
    }
}

struct EmailAccountCard: View {
    let account: EmailAccount
    let refresh: (() async -> Void)?

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(account.product ?? "Email account").font(.caption).foregroundStyle(.secondary)
                Spacer()
                StatusPill.forStatus(account.status)
            }
            CopyRow(label: "Email", value: account.email, monospaced: true)
            if account.password?.nonEmpty != nil {
                CopyRow(label: "Password", value: account.password, monospaced: true)
            }
            CopyRow(label: "Latest code", value: account.latestCode, monospaced: true)
            if let refresh {
                HStack {
                    if let at = account.codeFetchedAt {
                        Text("Checked \(Format.relative(at))").font(.caption2).foregroundStyle(.secondary)
                    }
                    Spacer()
                    AsyncButton("Check for code") { await refresh() }
                        .buttonStyle(.bordered).font(.caption)
                }
            }
        }
        .padding(.vertical, 4)
    }
}

struct PhoneNumbersView: View {
    @StateObject private var store = ProductStore<PhoneNumber>(type: "phone-numbers")
    @EnvironmentObject private var app: AppState
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            List {
                if !store.owned.isEmpty {
                    Section("My phone numbers") {
                        ForEach(store.owned) { number in
                            PhoneNumberCard(number: number) {
                                if await app.act("phone-numbers/\(number.id)/refresh") != nil { await store.load() }
                            }
                        }
                    }
                }
                ProductCatalogSection(title: "Buy a verification number", products: store.products) { buying = $0 }
            }
        }
        .navigationTitle("Phone numbers")
        .refreshable { await store.load() }
        .task { await store.load() }
        .sheet(item: $buying) { sku in
            BuyProductView(sku: sku) { id in if let id { payOrder = OrderRoute(id: id) } }
        }
        .navigationDestination(unwrapping: $payOrder) { route in OrderDetailView(orderId: route.id) }
    }
}

struct PhoneNumberCard: View {
    let number: PhoneNumber
    let refresh: (() async -> Void)?

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text("\(Format.flag(number.countryCode)) \(number.product ?? "Phone number")").font(.caption).foregroundStyle(.secondary)
                Spacer()
                StatusPill.forStatus(number.status)
            }
            CopyRow(label: "Number", value: number.phoneNumber, monospaced: true)
            CopyRow(label: "Latest code", value: number.latestCode, monospaced: true)
            if let refresh {
                HStack {
                    if let at = number.codeFetchedAt {
                        Text("Checked \(Format.relative(at))").font(.caption2).foregroundStyle(.secondary)
                    }
                    Spacer()
                    AsyncButton("Check for code") { await refresh() }
                        .buttonStyle(.bordered).font(.caption)
                }
            }
        }
        .padding(.vertical, 4)
    }
}
