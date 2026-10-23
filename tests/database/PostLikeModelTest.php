<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\PostLikeModel;
use Tests\Support\DatabaseTestCase;

/**
 * @internal
 */
final class PostLikeModelTest extends DatabaseTestCase
{
    private PostLikeModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PostLikeModel();
    }

    public function testToggleAddsThenRemovesLike(): void
    {
        $this->assertTrue($this->model->toggle(1, 'ip:a'));
        $this->assertSame(1, $this->model->countByPost(1));
        $this->assertTrue($this->model->hasLiked(1, 'ip:a'));

        $this->assertFalse($this->model->toggle(1, 'ip:a'));
        $this->assertSame(0, $this->model->countByPost(1));
        $this->assertFalse($this->model->hasLiked(1, 'ip:a'));
    }

    public function testCountsAreSeparatedByPostAndVoter(): void
    {
        $this->model->toggle(1, 'ip:a');
        $this->model->toggle(1, 'ip:b');
        $this->model->toggle(2, 'ip:a');

        $this->assertSame(2, $this->model->countByPost(1));
        $this->assertSame(1, $this->model->countByPost(2));
    }
}
