import Foundation

/// "Vhonix virtual camera": a video link (HLS, MP4, WebRTC/WHEP or MJPEG) that
/// the cloud phone sees as a camera.
///
/// How it works: the live screen's streaming SDK asks the web view for a
/// camera with `getUserMedia`. A script injected before the page loads adds
/// a fake camera to `enumerateDevices` (so it shows up in the Camera menu
/// next to the iPhone's own cameras) and, when that camera is asked for,
/// returns a video track painted from the link instead of the real camera.
/// Nothing on the website had to change.
enum VirtualCamera {
    static let deviceId = "vhonix-virtual-camera"
    private static let key = "virtualCamera.url"
    private static let enabledKey = "virtualCamera.enabled"
    private static let audioKey = "virtualCamera.audio"

    /// The saved link ("" = none). One link for every phone.
    static var url: String {
        get { UserDefaults.standard.string(forKey: key) ?? "" }
        set { UserDefaults.standard.set(newValue, forKey: key) }
    }

    /// Off = the cloud phones don't see the virtual camera (the link is kept).
    static var enabled: Bool {
        get { UserDefaults.standard.object(forKey: enabledKey) as? Bool ?? true }
        set { UserDefaults.standard.set(newValue, forKey: enabledKey) }
    }

    /// On = the video's sound is what apps on the cloud phone hear as the microphone.
    static var audio: Bool {
        get { UserDefaults.standard.object(forKey: audioKey) as? Bool ?? true }
        set { UserDefaults.standard.set(newValue, forKey: audioKey) }
    }

    struct Settings: Encodable {
        var url: String
        var enabled: Bool
        var audio: Bool
        /// "http://127.0.0.1:<port>" when the link is a local-network http link (see LocalRelay).
        var relay: String?
    }

    static var current: Settings { Settings(url: url, enabled: enabled, audio: audio) }

    /// True for an http:// link to this iPhone or the local network.
    static func isLocalLink(_ link: String) -> Bool {
        let l = link.trimmingCharacters(in: .whitespacesAndNewlines)
        guard l.lowercased().hasPrefix("http://"), let host = URL(string: l)?.host else { return false }
        return LocalRelay.isLocal(host)
    }

    static func json(_ settings: Settings) -> String {
        (try? JSONEncoder().encode(settings)).flatMap { String(data: $0, encoding: .utf8) } ?? "{}"
    }

    /// Why a link can't be used, or nil if it's fine (an empty link is fine: it removes the camera).
    static func problem(with link: String) -> String? {
        let l = link.trimmingCharacters(in: .whitespacesAndNewlines)
        if l.isEmpty { return nil }
        let lower = l.lowercased()
        if lower.hasPrefix("rtmp") || lower.hasPrefix("rtsp") || lower.hasPrefix("srt:") {
            return "RTMP/RTSP/SRT links can't play inside an iPhone app. Use your video server's HLS link (ends in .m3u8) or its WebRTC (WHEP) link instead — most servers offer both for the same stream."
        }
        if lower.hasPrefix("http://") {
            if isLocalLink(l) { return nil }
            return "Plain http:// links only work for addresses on your own network (like http://192.168.1.5:8080 or http://localhost). For anything on the internet, use the https:// link."
        }
        guard lower.hasPrefix("https://"), let u = URL(string: l), u.host != nil else {
            return "That doesn't look like a web link. It should start with https://"
        }
        return nil
    }

    /// The script injected at document start, starting with these settings.
    /// Settings plus the relay address when the link needs it (starts the relay).
    static func resolved(_ settings: Settings = current) -> Settings {
        var s = settings
        if isLocalLink(s.url) { s.relay = LocalRelay.shared.start() }
        return s
    }

    static func script(_ settings: Settings = current) -> String {
        let settings = resolved(settings)
        return template
            .replacingOccurrences(of: "__VID__", with: "\"\(deviceId)\"")
            .replacingOccurrences(of: "__CONFIG__", with: json(settings))
    }

