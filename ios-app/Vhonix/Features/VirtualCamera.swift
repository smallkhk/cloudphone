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

    /// The saved link ("" = none). One link for every phone.
    static var url: String {
        get { UserDefaults.standard.string(forKey: key) ?? "" }
        set { UserDefaults.standard.set(newValue, forKey: key) }
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
            return "The link must start with https:// — iPhone blocks plain http:// video inside a secure page. Turn on HTTPS on your video server, then use the https:// link."
        }
        guard lower.hasPrefix("https://"), let u = URL(string: l), u.host != nil else {
            return "That doesn't look like a web link. It should start with https://"
        }
        return nil
    }

    /// The script injected at document start. `url` is the link to start with.
    static func script(url: String) -> String {
        let encoded = (try? JSONEncoder().encode(url)).flatMap { String(data: $0, encoding: .utf8) } ?? "\"\""
        return template
            .replacingOccurrences(of: "__VID__", with: "\"\(deviceId)\"")
            .replacingOccurrences(of: "__URL__", with: encoded)
    }

    private static let template = #"""
(function () {
  if (window.vhonixVirtualCam) return;
  var VID = __VID__;
  var LABEL = 'Vhonix virtual camera';
  var md = navigator.mediaDevices;
  if (!md || !md.getUserMedia) return;
  var origEnum = md.enumerateDevices ? md.enumerateDevices.bind(md) : null;
  var origGUM = md.getUserMedia.bind(md);
  function post(m) { try { window.webkit.messageHandlers.vhonix.postMessage(m); } catch (e) {} }

  var cam = {
    id: VID, url: __URL__, probeNext: false,
    canvas: null, ctx: null, base: null, clones: [], timer: null, watch: null,
    video: null, img: null, pc: null, retry: null, snapTimer: null,
    status: 'off', message: '', tainted: false, checked: false,

    setStatus: function (s, msg) {
      this.status = s; this.message = msg || '';
      post({ type: 'virtualCam', status: s, message: this.message });
    },

    setUrl: function (u) {
      this.url = (u || '').trim();
      if (this.base) {
        this.stopSource();
        if (this.url) this.startSource(); else this.setStatus('off', 'No link set.');
      }
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
    },

    kind: function (u) {
      if (/whep/i.test(u)) return 'whep';
      if (/mjpe?g|\.cgi(\?|$)|action=stream/i.test(u)) return 'mjpeg';
      if (/\.(jpe?g|png|gif|webp)(\?|$)/i.test(u)) return 'snapshot';
      return 'video';
    },

    startSource: function () {
      var self = this, u = this.url, k = this.kind(u);
      clearTimeout(this.retry);
      if (!u) { this.setStatus('off', 'No link set.'); return; }
      this.setStatus('connecting', 'Connecting to your video…');

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
      v.onplaying = function () { if (self.video === v) self.setStatus('live', ''); };
      v.onerror = function () { if (self.video === v) self.fail('Couldn\'t play the video. Check the link is live, and that your video server allows cross-origin access (CORS).'); };
      v.onended = function () { if (self.video === v) self.fail('The video ended. Reconnecting…'); };
      this.video = v;
      this.box().appendChild(v);

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

    fail: function (msg) {
      var self = this;
      this.stopSource();
      this.setStatus('error', msg);
      this.retry = setTimeout(function () { if (self.base) self.startSource(); }, 5000);
    },

    stopSource: function () {
      clearTimeout(this.retry); clearTimeout(this.snapTimer);
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

  if (origEnum) {
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
      if (cam.url) {
        var info = { deviceId: VID, groupId: VID, kind: 'videoinput', label: LABEL };
        info.toJSON = function () { return { deviceId: VID, groupId: VID, kind: 'videoinput', label: LABEL }; };
        list.push(info);
      }
      return list;
    };
  }

  md.getUserMedia = async function (c) {
    c = c || {};
    var wantsVirtual = false;
    try { wantsVirtual = !!c.video && JSON.stringify(c.video).indexOf(VID) >= 0; } catch (e) {}
    if (!wantsVirtual) return origGUM(c);
    if (!cam.url) {
      // The virtual camera was picked but its link was removed: use the normal camera.
      var plain = {}; for (var k in c) plain[k] = c[k];
      plain.video = true;
      return origGUM(plain);
    }
    var tracks = [cam.track()];
    if (c.audio) {
      var a = await origGUM({ audio: c.audio });
      a.getAudioTracks().forEach(function (t) { tracks.push(t); });
    }
    return new MediaStream(tracks);
  };
})();
"""#
}
