// 본문의 코드블록(TinyMCE codesample: <pre class="language-xxx">)이 있을 때만 Prism 을 불러와 색을 입힌다
(function () {
    var blocks = document.querySelectorAll('pre[class*="language-"]');
    if (!blocks.length) return;

    // 본문은 사용자 입력이므로 Prism 자동 로더가 읽는 속성(data-dependencies 등)과
    // 언어 외 클래스를 제거해, 불러올 스크립트 파일명이 본문 내용에 좌우되지 않게 한다
    blocks.forEach(function (pre) {
        var lang = (pre.className.match(/(?:^|\s)language-[a-z0-9+#-]+(?=\s|$)/i) || [''])[0].trim();
        [pre].concat(Array.prototype.slice.call(pre.querySelectorAll('code'))).forEach(function (el) {
            Array.prototype.slice.call(el.attributes).forEach(function (attr) {
                if (attr.name !== 'class') el.removeAttribute(attr.name);
            });
            el.className = lang;
        });
    });

    var base = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/';
    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = base + 'themes/prism.min.css';
    document.head.appendChild(css);

    function load(src, done) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = done;
        document.head.appendChild(s);
    }

    window.Prism = window.Prism || {};
    window.Prism.manual = true;
    load(base + 'components/prism-core.min.js', function () {
        load(base + 'plugins/autoloader/prism-autoloader.min.js', function () {
            window.Prism.plugins.autoloader.languages_path = base + 'components/';
            window.Prism.highlightAll();
        });
    });
})();
