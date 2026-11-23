import SwiftUI

struct RootView: View {
    @EnvironmentObject private var app: AppState
    @Environment(\.scenePhase) private var scenePhase

    var body: some View {
        ZStack(alignment: .top) {
            Group {
                switch app.phase {
                case .signedOut: AuthView().transition(.opacity)
                case .signedIn: MainTabView().transition(.opacity)
                }
            }
            .animation(.easeInOut(duration: 0.35), value: app.phase)

            if let banner = app.banner {
                ToastView(banner: banner)
                    .transition(.move(edge: .top).combined(with: .opacity))
                    .onTapGesture { withAnimation { app.banner = nil } }
                    .zIndex(1)
            }
        }
        .onChange(of: scenePhase) { phase in
            if phase == .active, app.phase == .signedIn {
                Task { await app.refreshUser() }
            }
        }
    }
}

private struct ToastView: View {
    let banner: Banner

    var body: some View {
        HStack(alignment: .center, spacing: 12) {
            Image(systemName: banner.isError ? "exclamationmark.triangle.fill" : "checkmark.circle.fill")
                .font(.system(size: 20, weight: .semibold))
                .foregroundStyle(banner.isError ? Palette.danger : Palette.success)
            Text(banner.text)
                .font(Typo.rounded(.subheadline, .medium))
                .foregroundStyle(Palette.text)
                .frame(maxWidth: .infinity, alignment: .leading)
                .fixedSize(horizontal: false, vertical: true)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 14)
        .background(
            RoundedRectangle(cornerRadius: 20, style: .continuous)
                .fill(.ultraThinMaterial)
                .overlay(RoundedRectangle(cornerRadius: 20, style: .continuous)
                    .fill((banner.isError ? Palette.danger : Palette.success).opacity(0.10)))
        )
        .overlay(RoundedRectangle(cornerRadius: 20, style: .continuous).strokeBorder(Color.white.opacity(0.14), lineWidth: 1))
        .shadow(color: .black.opacity(0.4), radius: 20, y: 10)
        .padding(.horizontal, 14)
        .padding(.top, 4)
        .accessibilityElement(children: .combine)
        .accessibilityAddTraits(.isStaticText)
    }
}
