import SwiftUI
import UIKit

// MARK: - Tokens
//
// Dark-first palette. Text colours were checked against the card surface
// (#16171E) with the HIG contrast tool: primary 18+:1, secondary 7.7:1,
// tertiary 5.3:1 — never use white below 0.52 opacity for text.

extension Color {
    init(hex: UInt32, opacity: Double = 1) {
        self.init(.sRGB,
                  red: Double((hex >> 16) & 0xFF) / 255,
                  green: Double((hex >> 8) & 0xFF) / 255,
                  blue: Double(hex & 0xFF) / 255,
                  opacity: opacity)
    }
}

enum Palette {
    static let bg = Color(hex: 0x07080F)
    static let bgRaised = Color(hex: 0x10121C)
    static let surface = Color.white.opacity(0.055)
    static let surfaceStrong = Color.white.opacity(0.10)
    static let stroke = Color.white.opacity(0.09)

    static let text = Color.white
    static let text2 = Color.white.opacity(0.64)
    static let text3 = Color.white.opacity(0.52)

    static let indigo = Color(hex: 0x7B6CFF)
    static let violet = Color(hex: 0xB26BFF)
    static let cyan = Color(hex: 0x3ED3F7)
    static let pink = Color(hex: 0xFF6BB5)
    static let accentText = Color(hex: 0x9D90FF)

    static let success = Color(hex: 0x34D399)
    static let warning = Color(hex: 0xFBBF24)
    static let danger = Color(hex: 0xF87171)
}

enum Theme {
    static let accent = Palette.indigo
    /// Button fill — white text on it passes 5:1.
    static let gradient = LinearGradient(colors: [Color(hex: 0x5B4BE8), Color(hex: 0x8A45E6)],
                                         startPoint: .topLeading, endPoint: .bottomTrailing)
    static let brand = LinearGradient(colors: [Palette.indigo, Palette.violet, Palette.cyan],
                                      startPoint: .topLeading, endPoint: .bottomTrailing)
    static let brandText = LinearGradient(colors: [Color(hex: 0xB9B0FF), Color(hex: 0x8FE6FF)],
                                          startPoint: .leading, endPoint: .trailing)

    static func tile(_ a: Color, _ b: Color) -> LinearGradient {
        LinearGradient(colors: [a, b], startPoint: .topLeading, endPoint: .bottomTrailing)
    }
}

enum Typo {
    static func rounded(_ style: Font.TextStyle, _ weight: Font.Weight = .regular) -> Font {
        .system(style, design: .rounded, weight: weight)
    }
}

@MainActor
enum Haptics {
    static func tap() { UIImpactFeedbackGenerator(style: .light).impactOccurred() }
    static func medium() { UIImpactFeedbackGenerator(style: .medium).impactOccurred() }
    static func select() { UISelectionFeedbackGenerator().selectionChanged() }
    static func success() { UINotificationFeedbackGenerator().notificationOccurred(.success) }
    static func error() { UINotificationFeedbackGenerator().notificationOccurred(.error) }
}

@MainActor
enum Appearance {
    /// Native large titles in the app's rounded type, over the custom background.
    static func apply() {
        func rounded(_ size: CGFloat, _ weight: UIFont.Weight, _ style: UIFont.TextStyle) -> UIFont {
            let base = UIFont.systemFont(ofSize: size, weight: weight)
            let font = base.fontDescriptor.withDesign(.rounded).map { UIFont(descriptor: $0, size: size) } ?? base
            return UIFontMetrics(forTextStyle: style).scaledFont(for: font)
        }

        let large: [NSAttributedString.Key: Any] = [.font: rounded(34, .bold, .largeTitle), .foregroundColor: UIColor.white]
        let inline: [NSAttributedString.Key: Any] = [.font: rounded(17, .semibold, .headline), .foregroundColor: UIColor.white]

        let edge = UINavigationBarAppearance()
        edge.configureWithTransparentBackground()
        edge.largeTitleTextAttributes = large
        edge.titleTextAttributes = inline

        let scrolled = UINavigationBarAppearance()
        scrolled.configureWithDefaultBackground()
        scrolled.backgroundEffect = UIBlurEffect(style: .systemUltraThinMaterialDark)
        scrolled.backgroundColor = UIColor(red: 0.03, green: 0.03, blue: 0.06, alpha: 0.55)
        scrolled.shadowColor = UIColor.white.withAlphaComponent(0.06)
        scrolled.largeTitleTextAttributes = large
        scrolled.titleTextAttributes = inline

        let bar = UINavigationBar.appearance()
        bar.standardAppearance = scrolled
        bar.compactAppearance = scrolled
        bar.scrollEdgeAppearance = edge
        bar.tintColor = UIColor(red: 0.62, green: 0.56, blue: 1, alpha: 1)

        UITextField.appearance().tintColor = UIColor(red: 0.62, green: 0.56, blue: 1, alpha: 1)
    }
}

