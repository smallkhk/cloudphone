import SwiftUI
import CoreImage.CIFilterBuiltins

// MARK: - Layout

/// Scrollable screen made of stacked cards.
struct Screen<Content: View>: View {
    var backdrop: Backdrop = .indigo
    var spacing: CGFloat = 18
    @ViewBuilder let content: () -> Content

    var body: some View {
        ScrollView {
            // Lazy: rows off screen aren't built. A plain VStack built every
            // card at once and froze the app on long lists (watchdog kill).
            LazyVStack(alignment: .leading, spacing: spacing) {
                content()
            }
            .padding(.horizontal, 16)
            .padding(.top, 6)
            .padding(.bottom, 28)
        }
        .screenBackground(backdrop)
    }
}

struct SectionTitle: View {
    let title: String
    var subtitle: String? = nil
    var trailing: AnyView? = nil

    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(Typo.rounded(.title3, .bold))
                    .foregroundStyle(Palette.text)
                if let subtitle {
                    Text(subtitle).font(.footnote).foregroundStyle(Palette.text3)
                }
            }
            Spacer(minLength: 8)
            if let trailing { trailing }
        }
        .padding(.horizontal, 4)
        .padding(.top, 6)
        .accessibilityAddTraits(.isHeader)
    }
}

struct CardDivider: View {
    var body: some View {
        Rectangle().fill(Palette.stroke).frame(height: 1)
    }
}

/// Small note with an icon, for explanations under forms.
struct NoteView: View {
    let text: String
    var icon = "info.circle"
    var tint: Color = Palette.text3

    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: icon).foregroundStyle(tint).font(.subheadline)
            Text(text).font(.footnote).foregroundStyle(Palette.text2)
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(.horizontal, 4)
    }
}

// MARK: - Rows

struct InfoRow: View {
    let label: String
    let value: String
    var valueColor: Color = Palette.text

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(label).font(.subheadline).foregroundStyle(Palette.text2)
            Spacer(minLength: 8)
            Text(value).font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(valueColor)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 2)
        .accessibilityElement(children: .combine)
    }
}

/// A value with a copy button (44pt target).
struct CopyRow: View {
    let label: String
    let value: String?
    var monospaced = false

    @EnvironmentObject private var app: AppState

    var body: some View {
        HStack(alignment: .center, spacing: 10) {
            VStack(alignment: .leading, spacing: 3) {
                Text(label).font(.caption).foregroundStyle(Palette.text3)
                Text(value?.nonEmpty ?? "—")
                    .font(monospaced ? .system(.callout, design: .monospaced, weight: .medium) : Typo.rounded(.callout, .medium))
                    .foregroundStyle(Palette.text)
                    .fixedSize(horizontal: false, vertical: true)
            }
            Spacer(minLength: 4)
            if let value = value?.nonEmpty {
                Button {
                    UIPasteboard.general.string = value
                    app.show("\(label) copied.")
                } label: {
                    Image(systemName: "doc.on.doc")
                        .font(.system(size: 15, weight: .semibold))
                        .foregroundStyle(Palette.accentText)
                        .frame(width: 44, height: 44)
                        .background(Circle().fill(Palette.surfaceStrong).frame(width: 36, height: 36))
                }
                .buttonStyle(.pressable)
                .accessibilityLabel("Copy \(label)")
            }
        }
    }
}

/// Tappable row with an icon tile, used for menus and settings lists.
struct MenuRow: View {
    let icon: String
    var colors: (Color, Color) = TileColors.indigo
    let title: String
    var subtitle: String? = nil
    var value: String? = nil
    var showChevron = true

    var body: some View {
        HStack(spacing: 14) {
            IconTile(symbol: icon, colors: colors, size: 38)
            VStack(alignment: .leading, spacing: 2) {
                Text(title).font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                if let subtitle {
                    Text(subtitle).font(.caption).foregroundStyle(Palette.text3).lineLimit(1)
                }
            }
            Spacer(minLength: 6)
            if let value {
                Text(value).font(.subheadline).foregroundStyle(Palette.text2).lineLimit(1)
            }
            if showChevron {
                Image(systemName: "chevron.right").font(.footnote.weight(.bold)).foregroundStyle(Palette.text3)
            }
        }
        .frame(minHeight: 48)
        .contentShape(Rectangle())
    }
}

// MARK: - Inputs

struct GlassField: View {
    let label: String
    @Binding var text: String
    var prompt = ""
    var keyboard: UIKeyboardType = .default
    var secure = false
    var monospaced = false
    var capitalization: TextInputAutocapitalization = .never
    var contentType: UITextContentType? = nil

