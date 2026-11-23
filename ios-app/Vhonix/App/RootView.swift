import SwiftUI

struct RootView: View {
    @EnvironmentObject private var app: AppState
    @Environment(\.scenePhase) private var scenePhase

    var body: some View {
        ZStack(alignment: .top) {
            switch app.phase {
            case .locked: AccessCodeView()
            case .signedOut: AuthView()
            case .signedIn: MainTabView()
            }

            if let banner = app.banner {
                BannerView(banner: banner)
                    .transition(.move(edge: .top).combined(with: .opacity))
                    .onTapGesture { withAnimation { app.banner = nil } }
                    .zIndex(1)
            }
        }
        .task { await app.recheckAccessCode() }
        .onChange(of: scenePhase) { phase in
            if phase == .active, app.phase == .signedIn {
                Task { await app.refreshUser() }
            }
        }
    }
}

private struct BannerView: View {
    let banner: Banner

    var body: some View {
        HStack(alignment: .top, spacing: 10) {
            Image(systemName: banner.isError ? "exclamationmark.triangle.fill" : "checkmark.circle.fill")
            Text(banner.text).font(.subheadline).frame(maxWidth: .infinity, alignment: .leading)
        }
        .padding(14)
        .foregroundStyle(.white)
        .background(banner.isError ? Color.red.opacity(0.92) : Color.green.opacity(0.92))
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .shadow(radius: 8, y: 4)
        .padding(.horizontal, 16)
        .padding(.top, 6)
    }
}
