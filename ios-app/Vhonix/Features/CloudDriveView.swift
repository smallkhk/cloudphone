import SwiftUI

struct CloudDriveView: View {
    @EnvironmentObject private var app: AppState
    @State private var drive: CloudDrive?
    @State private var loading = true
    @State private var error: String?
    @State private var fileURL = ""
    @State private var fileName = ""
    @State private var backupDevice = ""
    @State private var deleting: DriveFile?

    var body: some View {
        LoadingView(isLoading: loading && drive == nil, error: drive == nil ? error : nil, retry: load) {
            if let drive {
                List {
                    Section("Storage") {
                        if let s = drive.storage, s.totalBytes > 0 {
                            VStack(alignment: .leading, spacing: 6) {
                                ProgressView(value: Double(s.usedBytes), total: Double(max(s.totalBytes, 1)))
                                Text("\(Format.bytes(s.usedBytes)) of \(Format.bytes(s.totalBytes)) used")
                                    .font(.caption).foregroundStyle(.secondary)
                            }
                        } else {
                            Text("Storage details aren't available right now.").foregroundStyle(.secondary)
                        }
                    }

                    Section {
                        TextField("Link to the file", text: $fileURL)
                            .textInputAutocapitalization(.never).autocorrectionDisabled().keyboardType(.URL)
                        TextField("File name (optional)", text: $fileName)
                        AsyncButton("Upload") {
                            var body: [String: Any] = ["url": fileURL.trimmingCharacters(in: .whitespaces)]
                            if let n = fileName.nonEmpty { body["file_name"] = n }
                            if await app.act("cloud-drive/upload", body: body) != nil {
                                fileURL = ""
                                fileName = ""
                                await load()
                            }
                        }
                        .disabled(URL(string: fileURL)?.scheme == nil)
                    } header: {
                        Text("Upload from a link")
                    } footer: {
                        Text("Shared by all your cloud phones.")
                    }

                    Section("Files") {
                        if drive.files.isEmpty {
                            Text("No files yet.").foregroundStyle(.secondary)
                        }
                        ForEach(drive.files) { f in
                            HStack {
                                Image(systemName: "doc").foregroundStyle(Theme.accent)
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(f.name).lineLimit(1)
                                    Text([f.sizeBytes > 0 ? Format.bytes(f.sizeBytes) : nil, f.createdAt].compactMap { $0 }.joined(separator: " · "))
                                        .font(.caption).foregroundStyle(.secondary)
                                }
                            }
                            .swipeActions {
                                Button("Delete", role: .destructive) { deleting = f }
                            }
                        }
                    }

                    Section {
                        if drive.devices.isEmpty {
                            Text("You need a ready cloud phone to make a backup.").foregroundStyle(.secondary)
                        } else {
                            DevicePicker(devices: drive.devices, padCode: $backupDevice)
                            AsyncButton("Back up this phone") {
                                if await app.act("cloud-drive/backup", body: ["pad_code": backupDevice]) != nil { await load() }
                            }
                            .disabled(backupDevice.isEmpty)
                        }
                        ForEach(drive.backups) { b in
                            HStack {
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(b.device ?? "Backup").font(.subheadline)
                                    Text(Format.when(b.createdAt) + (b.progress.map { " · \($0)%" } ?? ""))
                                        .font(.caption).foregroundStyle(.secondary)
                                }
                                Spacer()
                                StatusPill.forStatus(b.status)
                                AsyncButton {
                                    if await app.act("cloud-drive/backup/\(b.id)/progress") != nil { await load() }
                                } label: {
                                    Image(systemName: "arrow.clockwise")
                                }
                                .buttonStyle(.borderless)
                            }
                        }
                    } header: {
                        Text("Backups")
                    }
                }
            }
        }
        .navigationTitle("Cloud Drive")
        .refreshable { await load() }
        .task { await load() }
        .confirmationDialog("Delete \(deleting?.name ?? "this file")?", isPresented: Binding(get: { deleting != nil }, set: { if !$0 { deleting = nil } }), titleVisibility: .visible) {
            Button("Delete", role: .destructive) {
                if let f = deleting {
                    Task {
                        if await app.act("cloud-drive/file", method: "DELETE", body: ["file_ids": [f.id]]) != nil { await load() }
                    }
                }
            }
        }
    }

    private func load() async {
        do {
            drive = try await APIClient.shared.get("cloud-drive")
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}
