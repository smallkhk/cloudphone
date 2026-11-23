import Foundation

enum Format {
    // Formatters are expensive to create; build each one once.
    private static let currency: NumberFormatter = {
        let f = NumberFormatter()
        f.numberStyle = .currency
        f.currencyCode = "USD"
        f.locale = Locale(identifier: "en_US")
        return f
    }()
    private static let iso = ISO8601DateFormatter()
    private static let isoFractional: ISO8601DateFormatter = {
        let f = ISO8601DateFormatter()
        f.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return f
    }()
    private static let plain: DateFormatter = {
        let f = DateFormatter()
        f.locale = Locale(identifier: "en_US_POSIX")
        f.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return f
    }()
    private static let relativeFormatter = RelativeDateTimeFormatter()
    private static let bytesFormatter: ByteCountFormatter = {
        let f = ByteCountFormatter()
        f.countStyle = .file
        return f
    }()

    static func money(_ value: Double?) -> String {
        guard let value else { return "—" }
        return currency.string(from: NSNumber(value: value)) ?? String(format: "$%.2f", value)
    }

    static func crypto(_ value: Double?) -> String {
        guard let value else { return "—" }
        var s = String(format: "%.8f", value)
        while s.contains(".") && (s.hasSuffix("0") || s.hasSuffix(".")) { s.removeLast() }
        return s
    }

    static func date(_ iso: String?) -> Date? {
        guard let value = iso else { return nil }
        if let d = Self.iso.date(from: value) { return d }
        if let d = isoFractional.date(from: value) { return d }
        return plain.date(from: value)
    }

    static func when(_ iso: String?) -> String {
        guard let iso else { return "—" }
        guard let d = date(iso) else { return iso }
        return d.formatted(date: .abbreviated, time: .shortened)
    }

    static func relative(_ iso: String?) -> String {
        guard let d = date(iso) else { return iso ?? "—" }
        return relativeFormatter.localizedString(for: d, relativeTo: Date())
    }

    static func bytes(_ value: Int64) -> String {
        bytesFormatter.string(fromByteCount: value)
    }

    /// "awaiting_payment" -> "Awaiting payment"
    static func status(_ raw: String?) -> String {
        guard let raw, !raw.isEmpty else { return "—" }
        let s = raw.replacingOccurrences(of: "_", with: " ").replacingOccurrences(of: "-", with: " ")
        return s.prefix(1).uppercased() + s.dropFirst()
    }

    static func flag(_ code: String?) -> String {
        guard let code, code.count == 2 else { return "🌐" }
        return code.uppercased().unicodeScalars.compactMap { UnicodeScalar(127397 + $0.value) }.map { String($0) }.joined()
    }
}
