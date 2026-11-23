import Foundation

struct APIError: LocalizedError {
    let message: String
    var status: Int = 0

    var errorDescription: String? { message }
}

/// What every "do something" endpoint returns (restart, buy, bind...).
struct ActionResult: Decodable {
    let ok: Bool
    let message: String?
    let data: [String: JSONValue]?
    let location: String?

    /// The id at the end of the page the website would have gone to next (e.g. a new order).
    var locationId: Int? {
        guard let location, let last = URL(string: location)?.lastPathComponent else { return nil }
        return Int(last)
    }
}

private struct ErrorBody: Decodable {
    let message: String?
    let errors: [String: [String]]?
}

@MainActor
final class APIClient {
    static let shared = APIClient()

    var token: String? {
        didSet { Keychain.set(token, for: "token") }
    }

    /// Called when the server says the sign-in is no longer valid.
    var onUnauthorized: (@MainActor () -> Void)?

    private let session: URLSession
    private let decoder: JSONDecoder

    private init() {
        let config = URLSessionConfiguration.default
        config.timeoutIntervalForRequest = 30
        config.waitsForConnectivity = false
        config.requestCachePolicy = .reloadIgnoringLocalCacheData
        config.httpCookieStorage = nil
        config.httpShouldSetCookies = false
        session = URLSession(configuration: config)

        decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        token = Keychain.get("token")
    }

    func get<T: Decodable>(_ path: String, as type: T.Type = T.self) async throws -> T {
        try await request("GET", path, body: nil)
    }

    /// The last good response for a GET, straight from the phone — no network.
    func cached<T: Decodable>(_ path: String, as type: T.Type = T.self) -> T? {
        guard let data = DataCache.load(path) else { return nil }
        return try? decoder.decode(T.self, from: data)
    }

    func send<T: Decodable>(_ method: String, _ path: String, body: [String: Any]? = nil, as type: T.Type = T.self) async throws -> T {
        try await request(method, path, body: body)
    }

    func action(_ path: String, method: String = "POST", body: [String: Any] = [:]) async throws -> ActionResult {
        try await request(method, path, body: body)
    }

    private func perform(_ req: URLRequest) async throws -> (Data, URLResponse) {
        do {
            return try await session.data(for: req)
        } catch {
            throw APIError(message: "Couldn't reach the server. Check your internet connection and try again.")
        }
    }

    private func request<T: Decodable>(_ method: String, _ path: String, body: [String: Any]?) async throws -> T {
        guard let url = URL(string: Config.apiBase + path) else {
            throw APIError(message: "Bad address.")
        }

        var req = URLRequest(url: url)
        req.httpMethod = method
        req.setValue("application/json", forHTTPHeaderField: "Accept")

        if let token {
            req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        if let body {
            req.setValue("application/json", forHTTPHeaderField: "Content-Type")
            req.httpBody = try JSONSerialization.data(withJSONObject: body)
        }

        let (data, response) = try await perform(req)
        let status = (response as? HTTPURLResponse)?.statusCode ?? 0

        if status == 401, path.hasPrefix("auth/") == false {
            onUnauthorized?()
            throw APIError(message: "Please sign in again.", status: status)
        }

        guard (200..<300).contains(status) else {
            throw errorFrom(data: data, status: status)
        }

        do {
            let value = try decoder.decode(T.self, from: data)
            if method == "GET" { DataCache.store(data, for: path) }
            return value
        } catch {
            #if DEBUG
            print("Decode error for \(path):", error)
            #endif
            throw APIError(message: "The server sent something unexpected. Please update the app or try again.", status: status)
        }
    }

    private func errorFrom(data: Data, status: Int) -> APIError {
        let body = try? JSONDecoder().decode(ErrorBody.self, from: data)

        if let first = body?.errors?.values.compactMap(\.first).first {
            return APIError(message: first, status: status)
        }

        if let message = body?.message, !message.isEmpty, status < 500 {
            if status == 403, message == "This action is unauthorized." {
                return APIError(message: "That isn't yours to change.", status: status)
            }
            return APIError(message: message, status: status)
        }

        switch status {
        case 403: return APIError(message: "That isn't yours to change.", status: status)
        case 404: return APIError(message: "That item no longer exists.", status: status)
        case 429: return APIError(message: "Too many requests — wait a minute and try again.", status: status)
        default: return APIError(message: "Something went wrong on the server. Please try again.", status: status)
        }
    }
}
