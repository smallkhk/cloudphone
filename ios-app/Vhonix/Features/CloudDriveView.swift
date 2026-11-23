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
                Screen(backdrop: .indigo) {
                    storageCard(drive.storage)

                    SectionTitle(title: "Upload from a link", subtitle: "Shared by all your cloud phones")
                    VStack(alignment: .leading, spacing: 14) {
                        GlassField(label: "File link", text: $fileURL, prompt: "https://…", keyboard: .URL)
                        GlassField(label: "File name (optional)", text: $fileName, prompt: "my-file.apk", capitalization: .never)
                        AsyncButton {
                            var body: [String: Any] = ["url": fileURL.trimmingCharacters(in: .whitespaces)]
                            if let n = fileName.nonEmpty { body["file_name"] = n }
                            if await app.act("cloud-drive/upload", body: body) != nil {
                                fileURL = ""
                                fileName = ""
                                await load()
                            }
                        } label: { Label("Upload", systemImage: "icloud.and.arrow.up.fill") }
                        .buttonStyle(.primary)
                        .disabled(URL(string: fileURL)?.scheme == nil)
                    }
                    .glassCard(padding: 18)

                    SectionTitle(title: "Files", subtitle: "\(drive.files.count) item\(drive.files.count == 1 ? "" : "s")")
                    if drive.files.isEmpty {
                        EmptyStateView(icon: "folder.fill", title: "No files yet", message: "Upload one above.", colors: TileColors.cyan)
                            .glassCard()
                    } else {
                        VStack(spacing: 4) {
                            ForEach(Array(drive.files.enumerated()), id: \.element.id) { index, f in
                                if index > 0 { CardDivider() }
                                fileRow(f)
                            }
                        }
                        .glassCard(padding: 12)
                    }

                    SectionTitle(title: "Backups", subtitle: "Snapshot a whole phone")
                    VStack(alignment: .leading, spacing: 14) {
                        if drive.devices.isEmpty {
                            NoteView(text: "You need a ready cloud phone to make a backup.", icon: "iphone.slash", tint: Palette.warning)
                        } else {
                            DevicePicker(devices: drive.devices, padCode: $backupDevice)
                            AsyncButton {
                                if await app.act("cloud-drive/backup", body: ["pad_code": backupDevice]) != nil { await load() }
                            } label: { Label("Back up this phone", systemImage: "externaldrive.badge.timemachine") }
                            .buttonStyle(.primary)
                            .disabled(backupDevice.isEmpty)
                        }

                        ForEach(drive.backups) { b in
                            CardDivider()
                            HStack(spacing: 12) {
                                IconTile(symbol: "clock.arrow.circlepath", colors: TileColors.indigo, size: 36)
                                VStack(alignment: .leading, spacing: 2) {
                                    Text(b.device ?? "Backup").font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.text)
                                    Text(Format.when(b.createdAt) + (b.progress.map { " · \($0)%" } ?? ""))
                                        .font(.caption).foregroundStyle(Palette.text3)
                                }
                                Spacer()
                                StatusPill.forStatus(b.status)
                                AsyncButton {
                                    if await app.act("cloud-drive/backup/\(b.id)/progress") != nil { await load() }
                                } label: {
                                    Image(systemName: "arrow.clockwise")
                                        .font(.system(size: 14, weight: .bold))
                                        .foregroundStyle(Palette.accentText)
                                        .frame(width: 44, height: 44)
                                }
                                .accessibilityLabel("Refresh backup progress")
                            }
                        }
                    }
                    .glassCard(padding: 18)
                }
            }
        }
        .navigationTitle("Cloud Drive")
        .navigationBarTitleDisplayMode(.inline)
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

    private func storageCard(_ s: DriveStorage?) -> some View {
        let fraction = s.map { $0.totalBytes > 0 ? min(1, Double($0.usedBytes) / Double($0.totalBytes)) : 0 } ?? 0

        return HStack(spacing: 20) {
            ZStack {
                Circle().stroke(Palette.surfaceStrong, lineWidth: 10)
                Circle().trim(from: 0, to: fraction)
                    .stroke(Theme.brand, style: StrokeStyle(lineWidth: 10, lineCap: .round))
                    .rotationEffect(.degrees(-90))
                    .animation(.easeOut(duration: 0.8), value: fraction)
                Text("\(Int(fraction * 100))%").font(Typo.rounded(.headline, .bold)).foregroundStyle(Palette.text)
            }
            .frame(width: 86, height: 86)

            VStack(alignment: .leading, spacing: 6) {
                Text("Storage").font(.caption.weight(.semibold)).foregroundStyle(Palette.text3)
                if let s, s.totalBytes > 0 {
                    Text(Format.bytes(s.usedBytes)).font(Typo.rounded(.title2, .bold)).foregroundStyle(Palette.text)
                    Text("of \(Format.bytes(s.totalBytes)) used").font(.subheadline).foregroundStyle(Palette.text2)
                } else {
                    Text("Not available right now").font(.subheadline).foregroundStyle(Palette.text2)
                }
            }
            Spacer(minLength: 0)
            ArtImage(name: "art-drive", size: 96)
                .padding(.vertical, -16)
        }
        .glassCard(padding: 18, highlight: true, tint: Palette.indigo)
        .entrance(0)
        .accessibilityElement(children: .combine)
    }

    private func fileRow(_ f: DriveFile) -> some View {
        HStack(spacing: 12) {
            IconTile(symbol: f.name.lowercased().hasSuffix(".apk") ? "shippingbox.fill" : "doc.fill",
                     colors: f.name.lowercased().hasSuffix(".apk") ? TileColors.green : TileColors.cyan, size: 38)
            VStack(alignment: .leading, spacing: 2) {
                Text(f.name).font(Typo.rounded(.subheadline, .semibold)).foregroundStyle(Palette.text).lineLimit(1)
                Text([f.sizeBytes > 0 ? Format.bytes(f.sizeBytes) : nil, f.createdAt].compactMap { $0 }.joined(separator: " · "))
                    .font(.caption).foregroundStyle(Palette.text3)
            }
            Spacer()
            Button { deleting = f } label: {
                Image(systemName: "trash")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(Palette.danger)
                    .frame(width: 44, height: 44)
            }
            .accessibilityLabel("Delete \(f.name)")
        }
        .frame(minHeight: 50)
    }

    private func load() async {
        if drive == nil, let saved: CloudDrive = APIClient.shared.cached("cloud-drive") {
            drive = saved
            loading = false
        }
        do {
            let d: CloudDrive = try await APIClient.shared.get("cloud-drive")
            withAnimation(.easeOut(duration: 0.25)) { drive = d }
            error = nil
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
        loading = false
    }
}
