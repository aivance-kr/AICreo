# Theme Design Brief: fashion (패션 매거진)

> 이 테마(`app/Views/themes/fashion/`, `public/themes/fashion/`) 전용 디자인 메모. 코어 시스템은 루트 [`/DESIGN.md`](../../../../DESIGN.md).
> 애드센스 «블로그형» 테마 계열(`BLOG_THEMES`)에 속한다. 구조는 `quiet` 과 같고 접두사만 `fm-`.

- **성격**: 흑백 에디토리얼. 화보 잡지 표지·지면 같은 큰 이미지와 세리프 제목.
- **색**: 흰 바탕 + 검정 `#0d0d0d`, 포인트 딥 레드 `#b0122a`(흰 배경 6.7:1). 포인트는 상호작용·카테고리 라벨에만.
- **글꼴**: 제목·마스트헤드 Playfair Display(+ Noto Serif KR/나눔명조 폴백), 본문 Pretendard.
- **레이아웃**: 중앙 정렬 마스트헤드(날짜 띠 + 이중선) → 가운데 정렬 내비 → 첫 글 «커버 스토리»(전체 폭, 좌 이미지·우 텍스트) → 4:5 이미지 카드 3열 그리드 → 검정 푸터.
- **홈**: 첫 글만 커버로 키우는 것은 CSS(`.fm-list > .fm-item:first-child`)로 처리 — 마크업은 모든 글이 같다. 호버 시 이미지가 흑백으로 바뀐다.
- 광고 슬롯 규칙·접근성 계약은 `quiet` DESIGN.md 와 동일(`ad_unit.php` 공유, skip-link·`<main id="main-content">` 유지).

## Unresolved

- 썸네일 이미지 미제작. 썸네일 없는 글은 검정 그라데이션 블록으로 대체한다.
- 날짜 띠의 «The Edit» 문구는 고정 영문(장식). 다국어 문구로 옮길지는 미정.
