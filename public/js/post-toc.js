/* 게시글 상세: 본문 제목(h2~h4)으로 목차를 자동 생성하고, 읽기 진행바를 갱신한다. */
(function () {
    'use strict';

    var content = document.querySelector('.board-post-content');
    if (!content) {
        return;
    }

    // 읽기 진행바 — 본문 영역을 지난 비율
    var bar = document.querySelector('.post-progress__bar');
    var progress = document.querySelector('.post-progress');
    if (bar && progress) {
        var update = function () {
            var rect = content.getBoundingClientRect();
            var total = rect.height - window.innerHeight * 0.5;
            var ratio = total > 0 ? Math.min(1, Math.max(0, -rect.top / total)) : 1;
            var pct = Math.round(ratio * 100);
            bar.style.width = pct + '%';
            progress.setAttribute('aria-valuenow', String(pct));
        };
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    // 목차 — 제목이 2개 미만이면 만들지 않는다
    var headings = Array.prototype.slice.call(content.querySelectorAll('h2, h3, h4'))
        .filter(function (h) { return h.textContent.trim() !== ''; });
    if (headings.length < 2) {
        return;
    }

    var nav = document.createElement('nav');
    nav.className = 'post-toc';
    nav.setAttribute('aria-label', '목차');
    var details = document.createElement('details');
    details.open = window.matchMedia('(min-width: 1400px)').matches;
    var summary = document.createElement('summary');
    summary.textContent = '목차';
    details.appendChild(summary);
    var list = document.createElement('ol');
    var links = [];

    headings.forEach(function (h, i) {
        if (!h.id) {
            h.id = 'toc-' + (i + 1);
        }
        var li = document.createElement('li');
        li.className = 'post-toc__' + h.tagName.toLowerCase();
        var a = document.createElement('a');
        a.href = '#' + h.id;
        a.textContent = h.textContent.trim();
        li.appendChild(a);
        list.appendChild(li);
        links.push(a);
    });
    details.appendChild(list);
    nav.appendChild(details);
    content.parentNode.insertBefore(nav, content);

    // 현재 읽는 제목 강조
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                var idx = headings.indexOf(entry.target);
                links.forEach(function (a, n) {
                    if (n === idx) {
                        a.setAttribute('aria-current', 'true');
                    } else {
                        a.removeAttribute('aria-current');
                    }
                });
            });
        }, { rootMargin: '0px 0px -70% 0px' });
        headings.forEach(function (h) { observer.observe(h); });
    }
})();
