<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteManager;
use Illuminate\Database\Seeder;
use RuntimeException;

class HistoricalQuoteSeeder extends Seeder
{
    public function run(QuoteManager $manager): void
    {
        $configuredUsername = trim((string) env('ADMIN_USERNAME', ''));
        $owner = User::query()
            ->where('role', 'admin')
            ->when($configuredUsername !== '', fn ($query) => $query->where('username', $configuredUsername))
            ->oldest('id')
            ->first();

        if (! $owner) {
            throw new RuntimeException('导入历史报价前必须先创建管理员账号。');
        }

        foreach ($this->quotes() as $quote) {
            if (Quote::query()->where('source_name', $quote['source_name'])->exists()) {
                continue;
            }

            $manager->create($quote, $owner);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function quotes(): array
    {
        return [
            $this->qingyuanQuote(),
            $this->shaoguanQuote(),
            $this->huizhouQuote(),
        ];
    }

    /** @return array<string, mixed> */
    private function qingyuanQuote(): array
    {
        return [
            ...$this->commonFields(),
            'title' => '企业专属定制 · 清远两天一夜游',
            'customer_title' => '企业专属定制 · 清远两天一夜游',
            'destination' => '清远',
            'year' => 2026,
            'month' => 8,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 30,
            'budget_per_person' => 800,
            'source_name' => '26年8月 清远.xlsx',
            'groups' => [
                $this->group('DAY 01', [
                    $this->item('09:30-12:00', '集合出发-清远', '自备车辆', 0, 0, '/'),
                    $this->item('12:00-13:30', '享受团队午餐', '10人/围', 3, 500),
                    $this->item('13:30-17:00', '前往古龙峡游玩全程漂流', '位', 30, 168, '国际（勇猛）赛道全程漂'),
                    $this->item('13:30-17:00', '云天玻霸+悬崖飞车', '位', 30, 198),
                    $this->item('17:30-18:00', '入住清远恒大18房34床', '平日/晚', 1, 5000),
                    $this->item('18:00-21:00', '别墅烧烤BBQ', '10人/围', 3, 500),
                    $this->item('21:30', '别墅轰趴：麻将、KTV、桌游等', '/', 0, 0),
                ]),
                $this->group('DAY 02', [
                    $this->item('08:30-10:00', '享受别墅早餐', '位', 30, 15, '送上门的别墅早餐'),
                    $this->item('10:00-12:00', '打卡笔架山大瀑布与探险', '位', 30, 48),
                    $this->item('12:00-13:30', '享受团队午餐', '10人/围', 3, 500),
                    $this->item('13:30', '返程', '/', 0, 0),
                ]),
                $this->group('其他项', [
                    $this->item('/', '全陪导游', '/', 0, 0),
                    $this->taxItem('6%“生产生活服务”增值税普通发票', '6%', 0),
                    $this->item('/', '旅游出行团体意外险：10万保额', '2天/位', 30, 10),
                    $this->item('/', '旅行社策划/操作服务费', '位', 30, 10),
                    $this->item('/', '定制横幅1条+饮用水1支/人/天', '/', 0, 0, '旅行社赠送'),
                ], 'other'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function shaoguanQuote(): array
    {
        return [
            ...$this->commonFields(),
            'title' => '韶关丹霞 · 企业两日拓展',
            'customer_title' => '韶关丹霞 · 企业两日拓展',
            'destination' => '韶关',
            'year' => 2026,
            'month' => 4,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 30,
            'budget_per_person' => 812,
            'source_name' => '2026年4月 韶关.xlsx',
            'groups' => [
                $this->group('DAY 01', [
                    $this->item('09:00-12:00', '广州出发-韶关丹霞山', '自备车辆', 0, 0),
                    $this->item('13:00-17:00', '丹霞山景区游览', '位', 30, 100, '含门票'),
                    $this->item('18:00-20:00', '团队晚餐', '10人/围', 3, 600),
                    $this->item('20:00-次日', '入住当地民宿', '晚', 15, 420, '双人间'),
                ]),
                $this->group('DAY 02', [
                    $this->item('08:30-12:00', '户外拓展活动', '位', 30, 180, '教练与道具'),
                    $this->item('12:00-13:30', '团队午餐', '10人/围', 3, 600),
                    $this->item('14:00', '返程', '/', 0, 0),
                ]),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function huizhouQuote(): array
    {
        return [
            ...$this->commonFields(),
            'title' => '惠州巽寮湾 · 两天一夜定制行程',
            'customer_title' => '惠州海边两天一夜定制行程',
            'destination' => '惠州',
            'year' => 2026,
            'month' => 7,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 20,
            'budget_per_person' => 900,
            'source_name' => '2026年7月 惠州演示方案.xlsx',
            'source_url' => null,
            'groups' => [
                $this->group('DAY 01', [
                    $this->item('09:00-11:30', '集合出发-惠州海边', '自驾', 0, 0),
                    $this->item('12:00-13:30', '享受团队午餐', '10人/围', 2, 500),
                    $this->item('14:00-16:00', '网红双体帆船出海轰趴2小时', '2h/艘', 2, 1400, '每艘核载11名贵宾，可下水并赠送水果、饮料、鱼竿鱼饵、滑滑梯、浮毯、蹦蹦床、水枪等'),
                    $this->item('16:00-18:00', '前往入住途径赶海公园打卡', '/', 0, 0),
                    $this->item('18:00-20:00', '入住巽寮湾凤池岛威尼斯·9房20床', '周六/晚', 1, 7300),
                    $this->item('', '别墅烧烤BBQ', '10人/围', 2, 500),
                    $this->item('20:30', '别墅轰趴', '/', 0, 0),
                ]),
                $this->group('DAY 02', [
                    $this->item('08:30-10:00', '享受别墅早餐', '位', 20, 15),
                    $this->item('10:00-12:00', '睡到自然醒/自由活动', '/', 0, 0),
                    $this->item('12:00-13:30', '享受团队午餐', '10人/围', 2, 500),
                    $this->item('13:30-16:00', '蜜月湾上岛', '9人/艘', 3, 988, '快艇接送+皮划艇+装备游泳+太阳伞射箭+魔毯+赶海工具+摩托艇+冲凉'),
                    $this->item('16:30', '打卡磨子石公园', '/', 0, 0),
                    $this->item('', '返程', '/', 0, 0),
                ]),
                $this->group('其他项', [
                    $this->item('/', '全陪导游', '自驾/无', 0, 0),
                    $this->taxItem('6%“生产生活服务”增值税普通发票', '3%', 1),
                    $this->item('', '旅游出行团体意外险', '2天/位', 20, 10, '10万保额'),
                    $this->item('', '旅行社策划/操作服务费', '无', 0, 0),
                    $this->item('', '定制横幅1条', '/', 0, 0, '旅行社赠送'),
                ], 'other'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function commonFields(): array
    {
        return [
            'planner_name' => '演示策划师',
            'wechat' => 'demo_travel_quote',
            'phone' => '13800000000',
            'executor' => '示例旅行社有限公司',
            'reminder_title' => '温馨提示',
            'reminder_text' => '以下行程安排均在最理想化情况下，因交通堵塞、特殊情况、自然灾害等不可抗力因素，旅行社有权根据实际情况调整行程顺序，以保障行程顺利进行和人员安全。',
            'status' => 'historical',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function group(string $name, array $items, string $type = 'day'): array
    {
        return compact('name', 'type', 'items');
    }

    /** @return array<string, mixed> */
    private function item(
        string $time,
        string $name,
        string $unit,
        float $quantity,
        float $unitPrice,
        string $note = '',
    ): array {
        return [
            'time' => $time,
            'name' => $name,
            'unit' => $unit,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'note' => $note,
        ];
    }

    /** @return array<string, mixed> */
    private function taxItem(string $name, string $unit, float $quantity): array
    {
        return [
            'time' => '',
            'name' => $name,
            'unit' => $unit,
            'quantity' => $quantity,
            'is_tax' => true,
        ];
    }
}
