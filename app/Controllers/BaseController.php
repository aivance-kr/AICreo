<?php

namespace App\Controllers;

use App\Models\BannerModel;
use App\Models\BoardCategoryModel;
use App\Models\BoardModel;
use App\Models\InquiryModel;
use App\Models\MenuModel;
use App\Models\PopupModel;
use App\Models\SettingModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class BaseController extends Controller
{
    /**
     * @var array<string, mixed>
     */
    protected array $viewData = [];

    public function initController(
        RequestInterface $request,
        ResponseInterface $response,
        LoggerInterface $logger,
    ): void {
        parent::initController($request, $response, $logger);

        // 전역 사이트 설정 (캐시됨)
        $settings = (new SettingModel())->getAllAsMap();

        // 전역 네비게이션 메뉴 (캐시됨)
        $menus = (new MenuModel())->getTree();

        $boardCategories = [];
        if (($settings['active_theme'] ?? 'default') === 'blog') {
            $categoryModel = new BoardCategoryModel();

            foreach ((new BoardModel())->getActiveBoards() as $board) {
                $boardCategories[$board['slug']] = $categoryModel->getByBoard((int) $board['id']);
            }
        }

        // 로그인 정보
        $authUser = [
            'id'       => session()->get('user_id'),
            'nickname' => session()->get('user_nickname'),
            'role'     => session()->get('user_role') ?? 'guest',
            'loggedIn' => (bool) session()->get('user_id'),
        ];

        // 관리자용: 미읽음 문의 수
        $unreadInquiries = 0;
        if ($authUser['role'] === 'admin') {
            $unreadInquiries = (new InquiryModel())->getUnreadCount();
        }

        $isAdmin = str_starts_with(uri_string(), 'admin');

        $subLeftBanners = $isAdmin
            ? []
            : (new BannerModel())->getActiveByPosition('sub_left');

        $activePopups = $isAdmin
            ? []
            : (new PopupModel())->getActiveForPage(uri_string());

        // jsonLd 기본값을 항상 [] 로 명시 (Config\View::$saveData=true 공유 렌더러 누출 방지)
        $this->viewData = ['settings' => $settings, 'menus' => $menus, 'boardCategories' => $boardCategories, 'authUser' => $authUser, 'unreadInquiries' => $unreadInquiries, 'subLeftBanners' => $subLeftBanners, 'activePopups' => $activePopups, 'jsonLd' => []];
    }

    protected function getUserRole(): string
    {
        return session()->get('user_role') ?? 'guest';
    }

    /**
     * guest | member | admin 권한 체크
     */
    protected function checkPermission(string $required): bool
    {
        $role = $this->getUserRole();

        return match ($required) {
            'guest'  => true,
            'member' => in_array($role, ['member', 'admin'], true),
            'admin'  => $role === 'admin',
            default  => false,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): string
    {
        return view($view, array_merge($this->viewData, $data));
    }
}
