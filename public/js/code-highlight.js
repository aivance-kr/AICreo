// 본문의 코드블록(TinyMCE codesample: <pre class="language-xxx">)이 있을 때만 Prism 을 불러와 색을 입힌다
(function () {
    if (!document.querySelector('pre[class*="language-"]')) return;

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
