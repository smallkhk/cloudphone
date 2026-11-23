import SwiftUI

struct AccessCodeView: View {
    @EnvironmentObject private var app: AppState
    @State private var code = ""
    @State private var busy = false
    @State private var error: String?

    var body: some View {
        VStack(spacing: 24) {
            Spacer()

            BrandMark()

            VStack(spacing: 6) {
                Text("Enter your access code").font(.title2.bold())
                Text("You received this code with the app.").foregroundStyle(.secondary)
            }

            TextField("XXXX-XXXX-XXXX-XXXX", text: $code)
                .textInputAutocapitalization(.characters)
                .autocorrectionDisabled()
                .font(.title3.monospaced())
                .multilineTextAlignment(.center)
                .padding()
                .background(Color(.secondarySystemBackground))
                .clipShape(RoundedRectangle(cornerRadius: 14))
                .submitLabel(.go)
                .onSubmit { Task { await unlock() } }

            if let error {
                Text(error).font(.footnote).foregroundStyle(.red).multilineTextAlignment(.center)
            }

            Button {
                Task { await unlock() }
            } label: {
                if busy { ProgressView().tint(.white) } else { Text("Unlock") }
            }
            .buttonStyle(.primary)
            .disabled(busy || code.trimmingCharacters(in: .whitespaces).count < 4)

            Spacer()
            Spacer()
        }
        .padding(24)
    }

    private func unlock() async {
        busy = true
        error = nil
        defer { busy = false }

        do {
            try await app.unlock(with: code)
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
    }
}

struct BrandMark: View {
    var body: some View {
        VStack(spacing: 10) {
            Text("V")
                .font(.system(size: 44, weight: .heavy, design: .rounded))
                .foregroundStyle(.white)
                .frame(width: 84, height: 84)
                .background(Theme.gradient)
                .clipShape(RoundedRectangle(cornerRadius: 22, style: .continuous))
            Text(Config.appName).font(.title.bold())
        }
    }
}
