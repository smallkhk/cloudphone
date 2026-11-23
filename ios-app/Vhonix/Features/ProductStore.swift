import SwiftUI

/// Loads `products/{type}` — the storefront plus what the customer already owns.
@MainActor
final class ProductStore<Owned: Decodable>: ObservableObject {
    let type: String

    @Published var products: [Sku] = []
    @Published var owned: [Owned] = []
    @Published var devices: [DeviceRef] = []
    @Published var loading = true
    @Published var error: String?

    init(type: String) { self.type = type }

    func load() async {
        do {
            let r: ProductsResponse<Owned> = try await APIClient.shared.get("products/\(type)")
            withAnimation(.easeOut(duration: 0.25)) {
                products = r.products
                owned = r.owned
                devices = r.devices
            }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}

/// Order identifier used to open the payment screen after a purchase.
struct OrderRoute: Identifiable, Hashable { let id: Int }

/// Checkout for email accounts, numbers and proxies — creates the order, then
/// shows its crypto payment screen (same as the website).
struct BuyProductView: View {
    let sku: Sku
    var maxQuantity = 20
    let onOrder: (Int?) -> Void

    @EnvironmentObject private var app: AppState
    @Environment(\.dismiss) private var dismiss
    @State private var quantity = 1
    @State private var busy = false

    var body: some View {
        SheetScaffold(title: "Checkout") {
            HStack(spacing: 14) {
                IconTile(symbol: productIcon(for: sku.type), colors: productColors(for: sku.type), size: 54)
                VStack(alignment: .leading, spacing: 4) {
                    Text(sku.name).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text)
                    Text([sku.countryName.map { "\(Format.flag(sku.countryCode)) \($0)" }, sku.durationLabel].compactMap { $0 }.joined(separator: " · "))
                        .font(.subheadline).foregroundStyle(Palette.text2)
                }
            }
            .glassCard(highlight: true)

            VStack(spacing: 14) {
                InfoRow(label: "Price", value: Format.money(sku.price))
                if maxQuantity > 1 {
                    CardDivider()
                    HStack {
                        Text("Quantity").font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                        Spacer()
                        QuantityStepper(value: $quantity, range: 1...maxQuantity)
                    }
                }
                CardDivider()
                HStack(alignment: .firstTextBaseline) {
                    Text("Total").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
                    Spacer()
                    Text(Format.money(sku.price * Double(quantity))).font(Typo.rounded(.title, .bold)).foregroundStyle(Palette.text)
                        .contentTransition(.numericText())
                        .animation(.spring(), value: quantity)
                }
            }
            .glassCard(padding: 18)

            Button {
                Task { await buy() }
            } label: {
                HStack(spacing: 10) {
                    if busy { ProgressView().tint(.white) }
                    Text("Continue to payment")
                    Image(systemName: "arrow.right")
                }
            }
            .buttonStyle(.primary)
            .disabled(busy)

            NoteView(text: "Next you'll see the crypto address and exact amount to pay. Delivery starts automatically once it confirms.", icon: "lock.shield")
        }
    }

    private func buy() async {
        busy = true
        defer { busy = false }

        if let result = await app.act("orders", body: ["sku_id": sku.id, "quantity": quantity]) {
            dismiss()
            onOrder(result.locationId)
        }
    }
}

/// One buyable product as a card row.
struct ProductRow: View {
    let sku: Sku

    var body: some View {
        HStack(spacing: 14) {
            Text(Format.flag(sku.countryCode)).font(.title2)
                .frame(width: 44, height: 44)
                .background(Circle().fill(Palette.surfaceStrong))
            VStack(alignment: .leading, spacing: 3) {
                Text(sku.name).font(Typo.rounded(.subheadline, .bold)).foregroundStyle(Palette.text).lineLimit(2)
                Text([sku.countryName, sku.durationLabel].compactMap { $0 }.joined(separator: " · "))
                    .font(.caption).foregroundStyle(Palette.text3).lineLimit(1)
            }
            Spacer(minLength: 6)
            if sku.soldOut == true {
                StatusPill(text: "Sold out", color: Palette.danger)
            } else {
                Text(Format.money(sku.price)).font(Typo.rounded(.headline, .bold)).foregroundStyle(Theme.brandText)
            }
        }
        .glassCard(padding: 14)
    }
}

/// A list of buyable products.
struct ProductCatalogSection: View {
    let title: String
    let products: [Sku]
    let select: (Sku) -> Void

    var body: some View {
        SectionTitle(title: title)
        if products.isEmpty {
            EmptyStateView(icon: "shippingbox.fill", title: "Nothing available right now", message: "Check back a little later.", colors: TileColors.slate)
                .glassCard()
        }
        ForEach(products) { sku in
            Button {
                Haptics.tap()
                select(sku)
            } label: { ProductRow(sku: sku) }
            .buttonStyle(.pressable)
            .disabled(sku.soldOut == true)
        }
    }
}
