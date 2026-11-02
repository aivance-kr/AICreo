<?php

declare(strict_types=1);

namespace App\Libraries\Seo;

/**
 * 기본 홈(AICreo 소개) 의 GEO 용 정답형 사실 문구.
 * 화면 FAQ · FAQPage JSON-LD · llms.txt 가 같은 원문을 쓰도록 한 곳에 둔다.
 * 저장소에 없는 수치·고객 사례는 넣지 않는다.
 */
final class HomeIntro
{
    public const DESCRIPTION = 'AICreo는 1인 웹에이전시가 클라이언트 홈페이지를 빠르게 납품하도록 만든 CodeIgniter 4 기반 보일러플레이트입니다. 게시판 CMS, 동적 페이지, 문의, 회원, SEO·GEO를 기본 제공합니다.';

    /**
     * @return list<array{q: string, a: string}>
     */
    public static function faqs(): array
    {
        return [
            ['q' => 'AICreo는 무엇인가요?', 'a' => 'AICreo는 PHP 8.5와 CodeIgniter 4로 만든 홈페이지 보일러플레이트(게시판 CMS·사이트 빌더)입니다. 1인 웹에이전시가 코어는 재사용하고 테마만 바꿔 클라이언트 사이트를 납품하도록 설계했습니다.'],
            ['q' => '어떤 기능을 기본으로 제공하나요?', 'a' => '게시판 CMS, 동적 페이지와 2단계 메뉴, 문의폼(이메일·Slack 알림), 배너·팝업·광고 슬롯, 회원·소셜 로그인(Google·Naver·Kakao), 블로그 기능, 접속 통계와 보안, SEO·GEO(sitemap, robots, llms.txt, JSON-LD)를 제공합니다.'],
            ['q' => '납품에는 얼마나 걸리나요?', 'a' => '단순 홈페이지는 3~5일, 중형 사이트는 1~2주를 목표로 설계했습니다. 저장소 복제, 테마 선택, 관리자 설정, 배포의 네 단계로 진행합니다.'],
            ['q' => '라이선스와 접근성 기준은 무엇인가요?', 'a' => 'MIT 라이선스로 공개되어 있으며, KWCAG 2.2 AA 접근성 기준(대체 텍스트, 키보드 조작, 명도 대비 4.5:1)을 코어와 기본 테마에서 지킵니다.'],
        ];
    }
}
