import SwiftUI

struct MainTabView: View {
    @EnvironmentObject private var app: AppState

    private let tabs: [MainTab] = [.devices, .shop, .wallet, .more]

    var body: some View {
        ZStack {
            ForEach(tabs, id: \.self) { tab in
                root(for: tab)
                    .opacity(app.tab == tab ? 1 : 0)
                    .allowsHitTesting(app.tab == tab)
                    .accessibilityHidden(app.tab != tab)
            }
        }
        .safeAreaInset(edge: .bottom, spacing: 0) { Color.clear.frame(height: 76) }
        .overlay(alignment: .bottom) {
            FloatingTabBar(selection: $app.tab)
        }
        .task { await app.refreshUser() }
    }

    @ViewBuilder
    private func root(for tab: MainTab) -> some View {
        switch tab {
        case .devices: NavigationStack { DevicesView() }
        case .shop: NavigationStack { ShopView() }
        case .wallet: NavigationStack { WalletView() }
        case .more: NavigationStack { MoreView() }
        }
    }
}

struct FloatingTabBar: View {
    @Binding var selection: MainTab
    @Namespace private var ns

    private struct Item: Identifiable {
        let tab: MainTab
        let title: String
        let icon: String
        var id: MainTab { tab }
    }

    private let items: [Item] = [
        Item(tab: .devices, title: "Phones", icon: "iphone.gen3"),
        Item(tab: .shop, title: "Shop", icon: "bag.fill"),
        Item(tab: .wallet, title: "Wallet", icon: "creditcard.fill"),
        Item(tab: .more, title: "More", icon: "square.grid.2x2.fill"),
    ]

    var body: some View {
        HStack(spacing: 4) {
            ForEach(items) { item in
                let tab = item.tab
                let title = item.title
                let icon = item.icon
                let active = selection == tab
                Button {
                    guard selection != tab else { return }
                    Haptics.select()
                    withAnimation(.spring(response: 0.4, dampingFraction: 0.78)) { selection = tab }
                } label: {
                    VStack(spacing: 3) {
                        Image(systemName: icon).font(.system(size: 19, weight: .semibold))
                        Text(title).font(Typo.rounded(.caption2, .semibold))
                    }
                    .foregroundStyle(active ? Color.white : Palette.text3)
                    .frame(maxWidth: .infinity, minHeight: 52)
                    .background {
                        if active {
                            Capsule().fill(Theme.gradient)
                                .matchedGeometryEffect(id: "tab", in: ns)
                                .shadow(color: Palette.indigo.opacity(0.5), radius: 10, y: 4)
                        }
                    }
                    .contentShape(Capsule())
                }
                .buttonStyle(.plain)
                .accessibilityLabel(title)
                .accessibilityAddTraits(active ? [.isSelected, .isButton] : .isButton)
            }
        }
        .padding(6)
        .background(
            Capsule().fill(.ultraThinMaterial)
                .overlay(Capsule().fill(Palette.bgRaised.opacity(0.55)))
        )
        .overlay(Capsule().strokeBorder(Color.white.opacity(0.12), lineWidth: 1))
        .shadow(color: .black.opacity(0.5), radius: 24, y: 10)
        .padding(.horizontal, 18)
        .padding(.bottom, 6)
        .ignoresSafeArea(.keyboard)
    }
}

struct MoreView: View {
    @EnvironmentObject private var app: AppState
    @State private var confirmSignOut = false

    private let columns = [GridItem(.flexible(), spacing: 12), GridItem(.flexible(), spacing: 12)]

    var body: some View {
        Screen(animatedBackground: true) {
            if let user = app.user {
                HStack(spacing: 14) {
                    Text(String(user.name.prefix(1)).uppercased())
                        .font(Typo.rounded(.title2, .bold))
                        .foregroundStyle(.white)
                        .frame(width: 58, height: 58)
                        .background(Circle().fill(Theme.brand))
                        .overlay(Circle().strokeBorder(Color.white.opacity(0.3), lineWidth: 1))
                    VStack(alignment: .leading, spacing: 3) {
                        Text(user.name).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                        Text(user.email).font(.subheadline).foregroundStyle(Palette.text2).lineLimit(1)
                    }
                    Spacer()
                }
                .glassCard(padding: 18, highlight: true)

                Button { app.tab = .wallet } label: {
                    HStack {
                        IconTile(symbol: "creditcard.fill", colors: TileColors.green, size: 38)
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Wallet balance").font(.caption).foregroundStyle(Palette.text3)
                            Text(Format.money(user.balance)).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                        }
                        Spacer()
                        Text("Add funds").font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.accentText)
                    }
                    .glassCard()
                }
                .buttonStyle(.pressable)
            }

            SectionTitle(title: "Your products")
            LazyVGrid(columns: columns, spacing: 12) {
                tile("Orders", "Receipts & payments", "doc.text.fill", TileColors.slate) { OrdersView() }
                tile("Email accounts", "Inboxes & codes", "envelope.fill", TileColors.cyan) { EmailAccountsView() }
                tile("Phone numbers", "Verification SMS", "phone.fill", TileColors.green) { PhoneNumbersView() }
                tile("Cloud numbers", "Rent a real number", "antenna.radiowaves.left.and.right", TileColors.pink) { CloudNumbersView() }
                tile("Proxies", "Residential IPs", "network", TileColors.orange) { ProxiesView() }
                tile("Cloud Drive", "Files & backups", "externaldrive.fill", TileColors.indigo) { CloudDriveView() }
            }

            SectionTitle(title: "Account")
            VStack(spacing: 4) {
                Link(destination: Config.siteURL) {
                    MenuRow(icon: "safari.fill", colors: TileColors.cyan, title: "Open website")
                }
                CardDivider()
                Button { confirmSignOut = true } label: {
                    MenuRow(icon: "rectangle.portrait.and.arrow.right", colors: TileColors.red, title: "Sign out", showChevron: false)
                }
                .buttonStyle(.pressable)
            }
            .glassCard(padding: 12)

            Text("\(Config.appName) \(Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "")")
                .font(.footnote).foregroundStyle(Palette.text3)
                .frame(maxWidth: .infinity)
                .padding(.top, 4)
        }
        .navigationTitle("More")
        .refreshable { await app.refreshUser() }
        .confirmationDialog("Sign out of \(Config.appName)?", isPresented: $confirmSignOut, titleVisibility: .visible) {
            Button("Sign out", role: .destructive) { Task { await app.signOut() } }
        }
    }

    private func tile<D: View>(_ title: String, _ subtitle: String, _ icon: String, _ colors: (Color, Color), @ViewBuilder destination: @escaping () -> D) -> some View {
        NavigationLink {
            destination()
        } label: {
            VStack(alignment: .leading, spacing: 12) {
                IconTile(symbol: icon, colors: colors, size: 44)
                VStack(alignment: .leading, spacing: 2) {
                    Text(title).font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                    Text(subtitle).font(.caption).foregroundStyle(Palette.text3).lineLimit(1)
                }
            }
            .frame(maxWidth: .infinity, minHeight: 112, alignment: .topLeading)
            .glassCard()
        }
        .buttonStyle(.pressable)
    }
}
