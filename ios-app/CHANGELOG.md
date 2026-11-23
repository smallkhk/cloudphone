# Vhonix iPhone app — what changed

Download each version from GitHub → Releases → `ios-v<version>`, sign with ESign.

## 1.9.0
- Local links work in the virtual camera: http://192.168.x.x, http://localhost and .local addresses on your Wi-Fi.
  The first time, iPhone asks to allow "local network" access — tap Allow.
- EclipseLiveCam's on-phone preview link (http://127.0.0.1:8723/) works as the virtual camera — paste it as shown in EclipseLiveCam.
  Keep EclipseLiveCam running with Picture-in-Picture. (Picture only: that preview has no sound.)

## 1.8.1
- Fix: camera share links showed nothing — the app now reads the picture and sound from the right place on the camera site.
- Says "Waiting for your camera — start sharing" sooner when the share isn't live.

## 1.8.0
- The virtual camera plays your camera share links (`…/live.php?s=…`), picture and sound.
  The camera site's `live.php` needs the small CORS update for this to work.

## 1.7.0
- More → Tools → **Virtual camera**: set the link any time, with a live preview, Test button and "Hear sound".
- The video's sound goes to the cloud phone as its microphone.
- Switches to turn the virtual camera, or just its sound, off without deleting the link.

## 1.6.0
- **Virtual camera**: a video link (HLS, MP4, WebRTC, MJPEG) shows up as a camera on your cloud phones.
- The live screen no longer gets stuck on "Connecting…" after the phone was in the background.

## 1.5.0
- Shop filters in order: Android version → Model → Tier, with search and sorting.
