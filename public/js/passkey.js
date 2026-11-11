// 패스키(WebAuthn) 등록·로그인 — 브라우저의 JSON 변환 API(parse*FromJSON, toJSON)를 쓴다.
// CSRF 토큰은 요청마다 바뀌므로 서버가 응답에 다음 요청용 토큰(csrf)을 함께 내려준다.
(function () {
    'use strict';

    function supported() {
        return !!(window.PublicKeyCredential
            && PublicKeyCredential.parseCreationOptionsFromJSON
            && PublicKeyCredential.parseRequestOptionsFromJSON);
    }

    async function post(url, csrf, body, json) {
        var headers = { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
        if (json) { headers['Content-Type'] = 'application/json'; }

        var res = await fetch(url, { method: 'POST', headers: headers, body: body, credentials: 'same-origin' });
        var data = {};
        try { data = await res.json(); } catch (e) { /* 본문 없음 */ }
        if (!res.ok) { throw new Error(data.error || 'request failed'); }

        return data;
    }

    // 로그인: 이메일 없이 기기에 저장된 패스키로 인증하고, 서버가 알려준 주소로 이동한다.
    async function login(csrf) {
        var start = await post('/auth/passkey/options', csrf);
        var credential = await navigator.credentials.get({
            publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(start.options),
        });
        var done = await post('/auth/passkey/verify', start.csrf, JSON.stringify(credential.toJSON()), true);
        window.location.href = done.redirect;
    }

    // 등록: form 의 비밀번호·인증 코드로 재확인한 뒤 새 패스키를 만들어 저장한다.
    async function register(form, csrf) {
        var start = await post('/admin/security/passkeys/options', csrf, new FormData(form));
        var credential = await navigator.credentials.create({
            publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(start.options),
        });
        await post('/admin/security/passkeys', start.csrf, JSON.stringify({
            name: form.elements.name.value,
            credential: credential.toJSON(),
        }), true);
        window.location.reload();
    }

    window.Passkey = { supported: supported, login: login, register: register };
})();