    @FocusState private var focused: Bool

    var body: some View {
        VStack(alignment: .leading, spacing: 7) {
            Text(label).font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
            Group {
                if secure {
                    SecureField("", text: $text, prompt: Text(prompt).foregroundColor(Palette.text3))
                } else {
                    TextField("", text: $text, prompt: Text(prompt).foregroundColor(Palette.text3))
                }
            }
            .font(monospaced ? .system(.body, design: .monospaced) : .body)
            .foregroundStyle(Palette.text)
            .keyboardType(keyboard)
            .textInputAutocapitalization(capitalization)
            .autocorrectionDisabled()
            .textContentType(contentType)
            .focused($focused)
            .padding(.horizontal, 14)
            .frame(minHeight: 50)
            .background(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .fill(Color.white.opacity(focused ? 0.09 : 0.06))
            )
            .overlay(
                RoundedRectangle(cornerRadius: 14, style: .continuous)
                    .strokeBorder(focused ? AnyShapeStyle(Theme.brand) : AnyShapeStyle(Palette.stroke), lineWidth: focused ? 1.5 : 1)
            )
            .animation(.easeOut(duration: 0.18), value: focused)
            .accessibilityLabel(label)
        }
    }
}

struct ToggleRow: View {
    let title: String
    var subtitle: String? = nil
    @Binding var isOn: Bool

    var body: some View {
        Toggle(isOn: $isOn) {
            VStack(alignment: .leading, spacing: 2) {
                Text(title).font(Typo.rounded(.body, .semibold)).foregroundStyle(Palette.text)
                if let subtitle { Text(subtitle).font(.caption).foregroundStyle(Palette.text3) }
            }
        }
        .tint(Palette.indigo)
        .frame(minHeight: 44)
    }
}

/// Animated pill segmented control.
struct PillPicker: View {
    let options: [PickOption]
    @Binding var selection: String
    var scrollable = false

    @Namespace private var ns

    var body: some View {
        if scrollable {
            ScrollView(.horizontal, showsIndicators: false) { pills.padding(.horizontal, 2) }
        } else {
            pills
                .padding(4)
                .background(Capsule().fill(Palette.surface))
                .overlay(Capsule().strokeBorder(Palette.stroke, lineWidth: 1))
        }
    }

    private var pills: some View {
        HStack(spacing: scrollable ? 8 : 0) {
            ForEach(options) { option in
                let active = option.id == selection
                Button {
                    Haptics.select()
                    withAnimation(.spring(response: 0.35, dampingFraction: 0.8)) { selection = option.id }
                } label: {
                    Text(option.title)
                        .font(Typo.rounded(.subheadline, .semibold))
                        .foregroundStyle(active ? Color.white : Palette.text2)
                        .lineLimit(1)
                        .padding(.horizontal, 14)
                        .frame(maxWidth: scrollable ? nil : .infinity, minHeight: 38)
                        .background {
                            if active {
                                Capsule().fill(Theme.gradient)
                                    .matchedGeometryEffect(id: "pill", in: ns)
                                    .shadow(color: Palette.indigo.opacity(0.4), radius: 8, y: 3)
                            } else if scrollable {
                                Capsule().fill(Palette.surface)
                                    .overlay(Capsule().strokeBorder(Palette.stroke, lineWidth: 1))
                            }
                        }
                        .contentShape(Capsule())
                }
                .buttonStyle(.plain)
                .accessibilityAddTraits(active ? .isSelected : [])
            }
        }
    }
}

/// Quantity control with large targets.
struct QuantityStepper: View {
    @Binding var value: Int
    var range: ClosedRange<Int> = 1...20

    var body: some View {
        HStack(spacing: 0) {
            step("minus", enabled: value > range.lowerBound) { value -= 1 }
            Text("\(value)")
                .font(Typo.rounded(.title3, .bold))
                .foregroundStyle(Palette.text)
                .frame(minWidth: 44)
                .contentTransition(.numericText())
            step("plus", enabled: value < range.upperBound) { value += 1 }
        }
        .background(Capsule().fill(Palette.surface))
        .overlay(Capsule().strokeBorder(Palette.stroke, lineWidth: 1))
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("Quantity")
        .accessibilityValue("\(value)")
        .accessibilityAdjustableAction { dir in
            switch dir {
            case .increment: if value < range.upperBound { value += 1 }
            case .decrement: if value > range.lowerBound { value -= 1 }
            @unknown default: break
            }
        }
    }

