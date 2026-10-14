<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\InquiryModel;
use App\Models\PostCommentModel;
use App\Models\PostModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        $postModel    = new PostModel();
        $commentModel = new PostCommentModel();
        $userModel    = new UserModel();
        $inquiryModel = new InquiryModel();

        return $this->render('admin/dashboard/index', [
            'stats' => [
                'total_posts'      => $postModel->countAllResults(),
                'total_users'      => $userModel->countAllResults(),
                'total_inquiries'  => $inquiryModel->countAllResults(),
                'unread_inquiries' => $inquiryModel->getUnreadCount(),
            ],
            'recentInquiries' => $inquiryModel->orderBy('id', 'DESC')->findAll(5),
            'recentPosts'     => $postModel
                ->select('posts.*, boards.slug as board_slug, boards.name as board_name, users.nickname as user_nickname')
                ->join('boards', 'boards.id = posts.board_id', 'left')
                ->join('users', 'users.id = posts.user_id', 'left')
                ->orderBy('posts.id', 'DESC')
                ->findAll(5),
            'recentComments' => $commentModel->getRecent(5),
        ]);
    }
}