// MARK: - Background

/// Deep background with slow-drifting colour light. Stays still with Reduce Motion.
struct AppBackground: View {
    var animated = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var drift = false

    var body: some View {
        ZStack {
            Palette.bg
            Circle().fill(Palette.indigo.opacity(0.32))
                .frame(width: 440, height: 440).blur(radius: 120)
                .offset(x: drift ? -150 : -70, y: drift ? -330 : -270)
            Circle().fill(Palette.violet.opacity(0.20))
                .frame(width: 380, height: 380).blur(radius: 130)
                .offset(x: drift ? 170 : 120, y: drift ? -60 : -150)
            Circle().fill(Palette.cyan.opacity(0.10))
                .frame(width: 380, height: 380).blur(radius: 140)
                .offset(x: drift ? -30 : 60, y: drift ? 440 : 360)
        }
        .drawingGroup()
        .ignoresSafeArea()
        .accessibilityHidden(true)
        .onAppear {
            guard animated, !reduceMotion else { return }
            withAnimation(.easeInOut(duration: 16).repeatForever(autoreverses: true)) { drift = true }
        }
    }
}

// MARK: - Glass card

struct GlassCard: ViewModifier {
    var padding: CGFloat = 16
    var radius: CGFloat = 22
    var highlight = false

    func body(content: Content) -> some View {
        content
            .padding(padding)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(
                RoundedRectangle(cornerRadius: radius, style: .continuous)
                    .fill(.ultraThinMaterial)
                    .overlay(RoundedRectangle(cornerRadius: radius, style: .continuous).fill(Palette.surface))
            )
            .overlay(
                RoundedRectangle(cornerRadius: radius, style: .continuous)
                    .strokeBorder(
                        LinearGradient(colors: [Color.white.opacity(highlight ? 0.30 : 0.14), Color.white.opacity(0.03)],
                                       startPoint: .top, endPoint: .bottom),
                        lineWidth: 1)
            )
    }
}

extension View {
    func glassCard(padding: CGFloat = 16, radius: CGFloat = 22, highlight: Bool = false) -> some View {
        modifier(GlassCard(padding: padding, radius: radius, highlight: highlight))
    }

    /// Standard screen chrome: background, hidden indicators, keyboard dismissal.
    func screenBackground(animated: Bool = false) -> some View {
        self
            .scrollIndicators(.hidden)
            .scrollDismissesKeyboard(.interactively)
            .background(AppBackground(animated: animated))
    }
}

// MARK: - Buttons

struct PrimaryButtonStyle: ButtonStyle {
    @Environment(\.isEnabled) private var isEnabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(Typo.rounded(.headline, .semibold))
            .foregroundStyle(.white)
            .frame(maxWidth: .infinity, minHeight: 54)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(Theme.gradient)
                    .overlay(
                        RoundedRectangle(cornerRadius: 16, style: .continuous)
                            .strokeBorder(Color.white.opacity(0.22), lineWidth: 1)
                    )
                    .shadow(color: Palette.indigo.opacity(isEnabled ? 0.45 : 0), radius: 18, y: 8)
            )
            .opacity(isEnabled ? 1 : 0.45)
            .scaleEffect(configuration.isPressed ? 0.97 : 1)
            .animation(.spring(response: 0.3, dampingFraction: 0.7), value: configuration.isPressed)
    }
}

struct GlassButtonStyle: ButtonStyle {
    var tint: Color = .white
    @Environment(\.isEnabled) private var isEnabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(Typo.rounded(.headline, .semibold))
            .foregroundStyle(tint)
            .frame(maxWidth: .infinity, minHeight: 50)
            .background(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .fill(Palette.surfaceStrong)
                    .overlay(RoundedRectangle(cornerRadius: 16, style: .continuous).strokeBorder(Palette.stroke, lineWidth: 1))
            )
            .opacity(isEnabled ? 1 : 0.45)
            .scaleEffect(configuration.isPressed ? 0.97 : 1)
            .animation(.spring(response: 0.3, dampingFraction: 0.7), value: configuration.isPressed)
    }
}

/// Small capsule action. Visual height 36pt inside a 44pt hit area.
struct ChipButtonStyle: ButtonStyle {
    var tint: Color = Palette.text
    var filled = false
    @Environment(\.isEnabled) private var isEnabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(Typo.rounded(.subheadline, .semibold))
            .foregroundStyle(filled ? Color.white : tint)
            .padding(.horizontal, 14)
            .frame(minHeight: 36)
            .background(
                Capsule().fill(filled ? AnyShapeStyle(Theme.gradient) : AnyShapeStyle(tint.opacity(0.13)))
            )
            .padding(.vertical, 4)
            .contentShape(Rectangle())
            .opacity(isEnabled ? 1 : 0.4)
            .scaleEffect(configuration.isPressed ? 0.95 : 1)
            .animation(.spring(response: 0.25, dampingFraction: 0.7), value: configuration.isPressed)
    }
}

