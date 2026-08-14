# 宝塔面板部署教程

本系统运行环境：PHP 8.2、MySQL 8.0、Nginx、Laravel 12。以下命令中的站点目录以 `/www/wwwroot/travel-quote-system` 为例。

## 1. 安装运行环境

在宝塔软件商店安装：

- Nginx
- MySQL 8.0
- PHP 8.2
- Composer 2

在 PHP 8.2 的“安装扩展”中确认已启用：`fileinfo`、`mbstring`、`openssl`、`pdo_mysql`、`tokenizer`、`xml`、`ctype`、`curl`、`dom`、`bcmath`。上传文件限制不是本系统当前功能的瓶颈，可保留宝塔默认值。

## 2. 创建数据库

在宝塔“数据库”中新建数据库，例如：

- 数据库名：`travel_quote`
- 用户名：`travel_quote`
- 字符集：`utf8mb4`
- 访问权限：本地服务器

请使用宝塔生成的强密码，并单独保存。不要与管理员登录密码相同。

## 3. 上传项目

将 `dist/travel-quote-system.zip` 上传到 `/www/wwwroot/` 后解压，最终应能看到：

```text
/www/wwwroot/travel-quote-system/artisan
/www/wwwroot/travel-quote-system/public/index.php
```

在宝塔网站设置中：

- PHP 版本选择 `PHP-82`
- 运行目录必须选择 `/public`
- 关闭“防跨站攻击”只在 Composer 或 Laravel 报路径权限错误时使用；能正常运行则保持开启

Nginx 伪静态配置填入：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 4. 配置生产环境

进入站点终端：

```bash
cd /www/wwwroot/travel-quote-system
cp .env.example .env
```

编辑 `.env`，至少修改这些值：

```dotenv
APP_NAME="旅游报价工作台"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://quote.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=travel_quote
DB_USERNAME=travel_quote
DB_PASSWORD=数据库强密码

ADMIN_USERNAME=admin
ADMIN_NAME="系统管理员"
ADMIN_PASSWORD=首次登录使用的管理员强密码

SESSION_DRIVER=database
QUEUE_CONNECTION=sync
SESSION_SECURE_COOKIE=true
```

`ADMIN_PASSWORD` 只在初始化或主动重新运行 Seeder 时使用。生产环境如果仍为 `CHANGE_ME_BEFORE_SEEDING`，系统会拒绝初始化。

## 5. 安装并初始化

```bash
cd /www/wwwroot/travel-quote-system
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed --force
php artisan optimize
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

`migrate --seed` 会创建初始管理员并导入系统附带的历史报价。Seeder 可重复执行，但再次运行会按照 `.env` 中的密码更新同名管理员密码，因此平时不要无故执行 `db:seed`。

打开网站，用 `ADMIN_USERNAME` 和 `ADMIN_PASSWORD` 登录。进入“用户管理”创建员工账号，员工不需要首次登录后强制修改密码。

当前版本没有异步任务，`QUEUE_CONNECTION=sync` 即可，不需要额外启动队列进程。后续若增加异步导入、邮件或定时任务，再在宝塔“进程守护管理器”中运行 `php artisan queue:work --sleep=3 --tries=3`。

## 6. HTTPS 和安全设置

在宝塔网站的“SSL”中申请并开启证书，然后启用强制 HTTPS。确认 `.env` 中：

```dotenv
APP_URL=https://你的域名
SESSION_SECURE_COOKIE=true
APP_DEBUG=false
```

修改 `.env` 后执行：

```bash
php artisan optimize:clear
php artisan optimize
```

不要将 `.env` 下载后发给他人，也不要让 Nginx 站点根目录指向项目根目录，必须指向 `/public`。

## 7. 备份

建议在宝塔“计划任务”中配置：

- 每天备份 MySQL 数据库，保留至少 14 份
- 每天备份站点目录，重点保留 `.env` 和 `storage`
- 将备份同步到另一台服务器或对象存储

手工升级前可执行：

```bash
mysqldump -u travel_quote -p travel_quote > /www/backup/travel_quote_before_update.sql
```

## 8. 日后更新

先备份数据库和当前代码，再上传新发布包。不要覆盖服务器上的 `.env`。

```bash
cd /www/wwwroot/travel-quote-system
php artisan down --retry=60
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
php artisan up
```

更新过程一般不要加 `--seed`，避免管理员密码被 `.env` 中旧的初始化密码重置。

## 9. 常见问题

- 页面显示 500：先查看 `storage/logs/laravel.log`，并确认 `storage`、`bootstrap/cache` 属主为 `www` 且可写。
- 页面只有 404：检查运行目录是否为 `/public`，并重新填写 Nginx 伪静态。
- 登录后马上退出：检查数据库中的 `sessions` 表是否存在、`APP_URL` 是否正确；未开启 HTTPS 时将 `SESSION_SECURE_COOKIE` 暂设为 `false`。
- 图片或 Excel 导出失败：清理浏览器缓存；相关前端库已包含在 `public/vendor`，不依赖外部 CDN。
- Composer 提示 PHP 版本不符：确认网站和命令行使用的都是 PHP 8.2，可用 `/www/server/php/82/bin/php artisan --version` 检查。
