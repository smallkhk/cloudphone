import SwiftUI

struct MainTabView: View {
    @EnvironmentObject private var app: AppState

    var body: some View {
        TabView(selection: $app.tab) {
            NavigationStack { DevicesView() }
                .tabItem { Label("Phones", systemImage: "iphone") }
                .tag(MainTab.devices)

            NavigationStack { ShopView() }
                .tabItem { Label("Shop", systemImage: "bag") }
                .tag(MainTab.shop)

            NavigationStack { WalletView() }
                .tabItem { Label("Wallet", systemImage: "wallet.pass") }
                .tag(MainTab.wallet)

            NavigationStack { MoreView() }
                .tabItem { Label("More", systemImage: "square.grid.2x2") }
                .tag(MainTab.more)
        }
        .task { await app.refreshUser() }
    }
}

struct MoreView: View {
    @EnvironmentObject private var app: AppState
    @State private var confirmLock = false

    var body: some View {
        List {
            if let user = app.user {
                Section {
                    VStack(alignment: .leading, spacing: 4) {
                        Text(user.name).font(.headline)
                        Text(user.email).font(.subheadline).foregroundStyle(.secondary)
                    }
                    LabeledContent("Wallet balance", value: Format.money(user.balance))
                }
            }

            Section("My products") {
                NavigationLink { OrdersView() } label: { Label("Orders", systemImage: "list.bullet.rectangle") }
                NavigationLink { EmailAccountsView() } label: { Label("Email accounts", systemImage: "envelope") }
                NavigationLink { PhoneNumbersView() } label: { Label("Phone numbers", systemImage: "phone") }
                NavigationLink { CloudNumbersView() } label: { Label("Cloud numbers", systemImage: "antenna.radiowaves.left.and.right") }
                NavigationLink { ProxiesView() } label: { Label("Proxies", systemImage: "network") }
                NavigationLink { CloudDriveView() } label: { Label("Cloud Drive", systemImage: "externaldrive") }
            }

            Section("Account") {
                Link(destination: Config.siteURL) {
                    Label("Open website", systemImage: "safari")
                }
                AsyncButton(role: .destructive) {
                    await app.signOut()
                } label: {
                    Label("Sign out", systemImage: "rectangle.portrait.and.arrow.right")
                }
                Button(role: .destructive) {
                    confirmLock = true
                } label: {
                    Label("Sign out and remove access code", systemImage: "lock")
                }
            }

            Section {
                Text("\(Config.appName) \(Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "")")
                    .font(.footnote).foregroundStyle(.secondary)
            }
        }
        .navigationTitle("More")
        .refreshable { await app.refreshUser() }
        .confirmationDialog("Remove the access code from this iPhone? You'll need to enter it again.", isPresented: $confirmLock, titleVisibility: .visible) {
            Button("Remove and lock", role: .destructive) { Task { await app.lockApp() } }
        }
    }
}
