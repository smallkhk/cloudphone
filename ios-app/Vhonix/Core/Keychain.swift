import Foundation
import Security

/// Small Keychain wrapper for the sign-in token and the access code.
enum Keychain {
    private static func base(_ key: String) -> [String: Any] {
        [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: "vhonix",
            kSecAttrAccount as String: key,
        ]
    }

    static func set(_ value: String?, for key: String) {
        SecItemDelete(base(key) as CFDictionary)
        UserDefaults.standard.removeObject(forKey: "kc." + key)

        guard let value, let data = value.data(using: .utf8) else { return }

        var item = base(key)
        item[kSecValueData as String] = data
        item[kSecAttrAccessible as String] = kSecAttrAccessibleAfterFirstUnlock

        // Re-signed (sideloaded) builds occasionally can't use the Keychain;
        // fall back to app storage so the user isn't asked to sign in every launch.
        if SecItemAdd(item as CFDictionary, nil) != errSecSuccess {
            UserDefaults.standard.set(value, forKey: "kc." + key)
        }
    }

    static func get(_ key: String) -> String? {
        var query = base(key)
        query[kSecReturnData as String] = true
        query[kSecMatchLimit as String] = kSecMatchLimitOne

        var out: AnyObject?
        if SecItemCopyMatching(query as CFDictionary, &out) == errSecSuccess,
           let data = out as? Data, let value = String(data: data, encoding: .utf8) {
            return value
        }

        return UserDefaults.standard.string(forKey: "kc." + key)
    }
}
