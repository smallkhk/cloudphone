import SwiftUI

struct AuthView: View {
    @EnvironmentObject private var app: AppState

    enum Mode: String, CaseIterable { case signIn = "Sign in", register = "Create account" }

    @State private var mode: Mode = .signIn
    @State private var name = ""
    @State private var email = ""
    @State private var password = ""
    @State private var confirm = ""
    @State private var busy = false
    @State private var error: String?
    @State private var otpEmail = ""
    @State private var showOtp = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 22) {
                    BrandMark().padding(.top, 30)

                    Picker("", selection: $mode) {
                        ForEach(Mode.allCases, id: \.self) { Text($0.rawValue).tag($0) }
                    }
                    .pickerStyle(.segmented)

                    VStack(spacing: 12) {
                        if mode == .register {
                            field("Name", text: $name).textContentType(.name)
                        }
                        field("Email", text: $email)
                            .textContentType(.emailAddress)
                            .keyboardType(.emailAddress)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                        secure("Password", text: $password)
                            .textContentType(mode == .register ? .newPassword : .password)
                        if mode == .register {
                            secure("Confirm password", text: $confirm).textContentType(.newPassword)
                        }
                    }

                    if let error {
                        Text(error).font(.footnote).foregroundStyle(.red).multilineTextAlignment(.center)
                    }

                    Button {
                        Task { await submit() }
                    } label: {
                        if busy { ProgressView().tint(.white) } else { Text(mode.rawValue) }
                    }
                    .buttonStyle(.primary)
                    .disabled(busy || email.isEmpty || password.isEmpty)
                }
                .padding(24)
            }
            .navigationDestination(isPresented: $showOtp) {
                OtpView(email: otpEmail)
            }
        }
    }

    private func field(_ title: String, text: Binding<String>) -> some View {
        TextField(title, text: text)
            .padding()
            .background(Color(.secondarySystemBackground))
            .clipShape(RoundedRectangle(cornerRadius: 12))
    }

    private func secure(_ title: String, text: Binding<String>) -> some View {
        SecureField(title, text: text)
            .padding()
            .background(Color(.secondarySystemBackground))
            .clipShape(RoundedRectangle(cornerRadius: 12))
    }

    private func submit() async {
        busy = true
        error = nil
        defer { busy = false }

        let cleanEmail = email.trimmingCharacters(in: .whitespaces).lowercased()

        do {
            let response: AuthResponse
            if mode == .register {
                guard password == confirm else {
                    error = "The passwords don't match."
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
                app.didSignIn(token: token, user: response.user)
            } else if response.otpRequired == true {
                otpEmail = response.email ?? cleanEmail
                showOtp = true
            } else {
                error = response.message ?? "Something went wrong. Please try again."
            }
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
    }
}

struct OtpView: View {
    let email: String

    @EnvironmentObject private var app: AppState
    @State private var code = ""
    @State private var busy = false
    @State private var error: String?

    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "envelope.badge").font(.system(size: 44)).foregroundStyle(Theme.accent)
            Text("Check your email").font(.title2.bold())
            Text("We sent a 6-digit code to \(email). Enter it below to finish.")
                .multilineTextAlignment(.center).foregroundStyle(.secondary)

            TextField("123456", text: $code)
                .keyboardType(.numberPad)
                .textContentType(.oneTimeCode)
                .font(.system(size: 30, weight: .semibold, design: .monospaced))
                .multilineTextAlignment(.center)
                .padding()
                .background(Color(.secondarySystemBackground))
                .clipShape(RoundedRectangle(cornerRadius: 14))
                .onChange(of: code) { value in
                    let digits = String(value.filter(\.isNumber).prefix(6))
                    if digits != value { code = digits }
                }

            if let error {
                Text(error).font(.footnote).foregroundStyle(.red).multilineTextAlignment(.center)
            }

            Button {
                Task { await verify() }
            } label: {
                if busy { ProgressView().tint(.white) } else { Text("Verify") }
            }
            .buttonStyle(.primary)
            .disabled(busy || code.count != 6)

            AsyncButton("Resend code") { await resend() }

            Spacer()
        }
        .padding(24)
        .navigationTitle("Verify email")
        .navigationBarTitleDisplayMode(.inline)
    }

    private func verify() async {
        busy = true
        error = nil
        defer { busy = false }

        do {
            let response: AuthResponse = try await APIClient.shared.send("POST", "auth/verify-otp", body: [
                "email": email, "code": code, "device_name": UIDevice.current.name,
            ])
            if let token = response.token {
                app.didSignIn(token: token, user: response.user)
            } else {
                error = response.message ?? "That code didn't work."
            }
        } catch {
            self.error = (error as? APIError)?.message ?? error.localizedDescription
        }
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