    private func step(_ icon: String, enabled: Bool, action: @escaping () -> Void) -> some View {
        Button {
            Haptics.select()
            withAnimation(.spring(response: 0.3)) { action() }
        } label: {
            Image(systemName: icon)
                .font(.system(size: 15, weight: .bold))
                .foregroundStyle(enabled ? Palette.text : Palette.text3)
                .frame(width: 44, height: 44)
        }
        .buttonStyle(.plain)
        .disabled(!enabled)
    }
}

// MARK: - Search picker

struct PickOption: Identifiable, Hashable {
    let id: String
    let title: String
    var subtitle: String? = nil
    var leading: String? = nil
}

/// A field-looking row that opens a searchable list.
struct SelectRow: View {
    let label: String
    let options: [PickOption]
    @Binding var selection: String
    var placeholder = "Choose"
    var searchable = true

    @State private var open = false

    private var current: PickOption? { options.first { $0.id == selection } }

    var body: some View {
        VStack(alignment: .leading, spacing: 7) {
            Text(label).font(.footnote.weight(.semibold)).foregroundStyle(Palette.text2)
            Button {
                Haptics.tap()
                open = true
            } label: {
                HStack(spacing: 10) {
                    if let leading = current?.leading { Text(leading).font(.title3) }
                    Text(current?.title ?? placeholder)
                        .foregroundStyle(current == nil ? Palette.text3 : Palette.text)
                        .lineLimit(1)
                    Spacer()
                    Image(systemName: "chevron.up.chevron.down").font(.footnote.weight(.semibold)).foregroundStyle(Palette.text3)
                }
                .padding(.horizontal, 14)
                .frame(minHeight: 50)
                .background(RoundedRectangle(cornerRadius: 14, style: .continuous).fill(Color.white.opacity(0.06)))
                .overlay(RoundedRectangle(cornerRadius: 14, style: .continuous).strokeBorder(Palette.stroke, lineWidth: 1))
                .contentShape(Rectangle())
            }
            .buttonStyle(.pressable)
            .accessibilityLabel(label)
            .accessibilityValue(current?.title ?? "None")
        }
        .sheet(isPresented: $open) {
            SearchPickerSheet(title: label, options: options, selection: $selection, searchable: searchable)
        }
    }
}

struct SearchPickerSheet: View {
    let title: String
    let options: [PickOption]
    @Binding var selection: String
    var searchable = true

    @Environment(\.dismiss) private var dismiss
    @State private var query = ""

    private var filtered: [PickOption] {
        let q = query.trimmingCharacters(in: .whitespaces)
        guard !q.isEmpty else { return options }
        return options.filter { $0.title.localizedCaseInsensitiveContains(q) || $0.id.localizedCaseInsensitiveContains(q) || ($0.subtitle?.localizedCaseInsensitiveContains(q) ?? false) }
    }

    var body: some View {
        NavigationStack {
            ScrollView {
                LazyVStack(spacing: 6) {
                    ForEach(filtered) { option in
                        Button {
                            Haptics.select()
                            selection = option.id
                            dismiss()
                        } label: {
                            HStack(spacing: 12) {
                                if let leading = option.leading { Text(leading).font(.title2) }
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(option.title).foregroundStyle(Palette.text)
                                    if let s = option.subtitle { Text(s).font(.caption).foregroundStyle(Palette.text3) }
                                }
                                Spacer()
                                if option.id == selection {
                                    Image(systemName: "checkmark.circle.fill").foregroundStyle(Palette.accentText)
                                }
                            }
                            .padding(.horizontal, 14)
                            .frame(minHeight: 52)
                            .background(
                                RoundedRectangle(cornerRadius: 14, style: .continuous)
                                    .fill(option.id == selection ? Palette.indigo.opacity(0.18) : Palette.surface)
                            )
                            .contentShape(Rectangle())
                        }
                        .buttonStyle(.pressable)
                    }
                    if filtered.isEmpty {
                        Text("No matches").foregroundStyle(Palette.text3).padding(.top, 40)
                    }
                }
                .padding(16)
            }
            .screenBackground()
            .navigationTitle(title)
            .navigationBarTitleDisplayMode(.inline)
            .modifier(OptionalSearch(enabled: searchable, query: $query))
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Close") { dismiss() } }
            }
        }
        .preferredColorScheme(.dark)
    }
}

private struct OptionalSearch: ViewModifier {
    let enabled: Bool
    @Binding var query: String

    func body(content: Content) -> some View {
        if enabled {
            content.searchable(text: $query, placement: .navigationBarDrawer(displayMode: .always))
        } else {
            content
        }
    }
}

/// Lets a customer pick one of their own ready devices.
struct DevicePicker: View {
    let devices: [DeviceRef]
    @Binding var padCode: String

