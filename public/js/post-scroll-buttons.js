/* 게시글 상세: 맨위/맨아래로 이동하는 플로팅 버튼. 본문 길이가 화면보다 짧으면 숨긴다. */
(function () {
    'use strict';

    var wrap = document.querySelector('.post-scroll-buttons');
    if (!wrap) {
        return;
    }
    var topBtn = wrap.querySelector('.post-scroll-buttons__btn--top');
    var bottomBtn = wrap.querySelector('.post-scroll-buttons__btn--bottom');

    var update = function () {
        var scrollable = document.documentElement.scrollHeight - window.innerHeight > 200;
        if (!scrollable) {
            if (topBtn) { topBtn.classList.remove('is-visible'); }
            if (bottomBtn) { bottomBtn.classList.remove('is-visible'); }
            return;
        }
        var atTop = window.scrollY < 200;
        var atBottom = window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 200;
        if (topBtn) {
            topBtn.classList.toggle('is-visible', !atTop);
        }
        if (bottomBtn) {
            bottomBtn.classList.toggle('is-visible', !atBottom);
        }
    };

    if (topBtn) {
        topBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    if (bottomBtn) {
        bottomBtn.addEventListener('click', function () {
            window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
        });
    }

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();
