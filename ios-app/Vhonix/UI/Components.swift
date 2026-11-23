import SwiftUI
import CoreImage.CIFilterBuiltins

/// A value with a copy button.
struct CopyRow: View {
    let label: String
    let value: String?
    var monospaced = false

    @EnvironmentObject private var app: AppState

    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            VStack(alignment: .leading, spacing: 2) {
                Text(label).font(.caption).foregroundStyle(.secondary)
                Text(value?.nonEmpty ?? "—")
                    .font(monospaced ? .body.monospaced() : .body)
                    .textSelection(.enabled)
            }
            Spacer()
            if let value = value?.nonEmpty {
                Button {
                    UIPasteboard.general.string = value
                    app.show("\(label) copied.")
                } label: {
                    Image(systemName: "doc.on.doc")
                }
                .buttonStyle(.borderless)
            }
        }
    }
}

struct QRCodeView: View {
    let text: String

    var body: some View {
        if let image = Self.make(text) {
            Image(uiImage: image)
                .interpolation(.none)
                .resizable()
                .scaledToFit()
                .padding(10)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 12))
        }
    }

    static func make(_ text: String) -> UIImage? {
        let filter = CIFilter.qrCodeGenerator()
        filter.message = Data(text.utf8)
        filter.correctionLevel = "M"
        guard let output = filter.outputImage?.transformed(by: CGAffineTransform(scaleX: 10, y: 10)),
              let cg = CIContext().createCGImage(output, from: output.extent) else { return nil }
        return UIImage(cgImage: cg)
    }
}

/// Loading / error / content wrapper used by every screen that fetches data.
struct LoadingView<Content: View>: View {
    let isLoading: Bool
    let error: String?
    let retry: () async -> Void
    @ViewBuilder let content: () -> Content

    var body: some View {
        if let error, !isLoading {
            VStack(spacing: 14) {
                Image(systemName: "wifi.exclamationmark").font(.largeTitle).foregroundStyle(.secondary)
                Text(error).multilineTextAlignment(.center).foregroundStyle(.secondary)
                Button("Try again") { Task { await retry() } }.buttonStyle(.bordered)
            }
            .padding(32)
            .frame(maxWidth: .infinity, maxHeight: .infinity)
        } else if isLoading {
            ProgressView().frame(maxWidth: .infinity, maxHeight: .infinity)
        } else {
            content()
        }
    }
}

struct EmptyStateView: View {
    let icon: String
    let title: String
    var message: String? = nil

    var body: some View {
        VStack(spacing: 10) {
            Image(systemName: icon).font(.system(size: 40)).foregroundStyle(.secondary)
            Text(title).font(.headline)
            if let message {
                Text(message).font(.subheadline).foregroundStyle(.secondary).multilineTextAlignment(.center)
            }
        }
        .padding(28)
        .frame(maxWidth: .infinity)
    }
}

/// Lets a customer pick one of their own ready devices.
struct DevicePicker: View {
    let devices: [DeviceRef]
    @Binding var padCode: String

    var body: some View {
        Picker("Device", selection: $padCode) {
            Text("Choose a device").tag("")
            ForEach(devices) { d in
                Text(d.name + " · " + d.padCode).tag(d.padCode)
            }
        }
    }
}

/// Small async button that shows a spinner while its action runs.
struct AsyncButton<Label: View>: View {
    var role: ButtonRole? = nil
    let action: () async -> Void
    @ViewBuilder let label: () -> Label

    @State private var running = false

    var body: some View {
        Button(role: role) {
            guard !running else { return }
            running = true
            Task {
                await action()
                running = false
            }
        } label: {
            HStack(spacing: 8) {
                if running { ProgressView() }
                label()
            }
        }
        .disabled(running)
    }
}

extension AsyncButton where Label == Text {
    init(_ title: String, role: ButtonRole? = nil, action: @escaping () async -> Void) {
        self.role = role
        self.action = action
        self.label = { Text(title) }
    }
}

/// Wallet-style card that shows where to send a crypto payment, with QR and tx-hash box.
struct CryptoPaymentCard: View {
    let network: String?
    let currency: String?
    let address: String?
    let amountCrypto: Double?
    let amountUsd: Double?
    let expiresAt: String?
    let txHash: String?
    let status: String?
    let submit: (String) async -> Void

    @State private var hash = ""

    var body: some View {
        Section {
            if let address = address?.nonEmpty {
                HStack {
                    Spacer()
                    QRCodeView(text: address).frame(width: 190, height: 190)
                    Spacer()
                }
                .listRowBackground(Color.clear)

                CopyRow(label: "Send exactly", value: "\(Format.crypto(amountCrypto)) \(currency ?? "")".trimmingCharacters(in: .whitespaces), monospaced: true)
                CopyRow(label: "To this \(network ?? "") address", value: address, monospaced: true)
            }

            if let amountUsd {
                LabeledContent("Value", value: Format.money(amountUsd))
            }
            if let expiresAt {
                LabeledContent("Pay before", value: Format.when(expiresAt))
            }

            if let txHash = txHash?.nonEmpty {
                CopyRow(label: "Transaction hash", value: txHash, monospaced: true)
            } else if status == "awaiting_payment" {
                TextField("Paste transaction hash (optional)", text: $hash)
                    .textInputAutocapitalization(.never)
                    .autocorrectionDisabled()
                    .font(.body.monospaced())
                AsyncButton("Submit transaction hash") {
                    await submit(hash.trimmingCharacters(in: .whitespacesAndNewlines))
                }
                .disabled(hash.trimmingCharacters(in: .whitespaces).count < 10)
            }
        } header: {
            Text("Payment · \(Format.status(status))")
        } footer: {
            Text("Send the exact amount on the \(network ?? "shown") network. Payments are usually detected automatically within a few minutes; pasting the transaction hash makes it faster.")
        }
    }
}

extension View {
    /// iOS 16-friendly version of `navigationDestination(item:)`.
    func navigationDestination<Item, Destination: View>(
        unwrapping item: Binding<Item?>,
        @ViewBuilder destination: @escaping (Item) -> Destination
    ) -> some View {
        navigationDestination(isPresented: Binding(
            get: { item.wrappedValue != nil },
            set: { if !$0 { item.wrappedValue = nil } }
        )) {
            if let value = item.wrappedValue { destination(value) }
        }
    }
}
