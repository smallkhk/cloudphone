import SwiftUI

struct WalletView: View {
    @EnvironmentObject private var app: AppState
    @State private var wallet: Wallet?
    @State private var loading = true
    @State private var error: String?
    @State private var amount = ""
    @State private var network = ""
    @State private var openDeposit: Deposit?

    private var pending: [Deposit] {
        (wallet?.deposits ?? []).filter { $0.status == "awaiting_payment" || $0.status == "submitted" }
    }

    var body: some View {
        LoadingView(isLoading: loading && wallet == nil, error: wallet == nil ? error : nil, retry: load) {
            if let wallet {
                List {
                    Section {
                        VStack(alignment: .leading, spacing: 6) {
                            Text("Balance").font(.subheadline).foregroundStyle(.white.opacity(0.8))
                            Text(Format.money(wallet.balance)).font(.system(size: 38, weight: .bold, design: .rounded))
                        }
                        .foregroundStyle(.white)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(20)
                        .background(Theme.gradient)
                        .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                        .listRowInsets(EdgeInsets())
                        .listRowBackground(Color.clear)
                    }

                    if wallet.networks.isEmpty {
                        Section {
                            Text("Top-ups aren't available right now. Please contact support.").foregroundStyle(.secondary)
                        }
                    } else {
                        Section {
                            TextField("Amount in USD (min \(Format.money(wallet.minDeposit)))", text: $amount)
                                .keyboardType(.decimalPad)
                            Picker("Pay with", selection: $network) {
                                ForEach(wallet.networks) { n in Text(n.label).tag(n.code) }
                            }
                            AsyncButton("Continue") { await createDeposit() }
                                .disabled((Double(amount.replacingOccurrences(of: ",", with: ".")) ?? 0) < wallet.minDeposit || network.isEmpty)
                        } header: {
                            Text("Add funds")
                        } footer: {
                            Text("You'll get an address and exact amount to send. Your balance updates automatically once the payment confirms.")
                        }
                    }

                    if !pending.isEmpty {
                        Section("Waiting for payment") {
                            ForEach(pending) { d in
                                Button { openDeposit = d } label: { DepositRow(deposit: d) }
                            }
                        }
                    }

                    Section("History") {
                        if wallet.transactions.isEmpty {
                            Text("No transactions yet.").foregroundStyle(.secondary)
                        }
                        ForEach(wallet.transactions) { t in
                            HStack {
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(t.description?.nonEmpty ?? Format.status(t.type)).font(.subheadline)
                                    Text(Format.when(t.createdAt)).font(.caption).foregroundStyle(.secondary)
                                }
                                Spacer()
                                Text((t.amount >= 0 ? "+" : "") + Format.money(t.amount))
                                    .font(.subheadline.weight(.semibold))
                                    .foregroundStyle(t.amount >= 0 ? .green : .primary)
                            }
                        }
                    }

                    let past = wallet.deposits.filter { !pending.contains($0) }
                    if !past.isEmpty {
                        Section("Past top-ups") {
                            ForEach(past) { d in DepositRow(deposit: d) }
                        }
                    }
                }
            }
        }
        .navigationTitle("Wallet")
        .refreshable { await load() }
        .task { await load() }
        .task(id: pending.map(\.id)) {
            guard !pending.isEmpty else { return }
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 20_000_000_000)
                if Task.isCancelled { break }
                await load()
            }
        }
        .sheet(item: $openDeposit) { d in
            DepositPaymentView(deposit: d) { await load() }
        }
    }

    private func createDeposit() async {
        let value = Double(amount.replacingOccurrences(of: ",", with: ".")) ?? 0
        guard let result = await app.act("wallet/deposits", body: ["amount_usd": value, "network": network]) else { return }

        amount = ""
        await load()

        if let id = result.data?["newDepositId"]?.intValue, let d = wallet?.deposits.first(where: { $0.id == id }) {
            openDeposit = d
        }
    }

    private func load() async {
        do {
            let w: Wallet = try await APIClient.shared.get("wallet")
            wallet = w
            if network.isEmpty || !w.networks.contains(where: { $0.code == network }) {
                network = w.networks.first?.code ?? ""
            }
            if let current = app.user, current.balance != w.balance {
                await app.refreshUser()
            }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

struct DepositRow: View {
    let deposit: Deposit

    var body: some View {
        HStack {
            VStack(alignment: .leading, spacing: 2) {
                Text("\(Format.money(deposit.amountUsd)) · \(deposit.network ?? "")").font(.subheadline).foregroundStyle(.primary)
                Text(Format.when(deposit.createdAt)).font(.caption).foregroundStyle(.secondary)
            }
            Spacer()
            StatusPill.forStatus(deposit.status)
        }
    }
}

struct DepositPaymentView: View {
    let deposit: Deposit
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            List {
                CryptoPaymentCard(
                    network: deposit.network, currency: deposit.currency, address: deposit.address,
                    amountCrypto: deposit.amountCrypto, amountUsd: deposit.amountUsd, expiresAt: deposit.expiresAt,
                    txHash: deposit.txHash, status: deposit.status
                ) { hash in
                    if await app.act("wallet/deposits/\(deposit.id)/tx", body: ["tx_hash": hash]) != nil {
                        await reload()
                        dismiss()
                    }
                }
            }
            .navigationTitle("Top up \(Format.money(deposit.amountUsd))")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) { Button("Done") { dismiss() } }
            }
        }
    }
}
