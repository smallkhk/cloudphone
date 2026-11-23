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
            products = r.products
            owned = r.owned
            devices = r.devices
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
        NavigationStack {
            Form {
                Section {
                    LabeledContent("Product", value: sku.name)
                    if let c = sku.countryName { LabeledContent("Country", value: "\(Format.flag(sku.countryCode)) \(c)") }
                    if let d = sku.durationLabel { LabeledContent("Duration", value: d) }
                    LabeledContent("Price", value: Format.money(sku.price))
                    if maxQuantity > 1 {
                        Stepper("Quantity: \(quantity)", value: $quantity, in: 1...maxQuantity)
                    }
                }

                Section {
                    LabeledContent("Total", value: Format.money(sku.price * Double(quantity))).font(.headline)
                } footer: {
                    Text("Next you'll see the crypto address and exact amount to pay. Delivery starts automatically once payment confirms.")
                }

                Section {
                    Button {
                        Task { await buy() }
                    } label: {
                        if busy { ProgressView().tint(.white) } else { Text("Continue to payment") }
                    }
                    .buttonStyle(.primary)
                    .disabled(busy)
                    .listRowBackground(Color.clear)
                }
            }
            .navigationTitle("Checkout")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
            }
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

struct ProductRow: View {
    let sku: Sku

    var body: some View {
        HStack {
            VStack(alignment: .leading, spacing: 3) {
                Text(sku.name).font(.subheadline.weight(.semibold)).foregroundStyle(.primary)
                HStack(spacing: 6) {
                    if let c = sku.countryName { Text("\(Format.flag(sku.countryCode)) \(c)") }
                    if let d = sku.durationLabel { Text(d) }
                }
                .font(.caption).foregroundStyle(.secondary)
            }
            Spacer()
            if sku.soldOut == true {
                StatusPill(text: "Sold out", color: .red)
            } else {
                Text(Format.money(sku.price)).font(.subheadline.weight(.semibold)).foregroundStyle(Theme.accent)
            }
        }
    }
}

/// "Buy" list grouped by country where it matters.
struct ProductCatalogSection: View {
    let title: String
    let products: [Sku]
    let select: (Sku) -> Void

    var body: some View {
        Section(title) {
            if products.isEmpty {
                Text("Nothing available right now.").foregroundStyle(.secondary)
            }
            ForEach(products) { sku in
                Button { select(sku) } label: { ProductRow(sku: sku) }
                    .disabled(sku.soldOut == true)
            }
        }
    }
}