    private static let template = #"""
(function () {
  if (window.vhonixVirtualCam) return;
  var VID = __VID__;
  var LABEL = 'Vhonix virtual camera';
  var md = navigator.mediaDevices;
  var CFG = __CONFIG__;
  var origEnum = md && md.enumerateDevices ? md.enumerateDevices.bind(md) : null;
  var origGUM = md && md.getUserMedia ? md.getUserMedia.bind(md) : null;
  function post(m) { try { window.webkit.messageHandlers.vhonix.postMessage(m); } catch (e) {} }

  var cam = {
    relay: CFG.relay || '',
    id: VID, url: (CFG.url || '').trim(), enabled: CFG.enabled !== false, audio: CFG.audio !== false,
    probeNext: false,
    canvas: null, ctx: null, base: null, clones: [], timer: null, watch: null,
    video: null, img: null, pc: null, retry: null, snapTimer: null,
    actx: null, adest: null, anodes: [],
    status: 'off', message: '', tainted: false, checked: false,

    /** True when the cloud phone should be offered the virtual camera. */
    active: function () { return this.enabled && !!this.url; },

    setConfig: function (cfg) {
      cfg = cfg || {};
      var audioChanged = (cfg.audio !== false) !== this.audio;
      this.url = (cfg.url || '').trim();
      this.enabled = cfg.enabled !== false;
      this.audio = cfg.audio !== false;
      if (cfg.relay) this.relay = cfg.relay;
      if (this.base) {
        this.stopSource();
        if (this.url) this.startSource(); else this.setStatus('off', 'No link set.');
      }
      if (audioChanged && !this.audio) this.unhookAudio();
    },

    setStatus: function (s, msg) {
      this.status = s; this.message = msg || '';
      post({ type: 'virtualCam', status: s, message: this.message });
    },

    setUrl: function (u) { this.setConfig({ url: u, enabled: this.enabled, audio: this.audio }); },

    // ---- Sound: the video's audio, as a microphone track ----

    audioContext: function () {
      if (!this.actx) {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        this.actx = new AC();
        this.adest = this.actx.createMediaStreamDestination();
      }
      if (this.actx.state !== 'running' && this.actx.resume) { var r = this.actx.resume(); if (r && r.catch) r.catch(function () {}); }
      return this.actx;
    },

    /** A new audio track carrying the video's sound (silent until the video plays). */
    audioTrack: function () {
      if (!this.base || this.base.readyState === 'ended') this.startPipeline();
      var ctx = this.audioContext();
      if (!ctx) return null;
      var t = this.adest.stream.getAudioTracks()[0].clone();
      this.clones.push(t);
      return t;
    },

    hookElement: function (el) {
      if (!this.audio) return;
      var ctx = this.audioContext();
      if (!ctx) return;
      try {
        var n = ctx.createMediaElementSource(el);
        n.connect(this.adest);
        this.anodes.push(n);
        // Its sound now goes only to the cloud phone (not this iPhone's speaker).
        el.muted = false; el.defaultMuted = false; el.removeAttribute('muted');
      } catch (e) {}
    },

    hookStream: function (track) {
      if (!this.audio) return;
      var ctx = this.audioContext();
      if (!ctx) return;
      try {
        var stream = new MediaStream([track]);
        // WebRTC audio only flows into Web Audio while some element is playing it.
        var sink = new Audio(); sink.muted = true; sink.srcObject = stream;
        var p = sink.play(); if (p && p.catch) p.catch(function () {});
        var n = ctx.createMediaStreamSource(stream);
        n.connect(this.adest);
        n._sink = sink;
        this.anodes.push(n);
      } catch (e) {}
    },

    unhookAudio: function () {
      this.anodes.forEach(function (n) {
        try { n.disconnect(); } catch (e) {}
        if (n._sink) { try { n._sink.pause(); n._sink.srcObject = null; } catch (e) {} }
      });
      this.anodes = [];
    },

    /** A new video track for whoever asked for the camera. */
    track: function () {
      if (!this.base || this.base.readyState === 'ended') this.startPipeline();
      var t = this.base.clone();
      this.clones.push(t);
      return t;
    },

    box: function () {
      var b = document.getElementById('vhonix-vcam');
      if (!b) {
        b = document.createElement('div');
        b.id = 'vhonix-vcam';
        b.setAttribute('aria-hidden', 'true');
        b.style.cssText = 'position:fixed;left:0;top:0;width:2px;height:2px;opacity:0.01;overflow:hidden;pointer-events:none;z-index:-1;';
        (document.body || document.documentElement).appendChild(b);
      }
      return b;
    },

    startPipeline: function () {
      var self = this;
      this.stopPipeline();
      this.canvas = document.createElement('canvas');
      this.canvas.width = 1280; this.canvas.height = 720;
      this.canvas.style.cssText = 'width:2px;height:2px;';
      this.box().appendChild(this.canvas);
      this.ctx = this.canvas.getContext('2d');
      this.tainted = false; this.checked = false;
      this.draw();
      this.base = this.canvas.captureStream(30).getVideoTracks()[0];
      this.timer = setInterval(function () { self.draw(); }, 33);
      // Stop pulling the video once nobody is using the camera any more.
      this.watch = setInterval(function () {
        self.clones = self.clones.filter(function (t) { return t.readyState !== 'ended'; });
        if (!self.clones.length) self.stopPipeline();
      }, 5000);
      this.startSource();
    },

    stopPipeline: function () {
      clearInterval(this.timer); clearInterval(this.watch);
      this.timer = this.watch = null;
      this.stopSource();
      if (this.base) { try { this.base.stop(); } catch (e) {} }
      this.base = null;
      if (this.canvas && this.canvas.parentNode) this.canvas.parentNode.removeChild(this.canvas);
      this.canvas = this.ctx = null;
      if (this.actx && this.actx.suspend) { var r = this.actx.suspend(); if (r && r.catch) r.catch(function () {}); }
    },

    kind: function (u) {
      if (/live\.php\?(?:.*&)?s=[a-f0-9]{20}/i.test(u)) return 'share';
      if (/whep/i.test(u)) return 'whep';
      if (/mjpe?g|\.cgi(\?|$)|action=stream/i.test(u)) return 'mjpeg';
      if (/\.(jpe?g|png|gif|webp)(\?|$)/i.test(u)) return 'snapshot';
      return 'video';
    },

    /** Local http:// links go through the app's built-in relay (WebKit blocks http inside an https page). */
    address: function (u) {
      if (this.relay && /^http:\/\//i.test(u)) return this.relay + '/' + u.slice(7);
      return u;
    },

    startSource: function () {
      var self = this, k = this.kind(this.url), u = this.address(this.url);
      clearTimeout(this.retry);
      if (!u) { this.setStatus('off', 'No link set.'); return; }
      if (/^http:\/\//i.test(this.url) && !this.relay) {
        this.fail('Local link: the app couldn\'t start its local relay. Close and reopen this screen, then try again.');
        return;
      }
      this.setStatus('connecting', 'Connecting to your video…');

      if (k === 'share') { this.startShare(u); return; }

      if (k === 'mjpeg' || k === 'snapshot') {
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
          if (self.img !== img) return;
          if (self.status !== 'live') self.setStatus('live', '');
          // A single picture link (e.g. a webcam snapshot.jpg): keep re-fetching it.
          if (k === 'snapshot') self.snapTimer = setTimeout(function () {
            if (self.img === img) img.src = u + (u.indexOf('?') < 0 ? '?' : '&') + '_vh=' + Date.now();
          }, 150);
        };
        img.onerror = function () { if (self.img === img) self.fail('Couldn\'t load the video. Check the link, and that your video server allows cross-origin access (CORS).'); };
        this.img = img;
        this.box().appendChild(img);
        img.src = u;
        return;
      }

      var v = document.createElement('video');
      v.muted = true; v.defaultMuted = true; v.autoplay = true; v.playsInline = true;
      v.setAttribute('muted', ''); v.setAttribute('playsinline', '');
      v.crossOrigin = 'anonymous';
      v.onplaying = function () {
        if (self.video !== v) return;
        self.setStatus('live', '');
        if (self.actx && self.actx.state !== 'running') self.audioContext();
      };
      v.onerror = function () { if (self.video === v) self.fail('Couldn\'t play the video. Check the link is live, and that your video server allows cross-origin access (CORS).'); };
      v.onended = function () { if (self.video === v) self.fail('The video ended. Reconnecting…'); };
      this.video = v;
      this.box().appendChild(v);
      if (k !== 'whep') this.hookElement(v);

      if (k === 'whep') {
        this.whep(u, v).catch(function (e) {
          if (self.video === v) self.fail('WebRTC link failed: ' + ((e && e.message) || e));
        });
      } else {
        v.loop = !/\.m3u8(\?|$)/i.test(u); // a recorded video loops; a live stream doesn't
        v.src = u;
        var p = v.play(); if (p && p.catch) p.catch(function () {});
      }
    },

    whep: async function (u, v) {
      var self = this;
      var pc = new RTCPeerConnection();
      this.pc = pc;
      pc.addTransceiver('video', { direction: 'recvonly' });
      pc.addTransceiver('audio', { direction: 'recvonly' });
      pc.ontrack = function (e) {
        if (e.track.kind === 'audio') { if (self.pc === pc) self.hookStream(e.track); return; }
        if (e.track.kind !== 'video') return;
        v.srcObject = new MediaStream([e.track]);
        var p = v.play(); if (p && p.catch) p.catch(function () {});
      };
      pc.onconnectionstatechange = function () {
        if (self.pc === pc && (pc.connectionState === 'failed' || pc.connectionState === 'closed')) {
          self.fail('The WebRTC video connection dropped. Reconnecting…');
        }
      };
      await pc.setLocalDescription(await pc.createOffer());
      await new Promise(function (resolve) {
        if (pc.iceGatheringState === 'complete') return resolve();
        var t = setTimeout(resolve, 2000);
        pc.addEventListener('icegatheringstatechange', function () {
          if (pc.iceGatheringState === 'complete') { clearTimeout(t); resolve(); }
        });
      });
      var res = await fetch(u, { method: 'POST', headers: { 'Content-Type': 'application/sdp' }, body: pc.localDescription.sdp });
      if (!res.ok) throw new Error('the server answered ' + res.status);
      var sdp = await res.text();
      if (self.pc !== pc) return;
      await pc.setRemoteDescription({ type: 'answer', sdp: sdp });
    },

    // ---- Camera share links (live.php?s=…): JPEG frames + raw PCM audio chunks ----

    startShare: function (u) {
      var self = this;
      var m = u.match(/^(.*?live\.php)\?(?:.*&)?s=([a-f0-9]{20})/i);
      if (!m) { this.fail('That share link looks incomplete. Copy the whole link again.'); return; }
      var base = m[1], token = m[2].toLowerCase();
      // The link people share is the viewer page (/live.php); its frames and
      // sound are served by /api/live.php on the same site.
      if (!/\/api\/live\.php$/i.test(base)) base = base.replace(/live\.php$/i, 'api/live.php');
      var vid = 'vhonix' + Math.random().toString(36).slice(2, 12);
      var share = { stopped: false };
      this.share = share;
      function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
      function q(action, extra) { return base + '?action=' + action + '&s=' + token + '&v=' + vid + (extra || '') + '&_=' + Date.now(); }

      // Picture: fetch the newest frame about 10 times a second.
      (async function () {
        var misses = 0;
        while (!share.stopped) {
          var t0 = Date.now();
          try {
            var r = await fetch(q('view'), { cache: 'no-store' });
            if (share.stopped) break;
            if (r.status === 200) {
              var blob = await r.blob();
              var url = URL.createObjectURL(blob);
              var im = new Image();
              await new Promise(function (res) { im.onload = res; im.onerror = res; im.src = url; });
              URL.revokeObjectURL(url);
              if (share.stopped) break;
              if (im.naturalWidth) {
                self.img = im;
                misses = 0;
                if (self.status !== 'live') self.setStatus('live', '');
              }
            } else if (r.status === 404) {
              self.img = null;
              self.setStatus('error', 'This camera share link isn\'t active. Open the camera site, start sharing, then try again.');
              await sleep(4000);
            } else {
              // 204: the share exists but no fresh frame (the camera isn't sending right now).
              if (++misses >= 5) {
                self.img = null;
                if (self.status !== 'connecting' || !self.message) self.setStatus('connecting', 'Waiting for your camera — start sharing on the camera site.');
              }
            }
          } catch (e) {
            if (share.stopped) break;
            self.setStatus('error', 'Can\'t reach your camera share. If it keeps happening, the camera site needs the small CORS update.');
            await sleep(3000);
          }
          await sleep(Math.max(15, 100 - (Date.now() - t0)));
        }
      })();

      if (!this.audio) return;

      // Sound: pull each new audio chunk and play it, back to back, into the "microphone".
      (async function () {
        var after = 0, next = 0;
        try {
          var p = await (await fetch(q('poll'), { cache: 'no-store' })).json();
          after = Math.max(0, (p.audioSeq | 0) - 1); // start from the newest chunk, not old history
        } catch (e) {}
        while (!share.stopped) {
          try {
            var r = await fetch(q('audiopoll', '&after=' + after), { cache: 'no-store' });
            if (share.stopped) break;
            if (r.status === 200) {
              var seq = parseInt(r.headers.get('X-Seq') || '0', 10);
              var rate = parseInt(r.headers.get('X-Rate') || '24000', 10) || 24000;
              var buf = await r.arrayBuffer();
              after = seq > after ? seq : after + 1;
              if (!share.stopped) next = self.playPcm(buf, rate, next);
              continue;
            }
          } catch (e) {}
          await sleep(150);
        }
      })();
    },

    /** Plays one chunk of 16-bit mono PCM right after the previous one. Returns when the next should start. */
    playPcm: function (buf, rate, next) {
      var ctx = this.audioContext();
      var n = buf.byteLength >> 1;
      if (!ctx || !n) return next;
      var dv = new DataView(buf), ab = ctx.createBuffer(1, n, rate), ch = ab.getChannelData(0);
      for (var i = 0; i < n; i++) ch[i] = dv.getInt16(i * 2, true) / 32768;
      var src = ctx.createBufferSource();
      src.buffer = ab;
      src.connect(this.adest);
      var now = ctx.currentTime;
      // Fell behind (gap) or drifted too far ahead (delay): restart just ahead of now.
      if (next < now + 0.03 || next > now + 1.5) next = now + 0.25;
      src.start(next);
      return next + ab.duration;
    },

    fail: function (msg) {
      var self = this;
      this.stopSource();
      this.setStatus('error', msg);
      this.retry = setTimeout(function () { if (self.base) self.startSource(); }, 5000);
    },

    stopSource: function () {
      clearTimeout(this.retry); clearTimeout(this.snapTimer);
      this.unhookAudio();
      if (this.share) { this.share.stopped = true; this.share = null; }
      if (this.video) {
        var v = this.video; this.video = null;
        v.onplaying = v.onerror = v.onended = null;
        try { v.pause(); v.srcObject = null; v.removeAttribute('src'); v.load(); } catch (e) {}
        if (v.parentNode) v.parentNode.removeChild(v);
      }
      if (this.img) {
        var i = this.img; this.img = null;
        i.onload = i.onerror = null;
        if (i.parentNode) i.parentNode.removeChild(i);
      }
      if (this.pc) { var pc = this.pc; this.pc = null; try { pc.close(); } catch (e) {} }
    },

    draw: function () {
      var c = this.canvas, x = this.ctx;
      if (!c) return;
      var src = null, w = 0, h = 0;
      if (this.video && this.video.readyState >= 2 && this.video.videoWidth) { src = this.video; w = src.videoWidth; h = src.videoHeight; }
      else if (this.img && this.img.complete && this.img.naturalWidth) { src = this.img; w = src.naturalWidth; h = src.naturalHeight; }

      if (src && !this.tainted) {
        var s = Math.min(1, 1280 / Math.max(w, h));
        var cw = Math.round(w * s), ch = Math.round(h * s);
        if (c.width !== cw || c.height !== ch) { c.width = cw; c.height = ch; }
        try { x.drawImage(src, 0, 0, cw, ch); } catch (e) {}
        if (!this.checked) {
          this.checked = true;
          try { x.getImageData(0, 0, 1, 1); } catch (e) {
            this.tainted = true;
            this.setStatus('error', 'Your video server must allow cross-origin access (CORS) for this link.');
          }
        }
        return;
      }

      // No picture yet: a card that says what's going on.
      x.fillStyle = '#0b0b14'; x.fillRect(0, 0, c.width, c.height);
      x.textAlign = 'center'; x.fillStyle = '#ffffff';
      x.font = 'bold 40px -apple-system, sans-serif';
      x.fillText(LABEL, c.width / 2, c.height / 2 - 30);
      x.font = '26px -apple-system, sans-serif'; x.fillStyle = 'rgba(255,255,255,0.72)';
      var text = this.message || (this.url ? 'Connecting…' : 'No link set.');
      var words = text.split(' '), line = '', y = c.height / 2 + 20, max = c.width - 120;
      for (var n = 0; n < words.length; n++) {
        var test = line ? line + ' ' + words[n] : words[n];
        if (x.measureText(test).width > max && line) { x.fillText(line, c.width / 2, y); line = words[n]; y += 34; }
        else line = test;
      }
      if (line) x.fillText(line, c.width / 2, y);
    }
  };

  window.vhonixVirtualCam = cam;

  if (md && origEnum) {
    md.enumerateDevices = async function () {
      var list = [];
      try { list = Array.prototype.slice.call(await origEnum()); } catch (e) {}
      // "Find cameras": make the iPhone reveal its own camera names, as the page would.
      if (cam.probeNext) {
        cam.probeNext = false;
        if (!list.some(function (d) { return d.kind === 'videoinput' && d.label; })) {
          try {
            var p = await origGUM({ video: true });
            p.getTracks().forEach(function (t) { t.stop(); });
            list = Array.prototype.slice.call(await origEnum());
          } catch (e) {}
        }
      }
      if (cam.active()) {
        var info = { deviceId: VID, groupId: VID, kind: 'videoinput', label: LABEL };
        info.toJSON = function () { return { deviceId: VID, groupId: VID, kind: 'videoinput', label: LABEL }; };
        list.push(info);
      }
      return list;
    };
  }

  if (md && origGUM) md.getUserMedia = async function (c) {
    c = c || {};
    var wantsVirtual = false;
    try { wantsVirtual = !!c.video && JSON.stringify(c.video).indexOf(VID) >= 0; } catch (e) {}
    // The video's sound replaces the microphone while the virtual camera is on.
    var virtualAudio = !!c.audio && cam.active() && cam.audio;
    if (!wantsVirtual && !virtualAudio) return origGUM(c);

    var tracks = [];
    if (c.video) {
      if (wantsVirtual && cam.active()) {
        tracks.push(cam.track());
      } else {
        // Picked the virtual camera but it's turned off / has no link: use the normal camera.
        var v = await origGUM({ video: wantsVirtual ? true : c.video });
        v.getVideoTracks().forEach(function (t) { tracks.push(t); });
      }
    }
    if (c.audio) {
      var at = virtualAudio ? cam.audioTrack() : null;
      if (at) tracks.push(at);
      else {
        var a = await origGUM({ audio: c.audio });
        a.getAudioTracks().forEach(function (t) { tracks.push(t); });
      }
    }
    return new MediaStream(tracks);
  };
})();
"""#
}
