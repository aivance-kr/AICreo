---
name: AICreo
description: 클라이언트 홈페이지를 반복 납품하기 위한 중립 그릇 — 개성은 테마가 싣고, 코어는 구조와 접근성을 보장한다
colors:
  primary: "#0d6efd"
  dark: "#1e2a38"
  danger: "#dc3545"
  surface-body: "#f1f3f5"
  surface-raised: "#ffffff"
  border-subtle: "#e9ecef"
  border-hairline: "#f0f0f0"
  text-strong: "#212529"
  text-body: "#333333"
  text-muted: "#6c757d"
  on-dark-strong: "#ffffff"
  on-dark-body: "#a8b7c7"
  on-dark-muted: "#7e93a6"
  on-dark-raised: "#2d3f54"
  focus-ring: "#0a58ca"
typography:
  display:
    fontFamily: "Noto Sans KR, -apple-system, BlinkMacSystemFont, Segoe UI, Malgun Gothic, sans-serif"
    fontSize: "3rem"
    fontWeight: 700
    lineHeight: 1.2
  headline:
    fontFamily: "{typography.display.fontFamily}"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.3
  title:
    fontFamily: "{typography.display.fontFamily}"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.4
  body:
    fontFamily: "{typography.display.fontFamily}"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "{typography.display.fontFamily}"
    fontSize: "0.7rem"
    fontWeight: 600
    letterSpacing: "0.06em"
rounded:
  sm: "0.375rem"
  md: "0.5rem"
  lg: "0.75rem"
  pill: "50rem"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "3rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-dark-strong}"
    rounded: "{rounded.sm}"
    padding: "0.375rem 0.75rem"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.on-dark-strong}"
    rounded: "{rounded.sm}"
    padding: "0.375rem 0.75rem"
  card:
    backgroundColor: "{colors.surface-raised}"
    textColor: "{colors.text-body}"
    rounded: "{rounded.lg}"
    padding: "{spacing.md}"
  input:
    backgroundColor: "{colors.surface-raised}"
    textColor: "{colors.text-body}"
    rounded: "{rounded.sm}"
    padding: "0.375rem 0.75rem"
  sidebar-nav-item:
    backgroundColor: "{colors.dark}"
    textColor: "{colors.on-dark-body}"
    padding: "0.6rem 1.2rem"
  sidebar-nav-item-active:
    backgroundColor: "{colors.on-dark-raised}"
    textColor: "{colors.on-dark-strong}"
    padding: "0.6rem 1.2rem"
---

# Design System: AICreo

## Overview

**Creative North Star: "백지 (The Blank Sheet)"**

AICreo는 자기 얼굴을 갖지 않기로 선택한 시스템이다. 이 제품은 특정 서비스가 아니라 클라이언트마다 다시 팔리는 템플릿이고, 그래서 코어가 취향을 주장하는 순간 다음 납품이 느려진다. 기본값은 의도적으로 비어 있다 — 부트스트랩 기본에 가까운 파랑, 중립 회색, 장식 없는 표면. 개성은 `public/themes/{테마명}/css/style.css`가 토큰을 덮어쓰며 싣는다.

비어 있다는 것이 아무렇게나라는 뜻은 아니다. 백지는 **구조와 접근성에 대해서는 단호하다.** 명도 대비, 초점 표시, 레이블 연결, 건너뛰기 링크는 테마가 바꿀 수 있는 취향이 아니라 코어가 보장하는 계약이다. KWCAG 2.2 준수가 납품 계약 조건이기 때문에, 색은 양보하고 대비는 양보하지 않는다.

무게중심은 관리자 화면에 있다. 이 제품의 주 사용자는 개발자가 떠난 뒤 홈페이지를 혼자 굴리는 비개발자 운영자이고, 그가 매일 보는 화면은 프론트가 아니라 `/admin`이다. 그래서 시스템의 성격은 "차분하고 또렷하게" — 눈에 띄려 하지 않되, 무엇이 눌리는지 무엇이 지금 위치인지는 즉시 읽혀야 한다.

