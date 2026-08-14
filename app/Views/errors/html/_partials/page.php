<?php
/**
 * 공용 에러 페이지 골격.
 *
 * 호출부(error_XXX.php)가 $errCode, $errHeading, $errMessage 를 정의한 뒤 include 한다.
 *
 * 이 제품은 재판매되는 템플릿이라 에러 페이지도 중립을 유지한다 — 제작 도구의
 * 이름이나 로고를 넣지 않는다(DESIGN.md «백지»). 클라이언트 사이트의 정체성은
 * 테마가 싣고, 코어는 구조만 보장한다.
 *
 * 설정(site_name 등)을 읽지 않는 것도 의도적이다. DB 장애로 난 500 에서
 * 에러 페이지까지 함께 무너지면 안 된다.
 */
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc((string) $errCode) ?> — <?= esc($errHeading) ?></title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="/css/tokens.css">
  <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
  <main class="error-page">
    <div class="error-card">
      <p class="error-code"><?= esc((string) $errCode) ?></p>
      <h1 class="error-heading"><?= esc($errHeading) ?></h1>
      <p class="error-message"><?= esc($errMessage) ?></p>

      <div class="error-actions">
        <a href="/" class="btn btn-primary">홈으로 가기</a>
        <button type="button" class="btn btn-outline" id="error-back" hidden>이전 페이지로</button>
      </div>
    </div>
  </main>

  <script>
    // 돌아갈 기록이 있을 때만 버튼을 보여준다 — 눌러도 아무 일 없는 컨트롤을 두지 않는다.
    if (history.length > 1) {
      var back = document.getElementById('error-back');
      back.hidden = false;
      back.addEventListener('click', function () { history.back(); });
    }
  </script>
</body>
</html>
