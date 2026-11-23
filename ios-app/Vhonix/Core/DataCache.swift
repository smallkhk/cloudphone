import Foundation

/// Keeps the last response of every screen on the phone, so screens open
/// instantly with the last-known prices/balance and refresh in the background.
/// Cleared on sign-out.
enum DataCache {
    private static let folder: URL = {
        let base = FileManager.default.urls(for: .cachesDirectory, in: .userDomainMask)[0]
        let dir = base.appendingPathComponent("vhonix-data", isDirectory: true)
        try? FileManager.default.createDirectory(at: dir, withIntermediateDirectories: true)
        return dir
    }()

    private static var memory: [String: Data] = [:]
    private static let lock = NSLock()

    private static func file(_ key: String) -> URL {
        let safe = key.map { $0.isLetter || $0.isNumber ? String($0) : "_" }.joined()
        return folder.appendingPathComponent(safe + ".json")
    }

    static func store(_ data: Data, for key: String) {
        lock.lock()
        memory[key] = data
        lock.unlock()
        let url = file(key)
        DispatchQueue.global(qos: .utility).async {
            try? data.write(to: url, options: .atomic)
        }
    }

    static func load(_ key: String) -> Data? {
        lock.lock()
        if let hit = memory[key] {
            lock.unlock()
            return hit
        }
        lock.unlock()

        guard let data = try? Data(contentsOf: file(key)) else { return nil }
        lock.lock()
        memory[key] = data
        lock.unlock()
        return data
    }

    static func clear() {
        lock.lock()
        memory.removeAll()
        lock.unlock()
        try? FileManager.default.removeItem(at: folder)
        try? FileManager.default.createDirectory(at: folder, withIntermediateDirectories: true)
    }
}