**Key Characteristics:**
- 중립 기본값, 테마가 덮어쓰는 단일 토큰 층 (`/css/tokens.css`)
- 어두운 사이드바 / 밝은 작업 영역이라는 두 표면 체계
- 대비는 실측값으로 관리 — 토큰 주석에 비율이 박혀 있다
- 그림자는 상태와 오버레이에만, 나머지는 색조 층으로 깊이를 만든다
- 좁은 화면에서 표는 행 단위 카드로 재구성된다

## Colors

부트스트랩 기본에 가까운 저채도 팔레트다. 색으로 말하지 않고 위치와 대비로 말한다.

### Primary
- **작업 파랑** (`#0d6efd`): 주요 행동(저장·등록·문의 보내기)과 현재 위치 표시에만 쓴다. 테마가 가장 먼저 덮어쓰는 변수이며, 클라이언트 브랜드 색이 들어오는 자리다.

### Secondary
- **먹 네이비** (`#1e2a38`): 관리자 사이드바와 프론트 푸터의 바탕. 작업 영역과 탐색 영역을 가르는 경계로 기능한다. 강조가 아니라 **구획**이 목적이다.

### Tertiary
- **경고 빨강** (`#dc3545`): 삭제, 미읽음 배지, 파괴적 행동에만. 장식으로 쓰지 않는다.

### Neutral
- **작업대 회색** (`#f1f3f5`): 관리자 본문 배경. 카드가 떠 보이게 만드는 바닥.
- **종이 흰색** (`#ffffff`): 카드·입력·상단바 등 올라온 표면.
- **테두리 회색** (`#e9ecef`) / **실선 회색** (`#f0f0f0`): 구획선. 앞은 카드 경계, 뒤는 셀 사이 실낱 구분선.
- **본문 먹** (`#333333`): 기본 글자.
- **제목 먹** (`#212529`): 강조 글자.
- **보조 회색** (`#6c757d`): 부가 설명·메타 정보.
- **사이드바 글자** (`#a8b7c7`) / **사이드바 구분 레이블** (`#7e93a6`) / **사이드바 활성 바탕** (`#2d3f54`): 먹 네이비 위 전용 계열.
- **초점 파랑** (`#0a58ca`): 키보드 초점 링.

### Named Rules

