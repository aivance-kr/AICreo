/* 글쓰기·수정 화면 임시저장: 제목·태그·본문을 localStorage 에 주기적으로 저장하고, 재진입 시 복원 여부를 묻는다. */
(function () {
    'use strict';

    var INTERVAL_MS = 5000;

    // localStorage 는 차단·용량 초과 등으로 언제든 실패할 수 있어 모든 접근을 감싼다
    function readDraft(key) {
        try {
            var raw = window.localStorage.getItem(key);
            var draft = raw ? JSON.parse(raw) : null;
            return draft && typeof draft === 'object' ? draft : null;
        } catch (e) {
            return null;
        }
    }

    function writeDraft(key, draft) {
        try {
            window.localStorage.setItem(key, JSON.stringify(draft));
        } catch (e) { /* 저장 실패는 조용히 무시 */ }
    }

    function removeDraft(key) {
        try {
            window.localStorage.removeItem(key);
        } catch (e) { /* 무시 */ }
    }

    /**
     * @param {{form: HTMLFormElement, notice: HTMLElement, key: string, isEdit: boolean,
     *          getContent: function(): string, setContent: function(string)}} options
     * @return {{discard: function()}}
     */
    window.PostAutosave = function (options) {
        var form = options.form;
        var title = form.querySelector('[name="title"]');
        var tags = form.querySelector('[name="tags"]');
        if (!title || !tags) {
            return { discard: function () {} };
        }

        function snapshot() {
            return { title: title.value, tags: tags.value, content: options.getContent() };
        }

        function same(a, b) {
            return a.title === b.title && a.tags === b.tags && a.content === b.content;
        }

        // 화면이 처음 그려진 값(신규=빈 값, 수정=서버 값, 검증 실패 후=이전 입력)을 기준으로 삼는다
        var baseline = snapshot();
        var lastSaved = baseline;
        var paused = false;

        function save() {
            if (paused) {
                return;
            }
            var current = snapshot();
            if (same(current, lastSaved)) {
                return;
            }
            lastSaved = current;
            if (same(current, baseline)) {
                removeDraft(options.key);
                return;
            }
            writeDraft(options.key, { title: current.title, tags: current.tags, content: current.content, saved_at: Date.now() });
        }

        function resume() {
            options.notice.hidden = true;
            options.notice.textContent = '';
            paused = false;
        }

        function offerRestore(draft) {
            var when = draft.saved_at ? new Date(draft.saved_at).toLocaleString('ko-KR') : '';
            var message = options.isEdit
                ? '서버에 저장된 글과 다른 임시본이 있습니다'
                : '작성 중이던 임시본이 있습니다';
            var text = document.createElement('span');
            text.textContent = message + (when ? ' (' + when + ')' : '') + '. 어떻게 할까요?';

            var restore = document.createElement('button');
            restore.type = 'button';
            restore.className = 'btn btn-primary btn-sm ms-2';
            restore.textContent = '임시본 복원';
            restore.addEventListener('click', function () {
                title.value = draft.title || '';
                tags.value = draft.tags || '';
                options.setContent(draft.content || '');
                resume();
            });

            var discard = document.createElement('button');
            discard.type = 'button';
            discard.className = 'btn btn-outline-secondary btn-sm ms-1';
            discard.textContent = options.isEdit ? '서버 값 유지' : '버리기';
            discard.addEventListener('click', function () {
                removeDraft(options.key);
                resume();
            });

            options.notice.textContent = '';
            options.notice.appendChild(text);
            options.notice.appendChild(restore);
            options.notice.appendChild(discard);
            options.notice.hidden = false;
            paused = true;
        }

        var stored = readDraft(options.key);
        var draft = stored ? { title: String(stored.title || ''), tags: String(stored.tags || ''), content: String(stored.content || ''), saved_at: stored.saved_at } : null;
        if (draft && !same(draft, baseline)) {
            offerRestore(draft);
        } else if (draft) {
            removeDraft(options.key);
        }

        window.setInterval(save, INTERVAL_MS);
        // 탭을 닫거나 숨기는 순간에도 한 번 더 저장
        window.addEventListener('pagehide', save);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                save();
            }
        });

        // 제출 성공 직전에 호출한다 — 임시본을 지우고 이후 저장(pagehide 등)을 막는다
        return {
            discard: function () {
                paused = true;
                removeDraft(options.key);
            },
        };
    };
})();