    var body: some View {
        SelectRow(label: "Cloud phone",
                  options: devices.map { PickOption(id: $0.padCode, title: $0.name, subtitle: $0.padCode, leading: "📱") },
                  selection: $padCode,
                  placeholder: "Choose a phone",
                  searchable: devices.count > 6)
    }
}

// MARK: - Status

struct StatusPill: View {
    let text: String
    var color: Color = Palette.text2
    var pulse = false

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var on = false

    var body: some View {
        HStack(spacing: 6) {
            Circle().fill(color).frame(width: 7, height: 7)
                .overlay(
                    Circle().stroke(color, lineWidth: 2)
                        .scaleEffect(on ? 2.4 : 1)
                        .opacity(on ? 0 : 0.7)
                        .opacity(pulse ? 1 : 0)
                )
            Text(text).font(Typo.rounded(.caption, .semibold)).foregroundStyle(color)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 5)
        .background(Capsule().fill(color.opacity(0.14)))
        .onAppear {
            guard pulse, !reduceMotion else { return }
            withAnimation(.easeOut(duration: 1.6).repeatForever(autoreverses: false)) { on = true }
        }
        .accessibilityElement(children: .combine)
    }

    static func color(for raw: String?) -> Color {
        let s = (raw ?? "").lowercased()
        if ["completed", "confirmed", "active", "running", "online", "attached", "finished", "success", "bound", "paid", "ready", "live"].contains(where: { s.contains($0) }) {
            return Palette.success
        }
        if ["fail", "expired", "canceled", "cancelled", "error", "released", "stopped", "offline"].contains(where: { s.contains($0) }) {
            return Palette.danger
        }
        return s.isEmpty ? Palette.text2 : Palette.warning
    }

    static func forStatus(_ raw: String?) -> StatusPill {
        let c = color(for: raw)
        return StatusPill(text: Format.status(raw), color: c)
    }
}

// MARK: - Loading / empty

struct Shimmer: ViewModifier {
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var phase: CGFloat = -1

    func body(content: Content) -> some View {
        content
            .overlay(
                GeometryReader { geo in
                    LinearGradient(colors: [.clear, Color.white.opacity(0.10), .clear], startPoint: .leading, endPoint: .trailing)
                        .frame(width: geo.size.width * 0.6)
                        .offset(x: phase * geo.size.width * 1.4)
                }
                .mask(content)
            )
            .onAppear {
                guard !reduceMotion else { return }
                withAnimation(.linear(duration: 1.3).repeatForever(autoreverses: false)) { phase = 1.2 }
            }
    }
}

struct SkeletonList: View {
    var count = 4

    var body: some View {
        VStack(spacing: 14) {
            ForEach(0..<count, id: \.self) { _ in
                HStack(spacing: 14) {
                    RoundedRectangle(cornerRadius: 12).fill(Palette.surfaceStrong).frame(width: 52, height: 52)
                    VStack(alignment: .leading, spacing: 8) {
                        RoundedRectangle(cornerRadius: 5).fill(Palette.surfaceStrong).frame(width: 150, height: 14)
                        RoundedRectangle(cornerRadius: 5).fill(Palette.surface).frame(width: 100, height: 11)
                    }
                    Spacer()
                }
                .glassCard()
                .modifier(Shimmer())
            }
        }
        .padding(16)
        .accessibilityLabel("Loading")
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
            ScrollView {
                EmptyStateView(icon: "wifi.exclamationmark", title: "Can't load this right now", message: error,
                               actionTitle: "Try again", colors: TileColors.orange) { Task { await retry() } }
                    .padding(.top, 60)
            }
            .screenBackground()
        } else if isLoading {
            ScrollView { SkeletonList() }.screenBackground()
        } else {
            content()
        }
    }
}

struct EmptyStateView: View {
    let icon: String
    let title: String
    var message: String? = nil
    var actionTitle: String? = nil
    var colors: (Color, Color) = TileColors.indigo
    var art: String? = nil
    var action: (() -> Void)? = nil

    var body: some View {
        VStack(spacing: 16) {
            if let art {
                ArtImage(name: art, size: 170)
                    .padding(.vertical, -18)
            } else {
                ZStack {
                    Circle().fill(Theme.tile(colors.0, colors.1)).frame(width: 110, height: 110).blur(radius: 30).opacity(0.45)
                    IconTile(symbol: icon, colors: colors, size: 76)
                }
                .padding(.bottom, 4)
            }
            Text(title).font(Typo.rounded(.title3, .bold)).foregroundStyle(Palette.text).multilineTextAlignment(.center)
            if let message {
                Text(message).font(.subheadline).foregroundStyle(Palette.text2).multilineTextAlignment(.center)
            }
            if let actionTitle, let action {
                Button(actionTitle) {
                    Haptics.tap()
                    action()
                }
                .buttonStyle(.primary)
                .frame(maxWidth: 260)
                .padding(.top, 6)
            }
        }
        .padding(28)
        .frame(maxWidth: .infinity)
    }
}