**The Measured Contrast Rule.** 새 색을 토큰에 추가할 때는 **대비 비율을 계산해 주석에 적는다.** `--text-muted`(#6c757d, 4.7:1)가 밝은 표면의 하한이고 `--on-dark-muted`(#7e93a6, 4.6:1)가 어두운 표면의 하한이다. 이보다 옅은 회색은 토큰이 될 수 없다.

**The Theme Override Rule.** 색은 반드시 `var(--토큰)`으로 참조한다. 뷰나 컴포넌트에 헥사를 직접 박으면 테마 교체가 그 지점만 비껴간다. 예외는 외부 브랜드 규정 색(네이버 `#03C75A`, 카카오 `#FEE500`)뿐이다.

## Typography

**Display Font:** Noto Sans KR (fallback: -apple-system, BlinkMacSystemFont, Segoe UI, Malgun Gothic, sans-serif)
**Body Font:** 동일 — 한 서체로 전체를 운용한다.

**Character:** 한글 본문 가독성을 최우선으로 고른 단일 산세리프다. 표정을 만들지 않고 읽히기만 한다. 서체로 개성을 내는 일은 테마의 몫이다.

### Hierarchy
- **Display** (700, 3rem, 1.2): 프론트 홈 히어로의 사이트명. 페이지당 한 번.
- **Headline** (700, 1.5rem, 1.3): 동적 페이지·문의 페이지의 `h1`.
- **Title** (700, 1.25rem, 1.4): 섹션 제목, 카드 헤더.
- **Body** (400, 1rem, 1.6): 본문. 게시글 본문은 1.8까지 늘린다.
- **Label** (600, 0.7rem, letter-spacing 0.06em, 대문자): 사이드바 구획 레이블(콘텐츠/운영/설정), 카드 셀의 항목명.

### Named Rules

**The Level-Is-Meaning Rule.** 제목 태그는 **크기가 아니라 문서 구조로 고른다.** 작게 보이고 싶으면 `<h1 class="h5">`처럼 시각 크기를 클래스로 낮추되 레벨은 유지한다. 페이지마다 `h1`은 정확히 하나다.

**The 16px Touch Floor Rule.** 터치 기기(`pointer: coarse`)에서 모든 입력은 16px이다. 그 아래로 내려가면 iOS Safari가 초점 순간 화면을 강제 확대하고 되돌리지 않는다.

## Layout

부트스트랩 5 그리드 위에서 동작하며 컨테이너는 `.container`가 기본이다. 두 개의 지배적 골격이 있다.

**관리자**는 `240px` 고정 사이드바(`--sidebar-w`) + 유동 본문이다. `992px` 이상에서만 사이드바가 `position: fixed`로 붙고 본문이 그만큼 밀린다. 그 아래에서는 사이드바가 오프캔버스로 빠지고 본문 여백은 0이 된다 — 좁은 화면에서 고정 사이드바가 폭을 먹지 않게 하는 것이 이 시스템의 유일한 강한 레이아웃 규칙이다.

**프론트**는 단일 컬럼이며, 서브 좌측 배너가 있을 때만 `768px` 이상에서 사이드 슬롯(`320px`)이 붙는다.

간격 리듬은 부트스트랩 스케일(`0.25 / 0.5 / 1 / 1.5 / 3rem`)을 그대로 쓴다. 자체 스케일을 만들지 않는다 — 유틸리티 클래스와 어긋나면 유지 비용만 는다.

### Named Rules

**The Stacked Table Rule.** `768px` 미만에서 목록 표는 가로 스크롤 대신 **행 단위 카드로 쌓는다**(`.table-stack`). 각 `<td>`는 `data-label`로 항목명을 들고, 조작 버튼 칸은 `cell-actions`로 우측 정렬된다. 열이 6개를 넘는 표를 옆으로 긁게 만들면 운영자가 조작 버튼을 찾지 못한다.

## Elevation & Depth

**얕은 층 + 강한 오버레이.** 깊이는 대부분 색조로 만든다 — 작업대 회색(`#f1f3f5`) 위에 종이 흰색(`#ffffff`) 카드를 얹는 두 단계가 기본이고, 여기에 은은한 그림자가 경계를 거든다. 진짜 그림자는 화면 위로 떠오르는 것에만 허락된다.

### Shadow Vocabulary
- **표면 부양** (`box-shadow: 0 .125rem .25rem rgba(0,0,0,.075)` — 부트스트랩 `.shadow-sm`): 카드·상단바. 떠 있다는 느낌만 주고 스스로 눈에 띄지 않는다.
- **오버레이** (`box-shadow: 0 8px 32px rgba(0,0,0,.22)`): 팝업 레이어 전용. 페이지 흐름에서 완전히 벗어났음을 알리는 유일한 강한 그림자다.

### Named Rules

**The Overlay-Only Deep Shadow Rule.** `0 8px 32px` 급의 그림자는 **페이지 흐름 밖에 있는 요소에만** 쓴다. 카드·버튼·입력이 이 그림자를 쓰면 무엇이 진짜 떠 있는지 알 수 없어진다.

## Shapes

부드럽지만 둥글둥글하지 않은 모서리 언어다. 카드는 `0.75rem`, 팝업은 `8px`, 스택 카드 행은 `0.5rem`, 버튼·입력은 부트스트랩 기본 `0.375rem`을 쓴다. 알약형(`50rem`)은 배지에만 등장한다.

경계는 대부분 **1px 실선**이며 색은 `--border-subtle`(카드 경계)과 `--border-hairline`(셀 사이)로 나뉜다. 색 있는 굵은 좌측 테두리, 사선 절단, 각진 블록 그림자 같은 형태 장치는 쓰지 않는다 — 백지는 형태로도 주장하지 않는다.

## Components

전반적 성격: **차분하고 또렷하게.** 평소에는 물러나 있고, 상태가 바뀌는 순간에는 분명하게 말한다.

### Buttons
- **Shape:** 살짝 둥근 모서리 (`0.375rem`)
- **Primary:** 작업 파랑 바탕 + 흰 글자. 화면당 주요 행동 하나에만.
- **Outline / Secondary:** 목록 행의 수정·미리보기 등 반복 행동. 회색 테두리 + 본문 먹.
- **Danger:** 삭제 전용. 실행 전 무엇을 지우는지 이름을 밝히는 확인을 띄운다.
- **Hover / Focus:** 배경 채움으로 hover, 초점은 `3px` 파란 링 + `2px` 오프셋. 초점 링은 **키보드 이동에만**(`:focus-visible`) 나타난다.
- **터치 여유:** `991px` 이하에서 `.btn-sm`은 최소 높이 `40px`로 커진다.

### Cards / Containers
- **Corner Style:** `0.75rem` (`--bs-card-border-radius`)
- **Background:** 종이 흰색, 바닥은 작업대 회색
- **Shadow Strategy:** 표면 부양 그림자 (Elevation 참조). 테두리 없이 그림자만으로 경계를 만드는 것이 기본형.
- **Internal Padding:** `1rem`~`1.5rem`

### Inputs / Fields
- **Style:** 흰 바탕 + 1px 회색 테두리 + `0.375rem` 모서리
- **Label:** 모든 입력은 `for`/`id`로 연결된 레이블을 갖는다. 필수 항목은 시각적 `*`(보조기기에서 숨김) + "(필수)" 텍스트를 함께 낸다.
- **Focus:** 파란 초점 링
- **Error:** `.is-invalid` 테두리 + `.invalid-feedback` 문구를 **함께** 낸다. 색만 바꾸지 않는다.
- **Help:** `aria-describedby`로 연결된 `.form-text`

### Navigation
- **관리자 사이드바:** 먹 네이비 바탕, 글자 `#a8b7c7`. 구획 레이블은 0.7rem 대문자 `#7e93a6`. 활성 항목은 `#2d3f54` 바탕 + 흰 글자 + `aria-current="page"`.
- **프론트 GNB:** 흰 바탕 + 하단 실선. 활성 메뉴는 굵게 + 작업 파랑 + 하단 2px 인셋 밑줄.
- **모바일:** 관리자는 오프캔버스 서랍, 프론트는 부트스트랩 collapse. 두 토글 모두 접근 가능한 이름과 `aria-expanded`/`aria-controls`를 갖는다.

### 건너뛰기 링크 (시그니처)
`.skip-link`는 평소 `left: -9999px`로 화면 밖에 있다가 탭 초점을 받으면 좌상단으로 들어온다. 먹 네이비 바탕에 흰 글자, 우하단만 둥근 모서리. **레이아웃을 교체하는 테마도 이 요소를 반드시 옮겨야 한다** — 관리자 사이드바는 매 페이지 링크 15개를 반복한다.

## Do's and Don'ts

### Do:
- **Do** 색을 `var(--토큰)`으로 참조한다. 새 색이 필요하면 `tokens.css`에 대비 비율과 함께 추가한다.
- **Do** 제목 레벨을 문서 구조로 고르고, 크기는 `h1 class="h5"`처럼 클래스로 조정한다.
- **Do** 모든 입력에 `for`/`id` 레이블을 붙이고, 오류는 색과 문구를 함께 낸다.
- **Do** 6열 이상 목록 표에 `.table-stack`과 `data-label`을 적용한다.
- **Do** 아이콘만 있는 버튼에 `.visually-hidden` 텍스트나 `aria-label`을 넣는다.
- **Do** 외부 CDN 자원에 `integrity` + `crossorigin`을 붙인다.

### Don't:
- **Don't** `--text-muted`(4.7:1)보다 옅은 회색을 본문·레이블에 쓴다.
- **Don't** 어두운 배경에 부트스트랩 `.text-secondary`를 쓴다 (3.4:1로 미달). `.text-white-50`을 쓴다.
- **Don't** placeholder를 레이블 대신 쓴다.
- **Don't** 깊은 그림자(`0 8px 32px`)를 오버레이가 아닌 요소에 쓴다.
- **Don't** 색 있는 굵은 좌측 테두리, 각진 블록 그림자, 그라디언트 글자 같은 형태 장치를 코어에 넣는다 — 개성은 테마의 몫이다.
- **Don't** 터치 기기 입력을 16px 미만으로 내린다.
- **Don't** 테마 레이아웃에서 `tokens.css`·건너뛰기 링크·`main` 랜드마크를 빠뜨린다.
