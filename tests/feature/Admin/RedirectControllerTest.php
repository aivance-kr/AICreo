<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PageVisitModel;
use App\Models\RedirectModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\AdminTestCase;

/**
 * @internal
 */
final class RedirectControllerTest extends AdminTestCase
{
    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function payload(string $old, string $new, array $extra = []): array
    {
        return $extra + ['old_path' => $old, 'new_path' => $new, 'status_code' => '301', 'is_active' => '1'];
    }

    public function testListShowsRedirectsWithHitCount(): void
    {
        $model = new RedirectModel();
        $model->insert(['old_path' => '/old', 'new_path' => '/new', 'status_code' => 301, 'hit_count' => 7]);

        $result = $this->withSession($this->adminSession)->get('admin/redirects');

        $result->assertStatus(200);
        $result->assertSee('/old');
        $result->assertSee('/new');
    }

    public function testMemberCannotAccess(): void
    {
        $result = $this->withSession($this->memberSession)->get('admin/redirects');

        $this->assertNotSame(200, $result->response()->getStatusCode());
    }

    public function testStoreCreatesRedirectAndItRedirectsImmediately(): void
    {
        $this->withSession($this->adminSession)->post('admin/redirects', $this->payload('/gone', '/here'));

        $row = (new RedirectModel())->findByOldPath('/gone');
        $this->assertNotNull($row);
        $this->assertSame('/here', $row['new_path']);

        $result = $this->get('gone');
        $result->assertRedirectTo('/here');
        $this->assertSame(1, (int) (new RedirectModel())->findByOldPath('/gone')['hit_count']);
    }

    public function testInactiveRedirectDoesNotRedirectOrCount(): void
    {
        (new RedirectModel())->insert(['old_path' => '/off', 'new_path' => '/x', 'status_code' => 301, 'is_active' => 0]);

        try {
            $this->assertFalse($this->get('off')->isRedirect());
        } catch (PageNotFoundException) {
            // 사용 안 함이면 리다이렉트하지 않고 일반 404 로 흐른다
        }

        $this->assertSame(0, (int) (new RedirectModel())->findByOldPath('/off')['hit_count']);
    }

    public function testDuplicateOldPathIsRejected(): void
    {
        (new RedirectModel())->insert(['old_path' => '/dup', 'new_path' => '/a', 'status_code' => 301]);

        $this->withSession($this->adminSession)->post('admin/redirects', $this->payload('/dup', '/b'));

        $this->assertSame(1, (new RedirectModel())->where('old_path', '/dup')->countAllResults());
        $this->assertSame('/a', (new RedirectModel())->findByOldPath('/dup')['new_path']);
    }

    public function testSelfRedirectIsRejected(): void
    {
        $this->withSession($this->adminSession)->post('admin/redirects', $this->payload('/loop', '/loop'));

        $this->assertNull((new RedirectModel())->findByOldPath('/loop'));
    }

    public function testCycleIsRejected(): void
    {
        $model = new RedirectModel();
        $model->insert(['old_path' => '/a', 'new_path' => '/b', 'status_code' => 301]);
        $model->insert(['old_path' => '/b', 'new_path' => '/c', 'status_code' => 301]);

        $this->withSession($this->adminSession)->post('admin/redirects', $this->payload('/c', '/a'));

        $this->assertNull($model->findByOldPath('/c'));
    }

    public function testUpdateCannotCreateCycle(): void
    {
        $model = new RedirectModel();
        $model->insert(['old_path' => '/a', 'new_path' => '/b', 'status_code' => 301]);
        $id = (int) $model->insert(['old_path' => '/b', 'new_path' => '/c', 'status_code' => 301]);

        $this->withSession($this->adminSession)->post("admin/redirects/{$id}", $this->payload('/b', '/a'));

        $this->assertSame('/c', $model->findByOldPath('/b')['new_path']);
    }

    public function testUpdateKeepingSameOldPathIsAllowed(): void
    {
        $model = new RedirectModel();
        $id    = (int) $model->insert(['old_path' => '/a', 'new_path' => '/b', 'status_code' => 301]);

        $this->withSession($this->adminSession)->post("admin/redirects/{$id}", $this->payload('/a', '/z', ['status_code' => '302']));

        $row = $model->findByOldPath('/a');
        $this->assertSame('/z', $row['new_path']);
        $this->assertSame(302, (int) $row['status_code']);
    }

    public function testInvalidPathsAreRejected(): void
    {
        $this->withSession($this->adminSession)->post('admin/redirects', $this->payload('no-slash', '/x'));
        $this->withSession($this->adminSession)->post('admin/redirects', $this->payload('/ok', 'javascript:alert(1)'));

        $this->assertSame(0, (new RedirectModel())->countAllResults());
    }

    public function testToggleAndDelete(): void
    {
        $model = new RedirectModel();
        $id    = (int) $model->insert(['old_path' => '/t', 'new_path' => '/u', 'status_code' => 301]);

        $this->withSession($this->adminSession)->post("admin/redirects/{$id}/toggle");
        $this->assertSame(0, (int) $model->find($id)['is_active']);

        $this->withSession($this->adminSession)->post("admin/redirects/{$id}/delete");
        $this->assertNull($model->find($id));
    }

    public function testNotFoundCandidatesListedAndRegisteredOnesHidden(): void
    {
        $visits = new PageVisitModel();
        $a      = $visits->logVisit('/missing-a', '1.1.1.1', null, null);
        $b      = $visits->logVisit('/missing-b', '1.1.1.1', null, null);
        $visits->markNotFound($a);
        $visits->markNotFound($b);
        (new RedirectModel())->insert(['old_path' => '/missing-b', 'new_path' => '/x', 'status_code' => 301]);

        $result = $this->withSession($this->adminSession)->get('admin/redirects');

        $result->assertSee('/missing-a');
        $result->assertSee('old=%2Fmissing-a');
        $result->assertDontSee('old=%2Fmissing-b');
    }
}
