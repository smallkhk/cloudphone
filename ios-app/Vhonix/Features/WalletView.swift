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

    private var amountValue: Double { Double(amount.replacingOccurrences(of: ",", with: ".")) ?? 0 }

    var body: some View {
        LoadingView(isLoading: loading && wallet == nil, error: wallet == nil ? error : nil, retry: load) {
            if let wallet {
                Screen(backdrop: .emerald) {
                    BalanceCard(balance: wallet.balance, name: app.user?.name)
                        .entrance(0)

                    if !pending.isEmpty {
                        SectionTitle(title: "Waiting for payment")
                        ForEach(pending) { d in
                            Button { openDeposit = d } label: { DepositRow(deposit: d, showChevron: true) }
                                .buttonStyle(.pressable)
                        }
                    }

                    topUp(wallet)

                    SectionTitle(title: "Activity")
                    if wallet.transactions.isEmpty {
                        EmptyStateView(icon: "list.bullet.rectangle.portrait.fill", title: "No activity yet",
                                       message: "Top-ups and purchases will show up here.", colors: TileColors.slate, art: "art-wallet")
                            .glassCard()
                    } else {
                        VStack(spacing: 4) {
                            ForEach(Array(wallet.transactions.enumerated()), id: \.element.id) { index, t in
                                if index > 0 { CardDivider() }
                                TransactionRow(transaction: t)
                            }
                        }
                        .glassCard(padding: 12)
                    }

                    let past = wallet.deposits.filter { !pending.contains($0) }
                    if !past.isEmpty {
                        SectionTitle(title: "Past top-ups")
                        VStack(spacing: 4) {
                            ForEach(Array(past.enumerated()), id: \.element.id) { index, d in
                                if index > 0 { CardDivider() }
                                DepositRow(deposit: d, showChevron: false, plain: true)
                            }
                        }
                        .glassCard(padding: 12)
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

    @ViewBuilder
    private func topUp(_ wallet: Wallet) -> some View {
        HStack(spacing: 8) {
            SectionTitle(title: "Add funds", subtitle: "Pay with crypto — credited automatically")
            Spacer(minLength: 0)
            ArtImage(name: "art-wallet", size: 84)
                .padding(.vertical, -18)
        }

        if wallet.networks.isEmpty {
            NoteView(text: "Top-ups aren't available right now. Please contact support.", icon: "exclamationmark.triangle", tint: Palette.warning)
                .glassCard()
        } else {
            VStack(alignment: .leading, spacing: 16) {
                VStack(alignment: .leading, spacing: 7) {
                    Text("Amount").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
                    HStack(alignment: .firstTextBaseline, spacing: 4) {
                        Text("$").font(Typo.rounded(.largeTitle, .bold)).foregroundStyle(Palette.text3)
                        TextField("", text: $amount, prompt: Text("0").foregroundColor(Palette.text3))
                            .font(Typo.rounded(.largeTitle, .bold))
                            .foregroundStyle(Palette.text)
                            .keyboardType(.decimalPad)
                            .accessibilityLabel("Amount in US dollars")
                    }
                    Text("Minimum \(Format.money(wallet.minDeposit))").font(.caption).foregroundStyle(Palette.text3)
                }

                HStack(spacing: 8) {
                    ForEach([10, 25, 50, 100], id: \.self) { v in
                        Button("$\(v)") {
                            Haptics.select()
                            amount = String(v)
                        }
                        .buttonStyle(.chip(amountValue == Double(v) ? Palette.accentText : Palette.text))
                        .frame(maxWidth: .infinity)
                    }
                }

                VStack(alignment: .leading, spacing: 7) {
                    Text("Pay with").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
                    VStack(spacing: 8) {
                        ForEach(wallet.networks) { n in
                            NetworkOption(network: n, selected: network == n.code) {
                                Haptics.select()
                                withAnimation(.spring(response: 0.3)) { network = n.code }
                            }
                        }
                    }
                }

                AsyncButton { await createDeposit() } label: {
                    HStack(spacing: 8) {
                        Text("Continue")
                        Image(systemName: "arrow.right")
                    }
                }
                .buttonStyle(.primary)
                .disabled(amountValue < wallet.minDeposit || network.isEmpty)
            }
            .glassCard(padding: 18, highlight: true, tint: Palette.success)
            .entrance(1)
        }
    }

    private func createDeposit() async {
        guard let result = await app.act("wallet/deposits", body: ["amount_usd": amountValue, "network": network]) else { return }

        amount = ""
        await load()

        if let id = result.data?["newDepositId"]?.intValue, let d = wallet?.deposits.first(where: { $0.id == id }) {
            openDeposit = d
        }
    }

    private func load() async {
        if wallet == nil, let saved: Wallet = APIClient.shared.cached("wallet") {
            wallet = saved
            if network.isEmpty { network = saved.networks.first?.code ?? "" }
            loading = false
        }
        do {
            let w: Wallet = try await APIClient.shared.get("wallet")
            withAnimation(.easeOut(duration: 0.25)) { wallet = w }
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

/// The wallet balance, styled as a premium card.
struct BalanceCard: View {
    let balance: Double
    let name: String?

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var tilt: CGSize = .zero
    @State private var shine: CGFloat = -1

    var body: some View {
        card
            .overlay(
                GeometryReader { geo in
                    LinearGradient(colors: [.clear, Color.white.opacity(0.35), .clear], startPoint: .leading, endPoint: .trailing)
                        .frame(width: geo.size.width * 0.45)
                        .rotationEffect(.degrees(20))
                        .offset(x: shine * geo.size.width * 1.3)
                        .blendMode(.plusLighter)
                }
                .clipShape(RoundedRectangle(cornerRadius: 26, style: .continuous))
                .allowsHitTesting(false)
            )
            .rotation3DEffect(.degrees(Double(tilt.width) / 14), axis: (x: 0, y: 1, z: 0), perspective: 0.6)
            // Simultaneous so a vertical swipe on the card still scrolls the page.
            .simultaneousGesture(
                DragGesture(minimumDistance: 6)
                    .onChanged { value in
                        guard !reduceMotion else { return }
                        tilt = CGSize(width: max(-120, min(120, value.translation.width)), height: 0)
                    }
                    .onEnded { _ in
                        withAnimation(.spring(response: 0.5, dampingFraction: 0.55)) { tilt = .zero }
                    }
            )
            .onAppear {
                guard !reduceMotion, shine < 0 else { return }
                withAnimation(.easeInOut(duration: 1.4).delay(0.3)) { shine = 1.2 }
            }
    }

    private var card: some View {
        ZStack(alignment: .topLeading) {
            RoundedRectangle(cornerRadius: 26, style: .continuous)
                .fill(LinearGradient(colors: [Color(hex: 0x4A3BD6), Color(hex: 0x7B3FD9), Color(hex: 0x2A8FD6)],
                                     startPoint: .topLeading, endPoint: .bottomTrailing))
                .overlay(
                    Circle().fill(Color.white.opacity(0.12)).frame(width: 260, height: 260)
                        .offset(x: 150, y: -110).blur(radius: 2)
                )
                .overlay(
                    Circle().fill(Color.white.opacity(0.06)).frame(width: 200, height: 200)
                        .offset(x: -90, y: 120)
                )
                .clipShape(RoundedRectangle(cornerRadius: 26, style: .continuous))
                .overlay(RoundedRectangle(cornerRadius: 26, style: .continuous).strokeBorder(Color.white.opacity(0.25), lineWidth: 1))
                .shadow(color: Palette.indigo.opacity(0.5), radius: 28, y: 14)

            VStack(alignment: .leading, spacing: 0) {
                HStack {
                    Text(Config.appName.uppercased())
                        .font(Typo.rounded(.footnote, .heavy))
                        .tracking(2)
                    Spacer()
                    Image(systemName: "wave.3.right").font(.headline)
                }
                .foregroundStyle(.white.opacity(0.9))

                Spacer(minLength: 18)

                Text("Available balance").font(.subheadline.weight(.medium)).foregroundStyle(.white.opacity(0.8))
                Text(Format.money(balance))
                    .font(.system(size: 42, weight: .bold, design: .rounded))
                    .foregroundStyle(.white)
                    .minimumScaleFactor(0.6)
                    .lineLimit(1)
                    .contentTransition(.numericText())

                Spacer(minLength: 14)

                HStack {
                    Text(name ?? "").font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(.white.opacity(0.9))
                    Spacer()
                    RoundedRectangle(cornerRadius: 5).fill(LinearGradient(colors: [Color(hex: 0xF7D774), Color(hex: 0xD9A13B)], startPoint: .top, endPoint: .bottom))
                        .frame(width: 38, height: 28)
                        .overlay(RoundedRectangle(cornerRadius: 5).strokeBorder(Color.black.opacity(0.15), lineWidth: 1))
                }
            }
            .padding(22)
        }
        .frame(height: 210)
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Wallet balance \(Format.money(balance))")
    }
}

private struct NetworkOption: View {
    let network: Network
    let selected: Bool
    let action: () -> Void

    private var colors: (Color, Color) {
        switch network.code {
        case "TRC20": return (Color(hex: 0xEF4444), Color(hex: 0xB91C1C))
        case "BEP20": return (Color(hex: 0xF3BA2F), Color(hex: 0xD99A10))
        case "LTC": return (Color(hex: 0x6B8CCE), Color(hex: 0x345D9D))
        default: return TileColors.indigo
        }
    }

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                IconTile(symbol: network.code == "LTC" ? "l.circle.fill" : "dollarsign.circle.fill", colors: colors, size: 36)
                Text(network.label).font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                Spacer()
                Image(systemName: selected ? "checkmark.circle.fill" : "circle")
                    .font(.title3)
                    .foregroundStyle(selected ? Palette.accentText : Palette.text3)
            }
            .padding(.horizontal, 12)
            .frame(minHeight: 56)
            .background(RoundedRectangle(cornerRadius: 14, style: .continuous).fill(selected ? Palette.indigo.opacity(0.16) : Palette.surface))
            .overlay(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .strokeBorder(selected ? AnyShapeStyle(Theme.brand) : AnyShapeStyle(Palette.stroke), lineWidth: selected ? 1.5 : 1)
            )
        }
        .buttonStyle(.pressable)
        .accessibilityAddTraits(selected ? .isSelected : [])
    }
}

struct TransactionRow: View {
    let transaction: WalletTransaction

    var body: some View {
        let credit = transaction.amount >= 0
        HStack(spacing: 12) {
            Image(systemName: credit ? "arrow.down.left" : "arrow.up.right")
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(credit ? Palette.success : Palette.text2)
                .frame(width: 38, height: 38)
                .background(Circle().fill((credit ? Palette.success : Color.white).opacity(0.12)))
            VStack(alignment: .leading, spacing: 2) {
                Text(transaction.description?.nonEmpty ?? Format.status(transaction.type))
                    .font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.text).lineLimit(2)
                Text(Format.when(transaction.createdAt)).font(.caption).foregroundStyle(Palette.text3)
            }
            Spacer()
            Text((credit ? "+" : "") + Format.money(transaction.amount))
                .font(Typo.rounded(.subheadline, .bold))
                .foregroundStyle(credit ? Palette.success : Palette.text)
        }
        .frame(minHeight: 50)
        .accessibilityElement(children: .combine)
    }
}

struct DepositRow: View {
    let deposit: Deposit
    var showChevron = false
    var plain = false

    var body: some View {
        let row = HStack(spacing: 12) {
            IconTile(symbol: "arrow.down.circle.fill", colors: deposit.status == "confirmed" ? TileColors.green : TileColors.indigo, size: 38)
            VStack(alignment: .leading, spacing: 2) {
                Text("\(Format.money(deposit.amountUsd)) · \(deposit.network ?? "")")
                    .font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.text)
                Text(Format.when(deposit.createdAt)).font(.caption).foregroundStyle(Palette.text3)
            }
            Spacer()
            StatusPill.forStatus(deposit.status)
            if showChevron {
                Image(systemName: "chevron.right").font(.caption.weight(.bold)).foregroundStyle(Palette.text3)
            }
        }
        .frame(minHeight: 50)

        if plain {
            row
        } else {
            row.glassCard(padding: 14)
        }
    }
}

struct DepositPaymentView: View {
    let deposit: Deposit
    let reload: () async -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        SheetScaffold(title: "Top up \(Format.money(deposit.amountUsd))") {
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

            Button("Done") { dismiss() }
                .buttonStyle(.glass)
        }
    }
}
