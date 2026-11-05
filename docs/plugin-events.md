# 플러그인 이벤트 규약

플러그인끼리는 서로의 클래스를 `use` 하지 않는다. 통신은 CI4 `Events` 로만 한다
(`docs/plugin-architecture.md` §2.4). 출고 예시는 `app/Plugins/Sample` 이다.

## 이벤트 이름

- 형식: `{plugin_key}.{과거형 동사}` — 예: `quote.accepted`, `sample.note_added`
- 명령형·진행형 금지. 이벤트는 요청이 아니라 **이미 일어난 사실의 통지**다.

## 페이로드

- 배열 하나, 키는 `snake_case`.
- 식별자와 구독자가 판단하는 데 필요한 값만 담는다 (`['note_id' => 12]`). 모델 전체·민감 값은 넣지 않는다.
- 발행자는 키를 **삭제하거나 의미를 바꾸지 않는다**. 추가만 허용한다.

## 발행

```php
use CodeIgniter\Events\Events;

Events::trigger('sample.note_added', ['note_id' => $id]);
```

발행은 쓰기가 성공한 뒤에 한다. 구독자가 없어도(플러그인 비활성) 발행은 아무 일도 하지 않는다.

## 구독

- 플러그인 루트의 `Events.php` 에서만 구독한다.
- `Events.php` 는 플러그인이 **활성일 때만** 코어 `Config/Events.php` 의 `loadEvents()` 가 읽는다.
  비활성 플러그인의 구독은 존재하지 않는다.

```php
Events::on('sample.note_added', [NoteAddedListener::class, 'handle']);
```

## 구독자 책임

- 구독자는 **놓쳐도 되는 후속 작업**만 맡는다. 반드시 실행돼야 하는 후속은 발행자 책임이거나 큐로 보낸다.
- 구독자에서 예외가 나도 발행자의 쓰기를 되돌리지 않도록 가볍게 유지한다.

## `requires.plugins` 와의 관계

`requires.plugins` 는 "없으면 동작하지 않음" 선언이다. 이벤트를 듣기만 하는 관계는 넣지 않는다.
