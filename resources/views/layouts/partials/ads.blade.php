{{-- Yandex ads (RTB). Same blocks as before, but started once the page has loaded and the browser is idle
     (or on the first interaction), so the ad scripts cannot slow down first paint or input on phones. --}}
<script>
    (function () {
        var started = false;

        function start() {
            if (started) return;
            started = true;
            window.yaContextCb = window.yaContextCb || [];

            var script = document.createElement('script');
            script.src = 'https://yandex.ru/ads/system/context.js';
            script.async = true;
            document.head.appendChild(script);

            window.yaContextCb.push(function () {
                Ya.Context.AdvManager.render({ blockId: @json(config('kalta.ads.top_block')), type: 'topAd' });
            });
            window.yaContextCb.push(function () {
                Ya.Context.AdvManager.render({ blockId: @json(config('kalta.ads.floor_block')), type: 'floorAd', platform: 'desktop' });
            });
        }

        function later() {
            if ('requestIdleCallback' in window) requestIdleCallback(start, { timeout: 3000 });
            else setTimeout(start, 2000);
        }

        if (document.readyState === 'complete') later();
        else window.addEventListener('load', later);

        ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (name) {
            window.addEventListener(name, start, { once: true, passive: true });
        });
    })();
</script>
