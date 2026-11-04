<?php

declare(strict_types=1);

// 뉴스레터 — 구독 화면 메시지와 메일 문구. 키는 app/Language/en/Newsletter.php 와 같아야 한다.
return [
    'privacyNotice'      => '수집 항목은 이메일 주소 하나이며, 새 글 알림 발송에만 쓰고 해지하면 더 이상 발송하지 않습니다. 구독 확인 전에는 메일을 보내지 않습니다.',
    'confirmationSent'   => '확인 메일을 보냈습니다. 메일의 링크를 눌러야 구독이 완료됩니다.',
    'invalidEmail'       => '올바른 이메일 주소를 입력해 주세요.',
    'tooManyRequests'    => '요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.',
    'confirmOkTitle'     => '구독이 완료되었습니다',
    'confirmOkMessage'   => '이제 새 글이 공개되면 메일로 알려 드립니다. 언제든 메일 하단의 링크로 해지할 수 있습니다.',
    'invalidLinkTitle'   => '유효하지 않은 링크입니다',
    'confirmFailMessage' => '이미 해지했거나 만료된 확인 링크입니다. 구독 폼에서 다시 신청해 주세요.',
    'unsubOkTitle'       => '구독을 해지했습니다',
    'unsubOkMessage'     => '더 이상 뉴스레터를 보내지 않습니다.',
    'unsubFailMessage'   => '해지 링크를 확인해 주세요.',
    'pageTitle'          => '뉴스레터',
    'pageDesc'           => '뉴스레터 구독',
    'mailConfirmIntro'   => '{0} 새 글 알림 구독 신청을 받았습니다.',
    'mailConfirmGuide'   => '아래 링크를 눌러야 구독이 완료됩니다. 신청하지 않으셨다면 이 메일을 무시하세요.',
    'mailConfirmLink'    => '구독 확인하기',
    'mailConfirmSubject' => '[{0}] 구독 확인 메일',
    'mailReadPost'       => '글 읽기',
    'mailUnsubscribe'    => '구독 해지',
];
