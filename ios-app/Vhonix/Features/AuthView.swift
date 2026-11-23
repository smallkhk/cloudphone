import SwiftUI

struct AuthView: View {
    @EnvironmentObject private var app: AppState

    @State private var mode = "signin"
    @State private var name = ""
    @State private var email = ""
    @State private var password = ""
    @State private var confirm = ""
    @State private var busy = false
    @State private var error: String?
    @State private var otpEmail = ""
    @State private var showOtp = false

    private var registering: Bool { mode == "register" }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 26) {
                    VStack(spacing: 18) {
                        BrandMark(size: 84, showName: false)
                            .padding(.top, 36)
                        VStack(spacing: 8) {
                            Text(registering ? "Create your account" : "Welcome back")
                                .font(Typo.rounded(.largeTitle, .bold))
                                .foregroundStyle(Palette.text)
                                .multilineTextAlignment(.center)
                            Text("Real Android phones in the cloud — controlled from your iPhone.")
                                .font(.subheadline)
                                .foregroundStyle(Palette.text2)
                                .multilineTextAlignment(.center)
                        }
                    }

                    PillPicker(options: [PickOption(id: "signin", title: "Sign in"), PickOption(id: "register", title: "Create account")],
                               selection: $mode)

                    VStack(spacing: 14) {
                        if registering {
                            GlassField(label: "Name", text: $name, prompt: "Your name", capitalization: .words, contentType: .name)
                                .transition(.move(edge: .top).combined(with: .opacity))
                        }
                        GlassField(label: "Email", text: $email, prompt: "you@example.com", keyboard: .emailAddress, contentType: .emailAddress)
                        GlassField(label: "Password", text: $password, prompt: registering ? "At least 8 characters" : "Your password",
                                   secure: true, contentType: registering ? .newPassword : .password)
                        if registering {
                            GlassField(label: "Confirm password", text: $confirm, prompt: "Type it again", secure: true, contentType: .newPassword)
                                .transition(.move(edge: .top).combined(with: .opacity))
                        }
                    }
                    .animation(.spring(response: 0.4, dampingFraction: 0.85), value: registering)
                    .glassCard(padding: 18, highlight: true)

                    if let error {
                        HStack(spacing: 8) {
                            Image(systemName: "exclamationmark.circle.fill")
                            Text(error).frame(maxWidth: .infinity, alignment: .leading)
                        }
                        .font(.footnote.weight(.medium))
                        .foregroundStyle(Palette.danger)
                        .padding(.horizontal, 4)
                        .transition(.opacity)
                    }

                    Button {
                        Task { await submit() }
                    } label: {
                        HStack(spacing: 10) {
                            if busy { ProgressView().tint(.white) }
                            Text(registering ? "Create account" : "Sign in")
                            if !busy { Image(systemName: "arrow.right") }
                        }
                    }
                    .buttonStyle(.primary)
                    .disabled(busy || email.isEmpty || password.isEmpty || (registering && name.isEmpty))

                    Text(registering ? "We'll email you a 6-digit code to confirm it's you." : "New here? Tap Create account above.")
                        .font(.footnote).foregroundStyle(Palette.text3)
                        .multilineTextAlignment(.center)
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 40)
            }
            .screenBackground(animated: true)
            .navigationDestination(isPresented: $showOtp) {
                OtpView(email: otpEmail)
            }
            .toolbar(.hidden, for: .navigationBar)
        }
    }

    private func submit() async {
        Haptics.tap()
        busy = true
        withAnimation { error = nil }
        defer { busy = false }

        let cleanEmail = email.trimmingCharacters(in: .whitespaces).lowercased()

        do {
            let response: AuthResponse
            if registering {
                guard password == confirm else {
                    fail("The passwords don't match.")
                    return
                }
                response = try await APIClient.shared.send("POST", "auth/register", body: [
                    "name": name, "email": cleanEmail, "password": password, "password_confirmation": confirm,
                ])
            } else {
                response = try await APIClient.shared.send("POST", "auth/login", body: [
                    "email": cleanEmail, "password": password, "device_name": UIDevice.current.name,
                ])
            }

            if let token = response.token {
                Haptics.success()
                app.didSignIn(token: token, user: response.user)
            } else if response.otpRequired == true {
                otpEmail = response.email ?? cleanEmail
                showOtp = true
            } else {
                fail(response.message ?? "Something went wrong. Please try again.")
            }
        } catch {
            fail((error as? APIError)?.message ?? error.localizedDescription)
        }
    }

    private func fail(_ message: String) {
        Haptics.error()
        withAnimation { error = message }
    }
}

struct OtpView: View {
    let email: String

