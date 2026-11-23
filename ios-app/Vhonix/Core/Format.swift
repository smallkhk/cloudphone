import Foundation

enum Format {
    static func money(_ value: Double?) -> String {
        guard let value else { return "—" }
        let f = NumberFormatter()
        f.numberStyle = .currency
        f.currencyCode = "USD"
        f.locale = Locale(identifier: "en_US")
        return f.string(from: NSNumber(value: value)) ?? String(format: "$%.2f", value)
    }

    static func crypto(_ value: Double?) -> String {
        guard let value else { return "—" }
        var s = String(format: "%.8f", value)
        while s.contains(".") && (s.hasSuffix("0") || s.hasSuffix(".")) { s.removeLast() }
        return s
    }

    static func date(_ iso: String?) -> Date? {
        guard let iso else { return nil }
        let f = ISO8601DateFormatter()
        if let d = f.date(from: iso) { return d }
        f.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        if let d = f.date(from: iso) { return d }

        let plain = DateFormatter()
        plain.locale = Locale(identifier: "en_US_POSIX")
        plain.dateFormat = "yyyy-MM-dd HH:mm:ss"
        return plain.date(from: iso)
    }

    static func when(_ iso: String?) -> String {
        guard let iso else { return "—" }
        guard let d = date(iso) else { return iso }
        return d.formatted(date: .abbreviated, time: .shortened)
    }

    static func relative(_ iso: String?) -> String {
        guard let d = date(iso) else { return iso ?? "—" }
        return RelativeDateTimeFormatter().localizedString(for: d, relativeTo: Date())
    }

    static func bytes(_ value: Int64) -> String {
        ByteCountFormatter.string(fromByteCount: value, countStyle: .file)
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
