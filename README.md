# 旅行社旅游报价系统 / Travel Quote System

面向旅行社和定制旅游团队的开源历史报价库、行程规划与报价单管理系统。员工可以按年份、月份、目的地、行程天数、人数和预算筛选历史方案，复制最接近的报价后微调，并导出客户报价图片或 Excel 表格。

An open-source travel agency quotation and itinerary planner built with Laravel. Search reusable historical itineraries, copy a close match, adjust the schedule and pricing, then export a client-ready image or Excel workbook.

> 适合旅行社行程报价、团队定制游、团建方案、历史报价复用和内部协作。

## 解决什么问题 / Why It Exists

旅行社经常为相似人数、预算和目的地重复制作报价表。本项目把已经完成的方案沉淀为历史报价库，让团队从最接近的行程开始调整，而不是每次从空白表格重新规划。

The system turns previously completed quotations into a shared, searchable library. It reduces repeated spreadsheet work while preserving the familiar itinerary and quotation workflow.

## 核心功能 / Features

- 按年份、月份、目的地、行程类型、人数区间和预算筛选历史报价
- 查看原报价，或“复制并微调”后保存为当前员工的新报价
- 同一天排序和跨 DAY 拖动行程项目，“其他项”始终固定在报价末尾
- 完整支持报价基础信息、分组和项目的新增、删除与修改
- 自动计算每日小计、其他项小计、税费、人均金额和最终总价
- 管理员可管理全部报价和员工账号；普通员工可查看历史报价并管理自己的数据
- 客户报价预览支持复制/下载图片和导出带样式的 Excel 工作簿
- 关键账号及报价操作写入审计日志

## 使用流程 / Workflow

1. 在历史报价库中筛选目的地、天数、人数和预算。
2. 直接查看接近的报价，或选择“复制并微调”。
3. 调整行程顺序、项目、住宿、数量和价格。
4. 保存为当前员工名下的新报价，不修改原始历史方案。
5. 在客户预览页导出图片或 Excel 表格。

## 权限模型 / Permissions

| 能力 | 管理员 | 普通员工 |
| --- | --- | --- |
| 查看全部历史报价 | 是 | 是 |
| 新增报价 | 是 | 是 |
| 修改/删除自己的报价 | 是 | 是 |
| 修改/删除他人的报价 | 是 | 否 |
| 创建、停用和重置员工账号 | 是 | 否 |

## 技术栈 / Tech Stack

- PHP 8.2+
- Laravel 12
- MySQL 8.0（生产）/ SQLite（本地测试）
- Blade + 原生 JavaScript
- SortableJS、html2canvas、SheetJS / xlsx-js-style

## 本地运行 / Local Development

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

本地开发时，将 `.env` 中的 `APP_ENV` 设置为 `local`，并设置仅用于本地的管理员密码：

```dotenv
ADMIN_USERNAME=admin
ADMIN_NAME="Local Administrator"
ADMIN_PASSWORD=local-admin-change-me
```

打开 `http://127.0.0.1:8000`，使用上述本地账号登录。不要在生产环境使用示例密码。

## 测试 / Testing

```bash
php artisan test
./vendor/bin/pint --test
npm test
```

测试使用独立的内存 SQLite 数据库，不会连接生产数据库。

## 生产部署 / Production Deployment

项目包含宝塔面板、Nginx、PHP 8.2 和 MySQL 8.0 的完整部署说明：

- [宝塔面板部署教程](BAOTA_DEPLOYMENT.md)
- [在线演示 / Live Demo](https://baojia.dclvyou.com)

生成不包含 `.env`、数据库、测试和开发缓存的发布包：

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\build-release.ps1
```

## 安全说明 / Security

- 不要提交 `.env`、数据库备份、客户资料、日志或生产账号。
- 生产环境必须设置 `APP_DEBUG=false` 并启用 HTTPS。
- 使用独立强密码配置数据库和管理员账号。
- 站点运行目录必须指向 `public/`，不要暴露项目根目录。
- 发现安全问题时，请避免在公开 Issue 中披露有效凭据或客户数据。

## SEO Keywords

`travel quote system` · `itinerary planner` · `travel agency quotation` · `Laravel quotation management` · `旅游报价系统` · `旅行社行程报价` · `历史报价库`

## Contributing

欢迎提交 Issue 和 Pull Request。提交代码前请运行 PHP、格式和 JavaScript 测试，并确保示例数据不包含真实客户信息。

## License

本项目采用 [MIT License](LICENSE)，允许个人和企业使用、修改及商用，但须保留许可证和版权声明。