    @EnvironmentObject private var app: AppState
    @State private var code = ""
    @State private var busy = false
    @State private var error: String?
    @State private var shake: CGFloat = 0
    @FocusState private var focused: Bool

    var body: some View {
        ScrollView {
            VStack(spacing: 26) {
                ZStack {
                    Circle().fill(Theme.brand).frame(width: 120, height: 120).blur(radius: 34).opacity(0.5)
                    IconTile(symbol: "envelope.open.fill", colors: TileColors.indigo, size: 78)
                }
                .padding(.top, 30)

                VStack(spacing: 8) {
                    Text("Check your inbox").font(Typo.rounded(.largeTitle, .bold)).foregroundStyle(Palette.text)
                    Text("Enter the 6-digit code we sent to\n\(email)")
                        .font(.subheadline).foregroundStyle(Palette.text2).multilineTextAlignment(.center)
                }

                ZStack {
                    TextField("", text: $code)
                        .keyboardType(.numberPad)
                        .textContentType(.oneTimeCode)
                        .focused($focused)
                        .foregroundStyle(.clear)
                        .tint(.clear)
                        .accentColor(.clear)
                        .frame(width: 1, height: 1)
                        .opacity(0.02)
                        .onChange(of: code) { value in
                            let digits = String(value.filter(\.isNumber).prefix(6))
                            if digits != value { code = digits }
                            if digits.count == 6 { Task { await verify() } }
                        }

                    HStack(spacing: 10) {
                        ForEach(0..<6, id: \.self) { i in
                            let chars = Array(code)
                            let filled = i < chars.count
                            let current = i == chars.count && focused
                            Text(filled ? String(chars[i]) : "")
                                .font(Typo.rounded(.title, .bold))
                                .foregroundStyle(Palette.text)
                                .frame(maxWidth: .infinity, minHeight: 60)
                                .background(RoundedRectangle(cornerRadius: 14, style: .continuous).fill(Color.white.opacity(filled ? 0.10 : 0.05)))
                                .overlay(
                                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                                        .strokeBorder(current ? AnyShapeStyle(Theme.brand) : AnyShapeStyle(Palette.stroke), lineWidth: current ? 2 : 1)
                                )
                                .scaleEffect(filled ? 1 : 0.97)
                                .animation(.spring(response: 0.25, dampingFraction: 0.6), value: filled)
                        }
                    }
                    .contentShape(Rectangle())
                    .onTapGesture { focused = true }
                    .modifier(ShakeEffect(amount: shake))
                    .accessibilityElement(children: .ignore)
                    .accessibilityLabel("Verification code")
                    .accessibilityValue(code.isEmpty ? "Empty" : code.map { String($0) }.joined(separator: " "))
                    .accessibilityAddTraits(.isButton)
                }

                if let error {
                    Text(error).font(.footnote.weight(.medium)).foregroundStyle(Palette.danger).multilineTextAlignment(.center)
                }

                Button {
                    Task { await verify() }
                } label: {
                    HStack(spacing: 10) {
                        if busy { ProgressView().tint(.white) }
                        Text("Verify and continue")
                    }
                }
                .buttonStyle(.primary)
                .disabled(busy || code.count != 6)

                AsyncButton("Resend code") { await resend() }
                    .buttonStyle(.chip(Palette.accentText))
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 40)
        }
        .screenBackground(animated: true)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear { focused = true }
    }

    private func verify() async {
        guard !busy, code.count == 6 else { return }
        busy = true
        error = nil
        defer { busy = false }

        do {
            let response: AuthResponse = try await APIClient.shared.send("POST", "auth/verify-otp", body: [
                "email": email, "code": code, "device_name": UIDevice.current.name,
            ])
            if let token = response.token {
                Haptics.success()
                app.didSignIn(token: token, user: response.user)
            } else {
                wrong(response.message ?? "That code didn't work.")
            }
        } catch {
            wrong((error as? APIError)?.message ?? error.localizedDescription)
        }
    }

    private func wrong(_ message: String) {
        Haptics.error()
        error = message
        code = ""
        withAnimation(.linear(duration: 0.4)) { shake += 1 }
    }

    private func resend() async {
        do {
            let response: AuthResponse = try await APIClient.shared.send("POST", "auth/resend-otp", body: ["email": email])
            app.show(response.message ?? "A new code is on its way.")
        } catch {
            app.show(error)
        }
    }
}

struct ShakeEffect: GeometryEffect {
    var amount: CGFloat
    var animatableData: CGFloat {
        get { amount }
        set { amount = newValue }
    }

    func effectValue(size: CGSize) -> ProjectionTransform {
        ProjectionTransform(CGAffineTransform(translationX: 10 * sin(amount * .pi * 4), y: 0))
    }
}
