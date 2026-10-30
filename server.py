import os
import re
import ssl
import http.cookiejar
import urllib.request
import urllib.error
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
SUB_DIR = os.path.join(CURRENT_DIR, "lopal-kz-site")
SITE_DIR = SUB_DIR if os.path.isdir(SUB_DIR) else CURRENT_DIR
UPSTREAM_BASE = "https://lopal.dat881.com"

# Cache for immutable upstream static assets (js, css, images, fonts)
STATIC_CACHE = {}

SSL_CTX = ssl.create_default_context()
SSL_CTX.check_hostname = False
SSL_CTX.verify_mode = ssl.CERT_NONE

# Shared upstream session cookie jar so lopal.dat881.com always keeps usrShortName="lopalenApp" in HttpSession
COOKIE_JAR = http.cookiejar.CookieJar()
UPSTREAM_OPENER = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(COOKIE_JAR),
    urllib.request.HTTPSHandler(context=SSL_CTX),
)

INJECT_HEAD = """
<style id="lopal-kz-auto-close-style">
  #vehicleTipsModal,
  #oilTipsArea {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
  }
  .chanpinnojiyou,
  .chanpinno,
  [onclick*="openProductDetailPage"] {
    cursor: pointer !important;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease !important;
  }
  .chanpinnojiyou:hover,
  .chanpinno:hover,
  [onclick*="openProductDetailPage"]:hover {
    transform: translateY(-1px) !important;
    box-shadow: 0 6px 18px rgba(33, 146, 94, 0.38) !important;
    background-color: #197a4d !important;
  }
</style>
<script id="lopal-kz-early-suppress">
  // 1. Suppress Tencent Maps empty-key alert popup
  window.alert = function(msg) {
    console.log("[Lopal KZ Suppressed Alert]:", msg);
  };
  try {
    localStorage.setItem("lopalenAppRemember", "lopalenAppRemember");
    localStorage.setItem("lopalenAppRememberTime", String(Date.now()));
    var cachedBrands = localStorage.getItem("front40LevelBrandsListlopalenApp") || "";
    if (cachedBrands.indexOf("lopal-kz-auto-close") !== -1) {
      localStorage.removeItem("front40LevelBrandsListlopalenApp");
      localStorage.removeItem("front40LevelLastTimelopalenApp");
    }
  } catch (e) {}
</script>
"""

INJECT_BODY_END = """
<script id="lopal-kz-auto-close-script">
(function() {
  function dismissPopupsAndHookProducts() {
    try {
      localStorage.setItem("lopalenAppRemember", "lopalenAppRemember");
      localStorage.setItem("lopalenAppRememberTime", String(Date.now()));
    } catch (e) {}

    if (window.jQuery) {
      var $ = window.jQuery;
      var $tips = $("#vehicleTipsModal");
      if ($tips.length && ($tips.hasClass("in") || $tips.css("display") !== "none")) {
        try {
          $("#consentTips").trigger("click");
          $tips.modal("hide").hide().removeClass("in");
        } catch (e) {}
        $(".modal-backdrop").remove();
        $("body").removeClass("modal-open").css({ overflow: "auto", paddingRight: "0" });
      }
    } else {
      var modal = document.getElementById("vehicleTipsModal");
      if (modal) modal.style.display = "none";
    }

    // Hook product button click on final vehicle selection step to open Dealers Map modal
    window.openProductDetailPage_VEHICLE = function(productUuid, usrProTypeUuid, category, productNo) {
      window.parent.postMessage({ type: "OPEN_DEALERS_MAP", productNo: productNo || "" }, "*");
    };
    window.openProductDetailPage_VIN = function(productUuid, usrProTypeUuid, category, productNo) {
      window.parent.postMessage({ type: "OPEN_DEALERS_MAP", productNo: productNo || "" }, "*");
    };
  }

  dismissPopupsAndHookProducts();
  document.addEventListener("DOMContentLoaded", dismissPopupsAndHookProducts);
  window.addEventListener("load", dismissPopupsAndHookProducts);
  setTimeout(dismissPopupsAndHookProducts, 100);
  setTimeout(dismissPopupsAndHookProducts, 400);
  setTimeout(dismissPopupsAndHookProducts, 900);

  // Delegated click listener for product name buttons on getLevelProducts
  document.addEventListener("click", function(e) {
    var btn = e.target.closest(".chanpinnojiyou, .chanpinno, .productnodiv, [onclick*='openProductDetailPage']");
    if (btn) {
      e.preventDefault();
      e.stopPropagation();
      var productName = (btn.innerText || btn.textContent || "").trim();
      window.parent.postMessage({ type: "OPEN_DEALERS_MAP", productNo: productName }, "*");
    }
  }, true);
})();
</script>
"""


def ensure_upstream_session():
    if len(COOKIE_JAR) > 0:
        return
    try:
        req = urllib.request.Request(
            UPSTREAM_BASE + "/intell/dir40pc/lopalenApp",
            headers={
                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
            },
        )
        with UPSTREAM_OPENER.open(req, timeout=15) as resp:
            resp.read(256)
    except Exception:
        pass


