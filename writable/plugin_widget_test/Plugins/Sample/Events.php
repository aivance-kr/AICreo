<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use Plugins\Sample\Libraries\NoteAddedListener;

// 구독은 이 파일에서만 한다. 플러그인이 활성일 때만 로드되므로
// 비활성이면 구독 자체가 존재하지 않는다 (docs/plugin-events.md).
Events::on('sample.note_added', [NoteAddedListener::class, 'handle']);
