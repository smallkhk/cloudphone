import Foundation
import Network
import WebKit

/// Lets the virtual camera use plain-http links on the local network
/// (http://192.168.x.x, http://localhost, http://name.local).
///
/// The live screen is an https page, and WebKit blocks plain-http video
/// inside it ("mixed content"). So the app runs a tiny web server on this
/// iPhone's loopback address (http://127.0.0.1:<port>, which WebKit trusts):
/// the page asks it for `/<host:port>/<path>` and it fetches
/// `http://<host:port>/<path>` with URLSession, streaming the answer back with
/// CORS headers. Only local-network hosts are relayed, and it only listens on
/// loopback, so nothing outside this iPhone can reach it.
final class LocalRelay: NSObject, URLSessionDataDelegate, @unchecked Sendable {
    static let shared = LocalRelay()

    private let queue = DispatchQueue(label: "vhonix.local-relay")
    private let lock = NSLock()
    private var listener: NWListener?
    private var boundPort: UInt16 = 0
    private var connections: [Int: NWConnection] = [:]   // URLSession task id → browser connection

    private lazy var session: URLSession = {
        let config = URLSessionConfiguration.ephemeral
        config.timeoutIntervalForRequest = 20
        config.requestCachePolicy = .reloadIgnoringLocalCacheData
        let ops = OperationQueue()
        ops.maxConcurrentOperationCount = 1
        return URLSession(configuration: config, delegate: self, delegateQueue: ops)
    }()

    /// "http://127.0.0.1:<port>" once running, or nil if it couldn't start.
    var base: String? {
        lock.lock(); defer { lock.unlock() }
        return boundPort > 0 && listener != nil ? "http://127.0.0.1:\(boundPort)" : nil
    }

    /// Starts the relay if it isn't running. Waits briefly (well under a second) for it to be ready.
    @discardableResult
    func start() -> String? {
        if let base { return base }
        for port in UInt16(48731)...UInt16(48740) {
            if listen(on: port) { return base }
        }
        return nil
    }

    private func listen(on port: UInt16) -> Bool {
        let params = NWParameters.tcp
        params.requiredInterfaceType = .loopback
        params.allowLocalEndpointReuse = true
        guard let nwPort = NWEndpoint.Port(rawValue: port),
              let l = try? NWListener(using: params, on: nwPort) else { return false }

        let ready = DispatchSemaphore(value: 0)
        let flag = ReadyFlag()
        l.stateUpdateHandler = { [weak self] state in
            switch state {
            case .ready:
                flag.set()
                ready.signal()
            case .failed, .cancelled:
                self?.lock.lock()
                if self?.listener === l { self?.listener = nil; self?.boundPort = 0 }
                self?.lock.unlock()
                ready.signal()
            default:
                break
            }
        }
        l.newConnectionHandler = { [weak self] conn in self?.accept(conn) }
        lock.lock(); listener = l; boundPort = port; lock.unlock()
        l.start(queue: queue)

        _ = ready.wait(timeout: .now() + 0.8)
        let ok = flag.isSet
        if !ok {
            l.cancel()
            lock.lock(); if listener === l { listener = nil; boundPort = 0 }; lock.unlock()
        }
        return ok
    }

    // MARK: Incoming requests from the page

    private func accept(_ conn: NWConnection) {
        conn.start(queue: queue)
        read(conn, buffer: Data())
    }

    private func read(_ conn: NWConnection, buffer: Data) {
        conn.receive(minimumIncompleteLength: 1, maximumLength: 64 * 1024) { [weak self] data, _, done, error in
            guard let self else { return }
            var buf = buffer
            if let data { buf.append(data) }
            if error != nil { conn.cancel(); return }

            guard let headerEnd = buf.range(of: Data("\r\n\r\n".utf8)) else {
                if done || buf.count > 64 * 1024 { conn.cancel() } else { self.read(conn, buffer: buf) }
                return
            }
            let head = String(decoding: buf[..<headerEnd.lowerBound], as: UTF8.self)
            var lines = head.components(separatedBy: "\r\n")
            let requestLine = lines.removeFirst().split(separator: " ")
            guard requestLine.count >= 2 else { conn.cancel(); return }
            var headers: [String: String] = [:]
            for line in lines {
                guard let colon = line.firstIndex(of: ":") else { continue }
                headers[line[..<colon].lowercased()] = line[line.index(after: colon)...].trimmingCharacters(in: .whitespaces)
            }
            let body = buf[headerEnd.upperBound...]
            let needed = Int(headers["content-length"] ?? "0") ?? 0
            if body.count < needed && !done {
                self.read(conn, buffer: buf)
                return
            }
            self.handle(conn, method: String(requestLine[0]), target: String(requestLine[1]),
                        headers: headers, body: Data(body.prefix(needed)))
        }
    }

