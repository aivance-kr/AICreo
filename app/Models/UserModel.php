<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'username', 'email', 'password', 'nickname', 'role', 'is_active', 'last_login',
        'social_provider', 'social_id', 'social_token', 'avatar',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->where('is_active', 1)->first();
    }

    public function updateLastLogin(int $id): void
    {
        $this->update($id, ['last_login' => date('Y-m-d H:i:s')]);
    }

    public function withdraw(int $id): void
    {
        $suffix = bin2hex(random_bytes(8));

        $this->update($id, [
            'username'        => 'withdrawn-' . $id . '-' . $suffix,
            'email'           => 'withdrawn-' . $id . '-' . $suffix . '@deleted.invalid',
            'password'        => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'nickname'        => '탈퇴회원-' . $id,
            'is_active'       => 0,
            'social_provider' => null,
            'social_id'       => null,
            'social_token'    => null,
            'avatar'          => null,
        ]);
    }
}
