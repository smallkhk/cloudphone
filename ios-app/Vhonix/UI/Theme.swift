import SwiftUI

enum Theme {
    static let accent = Color(red: 0.31, green: 0.36, blue: 0.84)
    static let accent2 = Color(red: 0.55, green: 0.32, blue: 0.86)
    static let gradient = LinearGradient(colors: [accent, accent2], startPoint: .topLeading, endPoint: .bottomTrailing)
}

struct PrimaryButtonStyle: ButtonStyle {
    @Environment(\.isEnabled) private var isEnabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .frame(maxWidth: .infinity)
            .padding(.vertical, 14)
            .background(Theme.gradient.opacity(isEnabled ? 1 : 0.4))
            .foregroundStyle(.white)
            .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            .opacity(configuration.isPressed ? 0.8 : 1)
    }
}

extension ButtonStyle where Self == PrimaryButtonStyle {
    static var primary: PrimaryButtonStyle { PrimaryButtonStyle() }
}

struct StatusPill: View {
    let text: String
    var color: Color = .secondary

    var body: some View {
        Text(text)
            .font(.caption.weight(.semibold))
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(color.opacity(0.15))
            .foregroundStyle(color)
            .clipShape(Capsule())
    }

    static func forStatus(_ raw: String?) -> StatusPill {
        let s = (raw ?? "").lowercased()
        let color: Color
        if ["completed", "confirmed", "active", "running", "online", "attached", "finished", "success", "bound", "paid"].contains(where: { s.contains($0) }) {
            color = .green
        } else if ["fail", "expired", "canceled", "cancelled", "error", "released", "stopped"].contains(where: { s.contains($0) }) {
            color = .red
        } else if s.isEmpty {
            color = .secondary
        } else {
            color = .orange
        }
        return StatusPill(text: Format.status(raw), color: color)
    }
}
