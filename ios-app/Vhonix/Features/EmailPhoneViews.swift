import SwiftUI

/// Hero at the top of each product screen.
struct ProductHero: View {
    let icon: String
    let colors: (Color, Color)
    let title: String
    let text: String

    var body: some View {
        HStack(alignment: .center, spacing: 16) {
            IconTile(symbol: icon, colors: colors, size: 56)
            VStack(alignment: .leading, spacing: 4) {
                Text(title).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                Text(text).font(.subheadline).foregroundStyle(Palette.text2).fixedSize(horizontal: false, vertical: true)
            }
        }
        .glassCard(padding: 18, highlight: true)
    }
}

struct EmailAccountsView: View {
    @StateObject private var store = ProductStore<EmailAccount>(type: "email-accounts")
    @EnvironmentObject private var app: AppState
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            Screen {
                ProductHero(icon: "envelope.fill", colors: TileColors.cyan, title: "Email accounts",
                            text: "Ready-made inboxes that receive verification codes for sign-ups.")
                if !store.owned.isEmpty {
                    SectionTitle(title: "My inboxes")
                    ForEach(store.owned) { account in
                        EmailAccountCard(account: account) {
                            if await app.act("email-accounts/\(account.id)/refresh") != nil { await store.load() }
                        }
                    }
                }
                ProductCatalogSection(title: "Buy an inbox", products: store.products) { buying = $0 }
            }
        }
        .navigationTitle("Email accounts")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await store.load() }
        .task { await store.load() }
        .sheet(item: $buying) { sku in
            BuyProductView(sku: sku) { id in if let id { payOrder = OrderRoute(id: id) } }
        }
        .navigationDestination(unwrapping: $payOrder) { route in OrderDetailView(orderId: route.id) }
    }
}

/// Big, easy-to-read verification code.
struct CodeDisplay: View {
    let code: String?
    let checkedAt: String?
    let refresh: (() async -> Void)?

    @EnvironmentObject private var app: AppState

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("Latest code").font(.caption.weight(.semibold)).foregroundStyle(Palette.text3)
                Spacer()
                if let checkedAt {
                    Text("Checked \(Format.relative(checkedAt))").font(.caption2).foregroundStyle(Palette.text3)
                }
            }
            HStack {
                Text(code?.nonEmpty ?? "Waiting…")
                    .font(.system(code?.nonEmpty == nil ? .title3 : .largeTitle, design: .monospaced, weight: .bold))
                    .foregroundStyle(code?.nonEmpty == nil ? Palette.text3 : Palette.text)
                    .tracking(code?.nonEmpty == nil ? 0 : 4)
                    .textSelection(.enabled)
                Spacer()
                if let code = code?.nonEmpty {
                    Button {
                        UIPasteboard.general.string = code
                        app.show("Code copied.")
                    } label: { Label("Copy", systemImage: "doc.on.doc") }
                    .buttonStyle(.chip(Palette.accentText))
                }
            }
            if let refresh {
                AsyncButton { await refresh() } label: { Label("Check for a new code", systemImage: "arrow.clockwise") }
                    .buttonStyle(.glass(Palette.accentText))
            }
        }
        .padding(14)
        .background(RoundedRectangle(cornerRadius: 16, style: .continuous).fill(Palette.indigo.opacity(0.10)))
        .overlay(RoundedRectangle(cornerRadius: 16, style: .continuous).strokeBorder(Palette.indigo.opacity(0.25), lineWidth: 1))
    }
}

struct EmailAccountCard: View {
    let account: EmailAccount
    let refresh: (() async -> Void)?

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Text(account.product ?? "Email account").font(.caption.weight(.semibold)).foregroundStyle(Palette.text3)
                Spacer()
                StatusPill.forStatus(account.status)
            }
            CopyRow(label: "Email", value: account.email, monospaced: true)
            if account.password?.nonEmpty != nil {
                CopyRow(label: "Password", value: account.password, monospaced: true)
            }
            CodeDisplay(code: account.latestCode, checkedAt: account.codeFetchedAt, refresh: refresh)
        }
        .glassCard(padding: 16)
    }
}

struct PhoneNumbersView: View {
    @StateObject private var store = ProductStore<PhoneNumber>(type: "phone-numbers")
    @EnvironmentObject private var app: AppState
    @State private var buying: Sku?
    @State private var payOrder: OrderRoute?

    var body: some View {
        LoadingView(isLoading: store.loading && store.owned.isEmpty && store.products.isEmpty, error: store.products.isEmpty && store.owned.isEmpty ? store.error : nil, retry: store.load) {
            Screen {
                ProductHero(icon: "phone.fill", colors: TileColors.green, title: "Verification numbers",
                            text: "A number that receives the SMS code for an app sign-up.")
                if !store.owned.isEmpty {
                    SectionTitle(title: "My numbers")
                    ForEach(store.owned) { number in
                        PhoneNumberCard(number: number) {
                            if await app.act("phone-numbers/\(number.id)/refresh") != nil { await store.load() }
                        }
                    }
                }
                ProductCatalogSection(title: "Buy a number", products: store.products) { buying = $0 }
            }
        }
        .navigationTitle("Phone numbers")
        .navigationBarTitleDisplayMode(.inline)
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
        VStack(alignment: .leading, spacing: 12) {
            HStack {
                Text("\(Format.flag(number.countryCode)) \(number.product ?? "Phone number")").font(.caption.weight(.semibold)).foregroundStyle(Palette.text3)
                Spacer()
                StatusPill.forStatus(number.status)
            }
            CopyRow(label: "Number", value: number.phoneNumber, monospaced: true)
            CodeDisplay(code: number.latestCode, checkedAt: number.codeFetchedAt, refresh: refresh)
        }
        .glassCard(padding: 16)
    }
}
