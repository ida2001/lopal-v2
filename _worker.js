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

          const injectHead = `
<style id="lopal-cf-suppress">
  #vehicleTipsModal, #oilTipsArea { display: none !important; }
</style>
<script>
  window.alert = function(){};
  try {
    localStorage.setItem("lopalenAppRemember", "lopalenAppRemember");
    localStorage.setItem("lopalenAppRememberTime", String(Date.now()));
  } catch(e){}
</script>`;

          const injectBody = `
<script>
(function() {
  function dismiss() {
    try {
      localStorage.setItem("lopalenAppRemember", "lopalenAppRemember");
      localStorage.setItem("lopalenAppRememberTime", String(Date.now()));
    } catch(e){}
    if (window.jQuery) {
      var $ = window.jQuery;
      try { $("#consentTips").trigger("click"); } catch(e){}
      try { $("#vehicleTipsModal, #oilTipsArea").modal("hide").hide().removeClass("in"); } catch(e){}
      $(".modal-backdrop").remove();
      $("body").removeClass("modal-open").css({ overflow: "auto", paddingRight: "0" });
    }
  }
  dismiss();
  document.addEventListener("DOMContentLoaded", dismiss);
  window.addEventListener("load", dismiss);
  setTimeout(dismiss, 100);
  setTimeout(dismiss, 500);

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

