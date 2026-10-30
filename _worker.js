export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // Proxy dynamic selector API and pages
    if (
      url.pathname.startsWith('/intell/') ||
      url.pathname.startsWith('/dir40pc/') ||
      url.pathname.startsWith('/level/') ||
      url.pathname.startsWith('/dir14/')
    ) {
      let relPath = url.pathname;
      if (!relPath.startsWith('/intell/')) {
        relPath = '/intell' + relPath;
      }
      relPath = relPath.replace('/dir40pc//', '/dir40pc/lopalenApp/');
      if (relPath.replace(/\/$/, '') === '/intell/dir40pc') {
        relPath = '/intell/dir40pc/lopalenApp';
      }

      const upstreamUrl = 'https://lopal.dat881.com' + relPath + url.search;

      // Always pass desktop User-Agent so upstream doesn't redirect mobile to broken /longpanEN/
      const headers = new Headers(request.headers);
      headers.set(
        'User-Agent',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
      );
      headers.set('Referer', 'https://lopal.dat881.com/intell/dir40pc/lopalenApp');
      headers.set('Accept-Language', 'en-US,en;q=0.9,ru;q=0.8');

      try {
        const resp = await fetch(upstreamUrl, {
          method: request.method,
          headers: headers,
          body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
          redirect: 'follow',
        });

        const contentType = resp.headers.get('content-type') || '';
        if (contentType.includes('text/html')) {
          let text = await resp.text();
          text = text.replace(/<base\s+href=["'][^"']*["']\s*\/?>/gi, '<base href="/intell/">');
          text = text.replace('https://map.qq.com/api/gljs?v=1.exp&key=', '');
          text = text.replace(/dir40pc\/\//g, 'dir40pc/lopalenApp/');
          text = text.replace(/"dir40pc\/";/g, '"dir40pc/lopalenApp";');
          text = text.replace(/'dir40pc\/';/g, "'dir40pc/lopalenApp';");
          text = text.replace(
            'location.href = "https://market.dat881.com/longpanEN/#/";',
            '/* stay in responsive wrapper */'
          );

          // Neuter disclaimer and tips modals completely so bootstrap cannot show them
          text = text.replace(/id=["']vehicleTipsModal["']/gi, 'id="vehicleTipsModal_disabled" style="display:none!important;"');
          text = text.replace(/\$\(["']#vehicleTipsModal["']\)/g, '$("#vehicleTipsModal_disabled")');
          text = text.replace(/id=["']oilTipsArea["']/gi, 'id="oilTipsArea_disabled" style="display:none!important;"');
          text = text.replace(/\$\(["']#oilTipsArea["']\)/g, '$("#oilTipsArea_disabled")');

          const injectHead = `
<style id="lopal-cf-suppress">
  #vehicleTipsModal,
  #vehicleTipsModal_disabled,
  #oilTipsArea,
  #oilTipsArea_disabled,
  .modal-backdrop,
  .modal-backdrop.in {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
    width: 0 !important;
    height: 0 !important;
    z-index: -9999 !important;
  }
  body.modal-open {
    overflow: auto !important;
    padding-right: 0 !important;
    position: static !important;
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
<script>
  window.alert = function(){};
  try {
    localStorage.setItem("lopalenAppRemember", "lopalenAppRemember");
    localStorage.setItem("lopalenAppRememberTime", String(Date.now() + 365*24*3600*1000));
  } catch(e){}
</script>`;

          const injectBody = `
<script>
(function() {
  function dismiss() {
    try {
      localStorage.setItem("lopalenAppRemember", "lopalenAppRemember");
      localStorage.setItem("lopalenAppRememberTime", String(Date.now() + 365*24*3600*1000));
    } catch(e){}
    try {
      var btn = document.getElementById("consentTips");
      if (btn) btn.click();
    } catch(e){}
    var m1 = document.getElementById("vehicleTipsModal");
    if (m1) m1.remove();
    var m2 = document.getElementById("vehicleTipsModal_disabled");
    if (m2) m2.remove();
    var m3 = document.getElementById("oilTipsArea");
    if (m3) m3.remove();
    var m4 = document.getElementById("oilTipsArea_disabled");
    if (m4) m4.remove();
    var drops = document.querySelectorAll(".modal-backdrop");
    for (var i = 0; i < drops.length; i++) drops[i].remove();
    document.body.classList.remove("modal-open");
    document.body.style.overflow = "auto";
  }
  dismiss();
  document.addEventListener("DOMContentLoaded", dismiss);
  window.addEventListener("load", dismiss);
  var killTimer = setInterval(dismiss, 150);
  setTimeout(function() { clearInterval(killTimer); }, 5000);

  window.openProductDetailPage_VEHICLE = function(p, u, c, no) {
    window.parent.postMessage({ type: "OPEN_DEALERS_MAP", productNo: no || "" }, "*");
  };
  window.openProductDetailPage_VIN = function(p, u, c, no) {
    window.parent.postMessage({ type: "OPEN_DEALERS_MAP", productNo: no || "" }, "*");
  };
})();
</script>`;

          if (text.includes('</head>')) text = text.replace('</head>', injectHead + '\n</head>');
          if (text.includes('</body>')) text = text.replace('</body>', injectBody + '\n</body>');

          const newHeaders = new Headers(resp.headers);
          newHeaders.delete('x-frame-options');
          newHeaders.delete('content-security-policy');
          newHeaders.set('content-type', 'text/html; charset=utf-8');
          newHeaders.set('cache-control', 'no-cache, no-store, must-revalidate');
          return new Response(text, {
            status: resp.status,
            headers: newHeaders,
          });
        }

        const newHeaders = new Headers(resp.headers);
        newHeaders.delete('x-frame-options');
        newHeaders.delete('content-security-policy');
        return new Response(resp.body, {
          status: resp.status,
          headers: newHeaders,
        });
      } catch (err) {
        return new Response('Proxy Error: ' + err.message, { status: 502 });
      }
    }

    return env.ASSETS.fetch(request);
  },
};
