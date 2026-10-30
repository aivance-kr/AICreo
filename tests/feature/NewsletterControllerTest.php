<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\Front\NewsletterController;
use App\Models\SubscriberModel;
use CodeIgniter\Config\Factories;
use Tests\Support\FeatureTestCase;

/**
 * @internal
 */
final class NewsletterControllerTest extends FeatureTestCase
{
    private SubscriberModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->model = new SubscriberModel();

        // 확인 메일은 실제로 나가지 않게 닫힌 포트의 SMTP 로 보낸다(발송 실패는 컨트롤러 응답에 영향이 없다)
        $email           = config('Email');
        $email->protocol = 'smtp';
        $email->SMTPHost = '127.0.0.1';
        $email->SMTPPort = 1;
        Factories::injectMock('config', 'Email', $email);
    }

    public function testSubscribeCreatesPendingSubscriber(): void
    {
        $result = $this->post('newsletter/subscribe', ['email' => 'Reader@Example.com']);

        $result->assertStatus(302);
        $row = $this->model->where('email', 'reader@example.com')->first();
        $this->assertNotNull($row);
        $this->assertSame(SubscriberModel::STATUS_PENDING, $row['status']);
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->post('newsletter/subscribe', ['email' => 'not-an-email']);

        $this->assertSame(0, $this->model->countAllResults());
    }

    public function testHoneypotFilledSubscribesNobody(): void
    {
        $result = $this->post('newsletter/subscribe', ['email' => 'bot@example.com', NewsletterController::HONEYPOT_FIELD => 'http://spam']);

        $result->assertStatus(302);
        $this->assertSame(0, $this->model->countAllResults());
    }

    public function testRepeatedRequestsForSameEmailAreRateLimited(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->post('newsletter/subscribe', ['email' => 'flood@example.com']);
        }

        $this->assertSame(1, $this->model->where('email', 'flood@example.com')->countAllResults());
        $token = $this->model->where('email', 'flood@example.com')->first()['confirm_token'];
        // 3번째부터는 막혀 확인 토큰이 더는 바뀌지 않는다
        $this->post('newsletter/subscribe', ['email' => 'flood@example.com']);
        $this->assertSame($token, $this->model->where('email', 'flood@example.com')->first()['confirm_token']);
    }

    public function testConfirmLinkActivatesSubscription(): void
    {
        $row = $this->model->subscribe('a@example.com');

        $result = $this->get('newsletter/confirm/' . $row['confirm_token']);

        $result->assertStatus(200);
        $result->assertSee('구독이 완료되었습니다');
        $this->assertSame(SubscriberModel::STATUS_CONFIRMED, $this->model->find($row['id'])['status']);
    }

    public function testConfirmWithUnknownTokenShowsError(): void
    {
        $result = $this->get('newsletter/confirm/nope');

        $result->assertSee('유효하지 않은 링크입니다');
    }

    public function testUnsubscribeLinkIsOneClickAndAcceptsMailClientPost(): void
    {
        $row = $this->model->subscribe('a@example.com');
        $this->model->confirm($row['confirm_token']);

        $this->get('newsletter/unsubscribe/' . $row['unsubscribe_token'])->assertSee('구독을 해지했습니다');
        $this->assertSame(SubscriberModel::STATUS_UNSUBSCRIBED, $this->model->find($row['id'])['status']);

        $this->post('newsletter/unsubscribe/' . $row['unsubscribe_token'], ['List-Unsubscribe' => 'One-Click'])->assertStatus(200);
    }
}
