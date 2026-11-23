import SwiftUI

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