/// Whole-card tap target with a gentle press.
struct PressableStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .scaleEffect(configuration.isPressed ? 0.98 : 1)
            .opacity(configuration.isPressed ? 0.9 : 1)
            .animation(.spring(response: 0.3, dampingFraction: 0.75), value: configuration.isPressed)
    }
}

extension ButtonStyle where Self == PrimaryButtonStyle {
    static var primary: PrimaryButtonStyle { PrimaryButtonStyle() }
}

extension ButtonStyle where Self == GlassButtonStyle {
    static var glass: GlassButtonStyle { GlassButtonStyle() }
    static func glass(_ tint: Color) -> GlassButtonStyle { GlassButtonStyle(tint: tint) }
}

extension ButtonStyle where Self == ChipButtonStyle {
    static var chip: ChipButtonStyle { ChipButtonStyle() }
    static func chip(_ tint: Color, filled: Bool = false) -> ChipButtonStyle { ChipButtonStyle(tint: tint, filled: filled) }
}

extension ButtonStyle where Self == PressableStyle {
    static var pressable: PressableStyle { PressableStyle() }
}

// MARK: - Brand

struct BrandMark: View {
    var size: CGFloat = 88
    var showName = true
    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @State private var spin = false

    var body: some View {
        VStack(spacing: 14) {
            ZStack {
                Circle()
                    .fill(AngularGradient(colors: [Palette.indigo, Palette.violet, Palette.cyan, Palette.indigo], center: .center))
                    .frame(width: size * 1.25, height: size * 1.25)
                    .blur(radius: 26)
                    .opacity(0.55)
                    .rotationEffect(.degrees(spin ? 360 : 0))

                RoundedRectangle(cornerRadius: size * 0.28, style: .continuous)
                    .fill(Theme.brand)
                    .frame(width: size, height: size)
                    .overlay(
                        RoundedRectangle(cornerRadius: size * 0.28, style: .continuous)
                            .strokeBorder(Color.white.opacity(0.35), lineWidth: 1)
                    )
                    .overlay(
                        Text("V")
                            .font(.system(size: size * 0.52, weight: .heavy, design: .rounded))
                            .foregroundStyle(.white)
                            .shadow(color: .black.opacity(0.25), radius: 6, y: 3)
                    )
                    .shadow(color: Palette.violet.opacity(0.5), radius: 24, y: 10)
            }
            if showName {
                Text(Config.appName)
                    .font(Typo.rounded(.largeTitle, .bold))
                    .foregroundStyle(Palette.text)
            }
        }
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(Config.appName)
        .onAppear {
            guard !reduceMotion else { return }
            withAnimation(.linear(duration: 18).repeatForever(autoreverses: false)) { spin = true }
        }
    }
}

/// Rounded gradient square with a symbol — the app's icon language.
struct IconTile: View {
    let symbol: String
    var colors: (Color, Color) = (Palette.indigo, Palette.violet)
    var size: CGFloat = 40

    var body: some View {
        RoundedRectangle(cornerRadius: size * 0.3, style: .continuous)
            .fill(Theme.tile(colors.0, colors.1))
            .frame(width: size, height: size)
            .overlay(
                Image(systemName: symbol)
                    .font(.system(size: size * 0.44, weight: .semibold))
                    .foregroundStyle(.white)
            )
            .overlay(
                RoundedRectangle(cornerRadius: size * 0.3, style: .continuous)
                    .strokeBorder(Color.white.opacity(0.22), lineWidth: 1)
            )
            .shadow(color: colors.0.opacity(0.35), radius: 8, y: 4)
            .accessibilityHidden(true)
    }
}

enum TileColors {
    static let indigo = (Palette.indigo, Palette.violet)
    static let cyan = (Color(hex: 0x2FB6E8), Color(hex: 0x5B7CFA))
    static let green = (Color(hex: 0x22C58B), Color(hex: 0x1FA3A0))
    static let orange = (Color(hex: 0xFF9F43), Color(hex: 0xFF5E7E))
    static let pink = (Color(hex: 0xFF6BB5), Color(hex: 0xA66BFF))
    static let red = (Color(hex: 0xFF6B6B), Color(hex: 0xE0457B))
    static let slate = (Color(hex: 0x6B7489), Color(hex: 0x3F465A))
    static let gold = (Color(hex: 0xF7C948), Color(hex: 0xF08C35))
}