// MARK: - Async button

/// Button that shows a spinner while its action runs.
struct AsyncButton<Label: View>: View {
    var role: ButtonRole? = nil
    let action: () async -> Void
    @ViewBuilder let label: () -> Label

    @State private var running = false

    var body: some View {
        Button(role: role) {
            guard !running else { return }
            Haptics.tap()
            running = true
            Task {
                await action()
                running = false
            }
        } label: {
            HStack(spacing: 8) {
                if running { ProgressView().tint(.white).controlSize(.small) }
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

// MARK: - QR + payment

struct QRCodeView: View {
    let text: String

    var body: some View {
        if let image = Self.make(text) {
            Image(uiImage: image)
                .interpolation(.none)
                .resizable()
                .scaledToFit()
                .padding(14)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
                .shadow(color: Palette.indigo.opacity(0.35), radius: 24, y: 10)
                .accessibilityLabel("QR code of the payment address")
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

/// Live "time left" label.
struct CountdownText: View {
    let iso: String?

    var body: some View {
        if let end = Format.date(iso) {
            TimelineView(.periodic(from: .now, by: 1)) { context in
                let left = max(0, Int(end.timeIntervalSince(context.date)))
                Text(left == 0 ? "Expired" : String(format: "%d:%02d left", left / 60, left % 60))
                    .font(Typo.rounded(.subheadline, .semibold))
                    .monospacedDigit()
                    .foregroundStyle(left < 300 ? Palette.warning : Palette.text2)
            }
        }
    }
}

/// Where to send a crypto payment, with QR and the tx-hash box.
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
        VStack(alignment: .leading, spacing: 16) {
            HStack {
                StatusPill.forStatus(status)
                Spacer()
                if status == "awaiting_payment" { CountdownText(iso: expiresAt) }
            }

            if let address = address?.nonEmpty {
                HStack {
                    Spacer()
                    QRCodeView(text: address).frame(width: 210, height: 210)
                    Spacer()
                }
                .padding(.vertical, 4)

                VStack(alignment: .leading, spacing: 4) {
                    Text("Send exactly").font(.caption).foregroundStyle(Palette.text3)
                    HStack(alignment: .firstTextBaseline, spacing: 6) {
                        Text(Format.crypto(amountCrypto)).font(Typo.rounded(.title, .bold)).foregroundStyle(Palette.text)
                        Text(currency ?? "").font(Typo.rounded(.headline, .semibold)).foregroundStyle(Palette.accentText)
                    }
                    if let amountUsd {
                        Text("≈ \(Format.money(amountUsd)) on \(network ?? "")").font(.footnote).foregroundStyle(Palette.text2)
                    }
                }

                CardDivider()
                CopyRow(label: "Amount", value: Format.crypto(amountCrypto), monospaced: true)
                CopyRow(label: "\(network ?? "") address", value: address, monospaced: true)
            }

            if let txHash = txHash?.nonEmpty {
                CardDivider()
                CopyRow(label: "Transaction hash", value: txHash, monospaced: true)
            } else if status == "awaiting_payment" {
                CardDivider()
                GlassField(label: "Transaction hash (optional)", text: $hash, prompt: "Paste after you send", monospaced: true)
                AsyncButton("Submit hash") {
                    await submit(hash.trimmingCharacters(in: .whitespacesAndNewlines))
                }
                .buttonStyle(.glass(Palette.accentText))
                .disabled(hash.trimmingCharacters(in: .whitespaces).count < 10)
            }

            NoteView(text: "Send the exact amount on the \(network ?? "shown") network only. Payments are detected automatically within a few minutes — pasting the hash makes it faster.",
                     icon: "lock.shield")
        }
        .glassCard(padding: 18, highlight: true)
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

/// Standard sheet chrome: dark, background, Close button.
struct SheetScaffold<Content: View>: View {
    let title: String
    var backdrop: Backdrop = .violet
    @ViewBuilder let content: () -> Content
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            Screen(backdrop: backdrop) { content() }
                .navigationTitle(title)
                .navigationBarTitleDisplayMode(.inline)
                .toolbar {
                    ToolbarItem(placement: .cancellationAction) { Button("Close") { dismiss() } }
                }
        }
        .preferredColorScheme(.dark)
        .presentationDragIndicator(.visible)
    }
}
