/* 글 공유: 링크 복사(Clipboard API → execCommand → prompt 순 폴백), 카카오톡(키가 있을 때만 SDK 로드) */
(function () {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(area);
            ok ? resolve() : reject(new Error('copy failed'));
        });
    }

    var copyBtn = document.getElementById('share-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var label = copyBtn.querySelector('span');
            var icon = copyBtn.querySelector('i');
            copyText(copyBtn.dataset.url).then(function () {
                label.textContent = '복사됨';
                icon.className = 'bi bi-check2';
                copyBtn.classList.add('is-copied');
                setTimeout(function () {
                    label.textContent = '링크 복사';
                    icon.className = 'bi bi-link-45deg';
                    copyBtn.classList.remove('is-copied');
                }, 2000);
            }, function () {
                window.prompt('주소를 복사하세요', copyBtn.dataset.url);
            });
        });
    }

    var kakaoBtn = document.querySelector('[data-kakao-share]');
    if (!kakaoBtn) { return; }

    var sdkLoading = null;
    function loadKakao() {
        if (window.Kakao) { return Promise.resolve(window.Kakao); }
        if (!sdkLoading) {
            sdkLoading = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = 'https://t1.kakaocdn.net/kakao_js_sdk/2.7.4/kakao.min.js';
                s.crossOrigin = 'anonymous';
                s.onload = function () { resolve(window.Kakao); };
                s.onerror = function () { sdkLoading = null; reject(new Error('kakao sdk')); };
                document.head.appendChild(s);
            });
        }
        return sdkLoading;
    }

    kakaoBtn.addEventListener('click', function () {
        var url = kakaoBtn.dataset.url;
        loadKakao().then(function (Kakao) {
            if (!Kakao.isInitialized()) { Kakao.init(kakaoBtn.dataset.kakaoKey); }
            var ogImage = document.querySelector('meta[property="og:image"]');
            var content = { title: kakaoBtn.dataset.title, link: { mobileWebUrl: url, webUrl: url } };
            if (ogImage) { content.imageUrl = ogImage.content; }
            Kakao.Share.sendDefault({
                objectType: 'feed',
                content: content,
                buttons: [{ title: '글 보기', link: { mobileWebUrl: url, webUrl: url } }],
            });
        }).catch(function () {
            window.prompt('카카오톡 공유를 불러오지 못했습니다. 주소를 복사하세요', url);
        });
    });
}());
