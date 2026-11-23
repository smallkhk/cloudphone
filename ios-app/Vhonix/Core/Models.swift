import Foundation

// Shapes match app/Http/Controllers/Api/Mobile/MobileResources.php.
// Anything the server might leave out is optional so one missing field never
// breaks a whole screen.

struct User: Decodable, Equatable {
    let id: Int
    let name: String
    let email: String
    let balance: Double
    let isAdmin: Bool?
}

struct AuthResponse: Decodable {
    let token: String?
    let user: User?
    let otpRequired: Bool?
    let email: String?
    let message: String?
}

struct MeResponse: Decodable { let user: User }

struct Device: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
    let padCode: String?
    let ready: Bool
    let status: String?
    let online: Bool?
    let androidVersion: String?
    let model: String?
    let screenshotUrl: String?
    let autoRenew: Bool?
    let expiresAt: String?
    let expired: Bool?
}

struct DevicesResponse: Decodable { let devices: [Device] }

struct DeviceRef: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
    let padCode: String
}

struct InfoRowData: Decodable, Hashable {
    let label: String
    let value: String
}

struct DeviceDetails: Decodable {
    let rows: [InfoRowData]
    let simCountry: String?
    let timezone: String?
    let latitude: Double?
    let longitude: Double?
}

struct InstalledApp: Decodable, Hashable, Identifiable {
    let name: String
    let packageName: String
    let version: String?

    var id: String { packageName }
}

struct TaskItem: Decodable, Identifiable, Hashable {
    let id: Int
    let type: String?
    let status: String?
    let createdAt: String?
    let progress: String?
    let device: String?
}

struct Country: Decodable, Hashable, Identifiable {
    let code: String
    let name: String

    var id: String { code }
}

struct DeviceDetailResponse: Decodable {
    let device: Device
    let details: DeviceDetails?
    let apps: [InstalledApp]
    let tasks: [TaskItem]
    let countries: [Country]
}

struct LiveLink: Decodable { let url: String }

struct Sku: Decodable, Identifiable, Hashable {
    let id: Int
    let type: String?
    let name: String
    let model: String?
    let androidVersion: String?
    let durationLabel: String?
    let durationMinutes: Int?
    let price: Double
    let countryCode: String?
    let countryName: String?
    let soldOut: Bool?
    let tier: String?
}

struct ProxyProduct: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
    let price: Double
}

struct Catalog: Decodable {
    let plans: [Sku]
    let regions: [Country]
    let proxyProducts: [ProxyProduct]
    let proxyRegions: [Country]
    let balance: Double
}

struct ProductsResponse<Owned: Decodable>: Decodable {
    let products: [Sku]
    let owned: [Owned]
    let devices: [DeviceRef]
}

struct Payment: Decodable, Hashable {
    let id: Int
    let currency: String?
    let network: String?
    let address: String?
    let amountCrypto: Double?
    let amountUsd: Double?
    let status: String?
    let txHash: String?
    let expiresAt: String?
}

struct Order: Decodable, Identifiable, Hashable {
    let id: Int
    let reference: String?
    let product: String?
    let productType: String?
    let quantity: Int?
    let total: Double?
    let status: String?
    let error: String?
    let proxyStatus: String?
    let proxyError: String?
    let createdAt: String?
    let paidAt: String?
    let payment: Payment?
    let devices: [Device]?
    let emailAccounts: [EmailAccount]?
    let phoneNumbers: [PhoneNumber]?
    let cloudNumbers: [CloudNumber]?
    let proxies: [CustomerProxy]?
}

struct OrdersResponse: Decodable { let orders: [Order] }
struct OrderResponse: Decodable { let order: Order }

struct EmailAccount: Decodable, Identifiable, Hashable {
    let id: Int
    let product: String?
    let email: String?
    let password: String?
    let latestCode: String?
    let codeFetchedAt: String?
    let status: String?
    let deliveredAt: String?
}

struct PhoneNumber: Decodable, Identifiable, Hashable {
    let id: Int
    let product: String?
    let phoneNumber: String?
    let countryCode: String?
    let latestCode: String?
    let codeFetchedAt: String?
    let status: String?
}

struct SmsMessage: Decodable, Hashable {
    let from: String?
    let text: String?
    let at: String?
}

struct CloudNumber: Decodable, Identifiable, Hashable {
    let id: Int
    let product: String?
    let number: String?
    let countryCode: String?
    let countryName: String?
    let purchaseStatus: String?
    let ready: Bool
    let autoRenew: Bool
    let status: String?
    let expiresAt: String?
    let boundPadCode: String?
    let bindState: String?
    let sms: [SmsMessage]
}

struct CustomerProxy: Decodable, Identifiable, Hashable {
    let id: Int
    let source: String
    let label: String?
    let remarks: String?
    let host: String?
    let port: Int?
    let account: String?
    let hasPassword: Bool?
    let proxyName: String?
    let proxyType: String?
    let countryCode: String?
    let countryName: String?
    let purchaseStatus: String?
    let delivered: Bool
    let attachedPadCode: String?
    let product: String?
    let expiresAt: String?

    var isCustom: Bool { source == "custom" }
    var title: String { label?.nonEmpty ?? host ?? product ?? "Proxy" }
}

struct Network: Decodable, Hashable, Identifiable {
    let code: String
    let label: String

    var id: String { code }
}

struct Deposit: Decodable, Identifiable, Hashable {
    let id: Int
    let currency: String?
    let network: String?
    let address: String?
    let amountCrypto: Double?
    let amountUsd: Double?
    let status: String?
    let txHash: String?
    let expiresAt: String?
    let createdAt: String?
}

struct WalletTransaction: Decodable, Identifiable, Hashable {
    let id: Int
    let type: String?
    let amount: Double
    let balanceAfter: Double?
    let description: String?
    let createdAt: String?
}

struct Wallet: Decodable {
    let balance: Double
    let minDeposit: Double
    let networks: [Network]
    let deposits: [Deposit]
    let transactions: [WalletTransaction]
}

struct DriveStorage: Decodable {
    let usedBytes: Int64
    let totalBytes: Int64
}

struct DriveFile: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
    let sizeBytes: Int64
    let createdAt: String?
}

struct CloudDrive: Decodable {
    let storage: DriveStorage?
    let files: [DriveFile]
    let devices: [DeviceRef]
    let backups: [TaskItem]
}

extension String {
    var nonEmpty: String? { trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? nil : self }
}