    private func handle(_ conn: NWConnection, method: String, target: String, headers: [String: String], body: Data) {
        if method == "OPTIONS" {
            reply(conn, status: 204, text: "No Content", extra: [
                "Access-Control-Allow-Methods": "GET, POST, HEAD, OPTIONS",
                "Access-Control-Allow-Headers": headers["access-control-request-headers"] ?? "*",
                "Access-Control-Max-Age": "600",
            ])
            return
        }

        // "/<host:port>/<path?query>" → "http://<host:port>/<path?query>"
        let trimmed = target.hasPrefix("/") ? String(target.dropFirst()) : target
        guard let url = URL(string: "http://" + trimmed), let host = url.host, Self.isLocal(host) else {
            reply(conn, status: 403, text: "Forbidden", body: "Only local-network links can be relayed.")
            return
        }

        var req = URLRequest(url: url)
        req.httpMethod = method
        for name in ["range", "content-type", "accept"] {
            if let v = headers[name] { req.setValue(v, forHTTPHeaderField: name) }
        }
        if method == "POST" || method == "PUT" { req.httpBody = body }

        let task = session.dataTask(with: req)
        lock.lock(); connections[task.taskIdentifier] = conn; lock.unlock()
        conn.stateUpdateHandler = { [weak task] state in
            if case .cancelled = state { task?.cancel() }
            if case .failed = state { task?.cancel() }
        }
        task.resume()
    }

    private func reply(_ conn: NWConnection, status: Int, text: String, extra: [String: String] = [:], body: String = "") {
        var head = "HTTP/1.1 \(status) \(text)\r\nAccess-Control-Allow-Origin: *\r\nConnection: close\r\nContent-Length: \(body.utf8.count)\r\n"
        for (k, v) in extra { head += "\(k): \(v)\r\n" }
        head += "\r\n"
        conn.send(content: Data((head + body).utf8), isComplete: true, completion: .contentProcessed { _ in conn.cancel() })
    }

    // MARK: Answers from the local server, streamed back to the page

    private func connection(for task: URLSessionTask) -> NWConnection? {
        lock.lock(); defer { lock.unlock() }
        return connections[task.taskIdentifier]
    }

    func urlSession(_ session: URLSession, dataTask: URLSessionDataTask, didReceive response: URLResponse,
                    completionHandler: @escaping (URLSession.ResponseDisposition) -> Void) {
        guard let conn = connection(for: dataTask), let http = response as? HTTPURLResponse else {
            completionHandler(.cancel); return
        }
        var head = "HTTP/1.1 \(http.statusCode) \(HTTPURLResponse.localizedString(forStatusCode: http.statusCode))\r\n"
        head += "Access-Control-Allow-Origin: *\r\nAccess-Control-Expose-Headers: *\r\nConnection: close\r\n"
        let fields = http.allHeaderFields
        var encoded = false
        for (key, value) in fields {
            guard let k = key as? String else { continue }
            let lower = k.lowercased()
            if lower == "content-encoding" { encoded = true; continue }   // URLSession already decoded the body
            if ["content-length", "connection", "transfer-encoding", "keep-alive"].contains(lower) { continue }
            if lower.hasPrefix("access-control-") { continue }
            head += "\(k): \(value)\r\n"
        }
        if !encoded, http.expectedContentLength >= 0 {
            head += "Content-Length: \(http.expectedContentLength)\r\n"
        }
        head += "\r\n"
        conn.send(content: Data(head.utf8), completion: .contentProcessed { _ in })
        completionHandler(.allow)
    }

