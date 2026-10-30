export async function onRequest(context) {
  const url = new URL(context.request.url);
  const upstreamUrl = new URL(url.pathname + url.search, 'https://lopal.dat881.com');

  const modifiedHeaders = new Headers(context.request.headers);
  modifiedHeaders.set(
    'User-Agent',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
  );
  modifiedHeaders.set('Referer', 'https://lopal.dat881.com/intell/dir40pc/lopalenApp');

  try {
    const response = await fetch(upstreamUrl.toString(), {
      method: context.request.method,
      headers: modifiedHeaders,
      body: ['GET', 'HEAD'].includes(context.request.method) ? undefined : context.request.body,
      redirect: 'follow',
    });

    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('text/html')) {
      let text = await response.text();
      text = text.replace(/<base\s+href=["'][^"']*["']\s*\/?>/gi, '<base href="/intell/">');
      text = text.replace(
        'script.src = "https://map.qq.com/api/gljs?v=1.exp&key="+key+"&callback=initMap&libraries=service";',
        'script.src = "";'
      );
      text = text.replace(/dir40pc\/\//g, 'dir40pc/lopalenApp/');
      text = text.replace(/"dir40pc\/";/g, '"dir40pc/lopalenApp";');
      text = text.replace(/'dir40pc\/';/g, "'dir40pc/lopalenApp';");

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

      return new Response(text, {
        status: response.status,
        headers: response.headers,
      });
    }

    return response;
  } catch (err) {
    return new Response('Upstream error: ' + err.message, { status: 502 });
  }
}