def transform_upstream_html(raw_bytes):
    text = raw_bytes.decode("utf-8", errors="replace")

    # 1. Point <base> to local proxy /intell/ so all relative resources & AJAX stay same-origin
    text = re.sub(
        r'<base\s+href=["\']https?://lopal\.dat881\.com(?::\d+)?/intell/["\']\s*/?>',
        '<base href="/intell/">',
        text,
        flags=re.IGNORECASE,
    )

    # 2. Fix any empty session usrShortName paths (e.g. dir40pc//getLevelProducts -> dir40pc/lopalenApp/getLevelProducts)
    text = text.replace("dir40pc//", "dir40pc/lopalenApp/")
    text = text.replace('"dir40pc/";', '"dir40pc/lopalenApp";')
    text = text.replace("'dir40pc/';", "'dir40pc/lopalenApp';")
    text = text.replace(
        "https://lopal.dat881.com:443/intell/",
        "/intell/",
    )

    # 3. Prevent broken Tencent Maps GLJS script (empty API key) from loading and firing alert()
    text = text.replace(
        'script.src = "https://map.qq.com/api/gljs?v=1.exp&key="+key+"&callback=initMap&libraries=service";',
        'script.src = ""; /* disabled broken empty-key qq map */',
    )

    # 4. Ensure localStorage.clear() on version bump preserves lopalenAppRemember
    text = text.replace(
        'localStorage.setItem("dir40pclevellopalenApp",v);',
        'localStorage.setItem("dir40pclevellopalenApp",v); localStorage.setItem("lopalenAppRemember","lopalenAppRemember"); localStorage.setItem("lopalenAppRememberTime", String(Date.now()));',
    )

    # 4b. Prevent #vehicleTipsModal (Disclaimer popup) from ever calling .modal()
    text = text.replace(
        '$("#vehicleTipsModal").on(',
        '$("#vehicleTipsModal_suppressed").on(',
    )

    # 5. Prevent mobile redirect inside iframe so mobile users stay inside our adapted wrapper
    text = text.replace(
        'location.href = "https://market.dat881.com/longpanEN/#/";',
        '/* stay in responsive wrapper */',
    )

    # 6. Inject early CSS/JS into full HTML pages only (not small AJAX fragments)
    if "</head>" in text:
        text = text.replace("</head>", INJECT_HEAD + "\n</head>", 1)

    if "</body>" in text:
        text = text.replace("</body>", INJECT_BODY_END + "\n</body>", 1)

    return text.encode("utf-8")


class LopalRequestHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=SITE_DIR, **kwargs)

    def is_proxy_path(self):
        p = self.path.split("?")[0]
        if p.startswith(("/intell/", "/dir40pc/", "/level/", "/dir14/")):
            return True
        return False

    def handle_proxy(self, method="GET"):
        ensure_upstream_session()

        rel_path = self.path
        if not rel_path.startswith("/intell/"):
            rel_path = "/intell" + (rel_path if rel_path.startswith("/") else "/" + rel_path)

        # Normalize any accidental missing usrShortName in URL
        rel_path = rel_path.replace("/dir40pc//", "/dir40pc/lopalenApp/")
        if rel_path.rstrip("/") == "/intell/dir40pc":
            rel_path = "/intell/dir40pc/lopalenApp"

        target_url = UPSTREAM_BASE + rel_path
        is_cacheable = method == "GET" and any(
            rel_path.split("?")[0].endswith(ext)
            for ext in (".js", ".css", ".png", ".jpg", ".jpeg", ".gif", ".woff", ".woff2", ".ttf")
        )

        if is_cacheable and target_url in STATIC_CACHE:
            status, ctype, body = STATIC_CACHE[target_url]
            self.send_response(status)
            self.send_header("Content-Type", ctype)
            self.send_header("Content-Length", str(len(body)))
            self.send_header("Cache-Control", "public, max-age=3600")
            self.end_headers()
            self.wfile.write(body)
            return

        req_body = None
        if method == "POST":
            length = int(self.headers.get("Content-Length", 0))
            if length > 0:
                req_body = self.rfile.read(length)

        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            "Accept": self.headers.get("Accept", "*/*"),
            "Accept-Language": "en-US,en;q=0.9,ru;q=0.8",
            "Referer": "https://lopal.dat881.com/intell/dir40pc/lopalenApp",
        }
        if "Content-Type" in self.headers:
            headers["Content-Type"] = self.headers["Content-Type"]
        if "X-Requested-With" in self.headers:
            headers["X-Requested-With"] = self.headers["X-Requested-With"]

        req = urllib.request.Request(target_url, data=req_body, headers=headers, method=method)
        try:
            with UPSTREAM_OPENER.open(req, timeout=20) as resp:
                status = resp.status
                ctype = resp.headers.get("Content-Type", "application/octet-stream")
                raw = resp.read()
        except urllib.error.HTTPError as e:
            status = e.code
            ctype = e.headers.get("Content-Type", "text/plain; charset=utf-8")
            raw = e.read() if e.fp else b""
        except Exception as e:
            self.send_response(502)
            self.send_header("Content-Type", "text/plain; charset=utf-8")
            self.end_headers()
            self.wfile.write(f"Upstream proxy error: {e}".encode("utf-8"))
            return

        if "text/html" in ctype.lower():
            raw = transform_upstream_html(raw)
        elif is_cacheable and status == 200:
            STATIC_CACHE[target_url] = (status, ctype, raw)

        self.send_response(status)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_GET(self):
        if self.is_proxy_path():
            self.handle_proxy("GET")
        else:
            super().do_GET()

    def do_HEAD(self):
        if self.is_proxy_path():
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.end_headers()
        else:
            super().do_HEAD()

    def do_POST(self):
        if self.is_proxy_path():
            self.handle_proxy("POST")
        else:
            self.send_error(405, "Method Not Allowed")

    def log_message(self, format, *args):
        pass


if __name__ == "__main__":
    ensure_upstream_session()
    port = 8080
    server = ThreadingHTTPServer(("0.0.0.0", port), LopalRequestHandler)
    print(f"Lopal KZ server running on http://localhost:{port}")
    server.serve_forever()