    func urlSession(_ session: URLSession, dataTask: URLSessionDataTask, didReceive data: Data) {
        connection(for: dataTask)?.send(content: data, completion: .contentProcessed { _ in })
    }

    func urlSession(_ session: URLSession, task: URLSessionTask, didCompleteWithError error: Error?) {
        lock.lock()
        let conn = connections.removeValue(forKey: task.taskIdentifier)
        lock.unlock()
        guard let conn else { return }
        if error != nil, task.response == nil {
            reply(conn, status: 502, text: "Bad Gateway", body: "Couldn't reach that address on your network.")
        } else {
            conn.send(content: nil, isComplete: true, completion: .contentProcessed { _ in conn.cancel() })
        }
    }

    // MARK: What counts as "local"

    /// localhost, 127.x, 10.x, 172.16–31.x, 192.168.x, 169.254.x, *.local, or a bare name with no dots.
    static func isLocal(_ host: String) -> Bool {
        let h = host.lowercased().trimmingCharacters(in: CharacterSet(charactersIn: "[]"))
        if h == "localhost" || h.hasSuffix(".local") || h == "::1" { return true }
        let parts = h.split(separator: ".").compactMap { Int($0) }
        if parts.count == 4 {
            switch (parts[0], parts[1]) {
            case (127, _), (10, _), (192, 168), (169, 254): return true
            case (172, 16...31): return true
            default: return false
            }
        }
        return !h.contains(".") && !h.contains(":")
    }
}

private final class ReadyFlag: @unchecked Sendable {
    private let lock = NSLock()
    private var value = false
    func set() { lock.lock(); value = true; lock.unlock() }
    var isSet: Bool { lock.lock(); defer { lock.unlock() }; return value }
}

/// Fetches frames from an on-phone camera server (EclipseLiveCam's
/// http://127.0.0.1:<port>/view) natively and hands each JPEG to the
/// virtual-camera script. Done outside the web view on purpose: WebKit may
/// refuse plain-http requests from the https live page, URLSession doesn't.
@MainActor
final class LocalFramePump {
    weak var webView: WKWebView?
    private var task: Task<Void, Never>?

    private let session: URLSession = {
        let config = URLSessionConfiguration.ephemeral
        config.timeoutIntervalForRequest = 4
        config.requestCachePolicy = .reloadIgnoringLocalCacheData
        config.urlCache = nil
        return URLSession(configuration: config)
    }()

    /// Handles a {type: "virtualCamPump", action: "start"/"stop", url} message from the page.
    func handle(_ msg: [String: Any], from webView: WKWebView?) {
        switch msg["action"] as? String {
        case "start":
            guard let s = msg["url"] as? String, let url = URL(string: s),
                  let host = url.host, LocalRelay.isLocal(host) else { return }
            self.webView = webView
            start(url)
        case "stop":
            stop()
        default:
            break
        }
    }

    func start(_ url: URL) {
        stop()
        task = Task { [weak self] in
            var failures = 0
            while !Task.isCancelled {
                let started = Date()
                do {
                    let (data, response) = try await self?.session.data(from: url) ?? (Data(), URLResponse())
                    if Task.isCancelled { break }
                    let code = (response as? HTTPURLResponse)?.statusCode ?? 0
                    failures = 0
                    if code == 200, !data.isEmpty {
                        self?.call("pushFrame('\(data.base64EncodedString())')")
                    } else if code == 404 {
                        self?.call("pumpGone()")
                        try? await Task.sleep(nanoseconds: 3_000_000_000)
                    } else {
                        self?.call("pumpMiss()")
                    }
                } catch {
                    if Task.isCancelled { break }
                    failures += 1
                    if failures >= 2 { self?.call("pumpError()") }
                    try? await Task.sleep(nanoseconds: 1_500_000_000)
                }
                // About 12 frames a second.
                let wait = 0.08 - Date().timeIntervalSince(started)
                if wait > 0 { try? await Task.sleep(nanoseconds: UInt64(wait * 1_000_000_000)) }
            }
        }
    }

    func stop() {
        task?.cancel()
        task = nil
    }

    private func call(_ js: String) {
        webView?.evaluateJavaScript("window.vhonixVirtualCam && window.vhonixVirtualCam.\(js);", completionHandler: nil)
    }
}
