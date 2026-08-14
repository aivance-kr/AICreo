<style>
  /*
   * 에러 페이지 스타일.
   *
   * 색·타이포는 /css/tokens.css 를 따른다. 다만 에러 페이지는 무언가 이미
   * 잘못된 상태에서 뜨므로, 토큰이 실리지 않았을 때도 읽히도록 var() 마다
   * 대체값을 둔다 — 여기서만 허용하는 예외다.
   */
  *{ box-sizing: border-box }
  html, body { height: 100% }

  body {
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surface-body, #f1f3f5);
    color: var(--text-body, #333);
    font-family: var(--font-sans, 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif);
    -webkit-font-smoothing: antialiased;
  }

  .error-page { width: 100%; padding: 24px }

  .error-card {
    max-width: 520px;
    margin: 0 auto;
    padding: 40px 32px;
    background: var(--surface-raised, #fff);
    border: 1px solid var(--border-subtle, #e9ecef);
    border-radius: .75rem;
    box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075);
    text-align: center;
  }

  .error-code {
    margin: 0;
    font-size: 3rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--text-muted, #6c757d);
    font-variant-numeric: tabular-nums;
  }

  .error-heading {
    margin: .5rem 0 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.3;
    color: var(--text-strong, #212529);
  }

  .error-message {
    margin: .75rem 0 0;
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text-body, #333);
    overflow-wrap: anywhere;
  }

  .error-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .5rem;
    margin-top: 2rem;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: .5rem 1.25rem;
    border-radius: .375rem;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: 1rem;
    line-height: 1.5;
    text-decoration: none;
    cursor: pointer;
  }

  .btn-primary {
    background: var(--primary, #0d6efd);
    color: #fff;
  }
  .btn-primary:hover { background: #0b5ed7 }

  .btn-outline {
    background: transparent;
    border-color: var(--text-muted, #6c757d);
    color: var(--text-muted, #6c757d);
  }
  .btn-outline:hover {
    background: var(--text-muted, #6c757d);
    color: #fff;
  }

  /* tokens.css 가 실리지 않았을 때를 대비한 초점 표시 보강 */
  .btn:focus-visible {
    outline: 3px solid var(--focus-ring-color, #0a58ca);
    outline-offset: 2px;
  }
</style>
