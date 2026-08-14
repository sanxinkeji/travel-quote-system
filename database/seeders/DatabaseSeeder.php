<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $username = trim((string) env('ADMIN_USERNAME', 'admin'));
        $name = trim((string) env('ADMIN_NAME', '系统管理员'));
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($password === '') {
            if (app()->isProduction()) {
                throw new RuntimeException('生产环境运行 db:seed 前必须设置 ADMIN_PASSWORD。');
            }

            $password = 'local-admin-change-me';
        }

        if (app()->isProduction() && $password === 'CHANGE_ME_BEFORE_SEEDING') {
            throw new RuntimeException('请将 ADMIN_PASSWORD 修改为安全密码后再运行 db:seed。');
        }

        if (app()->isProduction() && (
            mb_strlen($password) < 12
            || ! preg_match('/[A-Za-z]/', $password)
            || ! preg_match('/\d/', $password)
        )) {
            throw new RuntimeException('生产环境的 ADMIN_PASSWORD 必须至少 12 位，并同时包含字母和数字。');
        }

        User::query()->updateOrCreate([
            'username' => $username,
        ], [
            'name' => $name,
            'role' => 'admin',
            'is_active' => true,
            'password' => Hash::make($password),
        ]);

        $this->call(HistoricalQuoteSeeder::class);
    }
}
