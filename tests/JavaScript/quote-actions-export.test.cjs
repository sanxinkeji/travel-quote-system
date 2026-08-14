const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const projectRoot = path.resolve(__dirname, '..', '..');

function loadQuoteExport() {
    const context = { console, setTimeout, clearTimeout };
    context.window = context;
    context.self = context;
    context.global = context;
    context.document = { querySelector: () => null };
    context.navigator = {};
    vm.createContext(context);
    vm.runInContext(
        fs.readFileSync(path.join(projectRoot, 'public/vendor/xlsx-js-style/xlsx.bundle.js'), 'utf8'),
        context,
    );
    vm.runInContext(
        fs.readFileSync(path.join(projectRoot, 'public/js/quote-actions.js'), 'utf8'),
        context,
    );

    return { XLSX: context.XLSX, quoteExport: context.quoteExport };
}

function quoteModel() {
    return {
        title: '企业专属定制 · 惠州两天一夜',
        customerTitle: '客户行程报价',
        planner: '演示策划师',
        wechat: 'travel-account',
        phone: '13800138000',
        executor: '广东旅行社',
        reminderTitle: '温馨提示',
        reminderText: '行程顺序可能根据交通情况调整。',
        people: 20,
        perPerson: 853.05,
        total: 17060.92,
        groups: [
            {
                name: 'DAY 01',
                type: 'day',
                subtotal: 3800,
                items: [
                    { time: '09:00', name: '双体帆船', unit: '艘', quantity: 2, unitPrice: 1400, total: 2800, note: '出海2小时' },
                    { time: '18:00', name: '海边别墅', unit: '晚', quantity: 1, unitPrice: 1000, total: 1000, note: '20床' },
                ],
            },
            {
                name: '其他项',
                type: 'other',
                subtotal: 200,
                items: [
                    { time: '', name: '旅游意外险', unit: '位', quantity: 20, unitPrice: 10, total: 200, note: '10万保额' },
                ],
            },
        ],
    };
}

function classList(...names) {
    return { contains: (name) => names.includes(name) };
}

function cell(text, classes = [], dataset = {}) {
    return { textContent: text, classList: classList(...classes), dataset };
}

test('quote export model is extracted from the rendered preview table', () => {
    const { quoteExport } = loadQuoteExport();
    const attributes = {
        'data-export-title': '惠州报价',
        'data-export-customer-title': '客户行程',
        'data-export-planner': '演示策划师',
        'data-export-wechat': 'travel-account',
        'data-export-phone': '13800138000',
        'data-export-executor': '广东旅行社',
        'data-export-reminder-title': '温馨提示',
        'data-export-reminder-text': '按实际情况调整。',
        'data-export-people': '20',
        'data-export-per-person': '853.05',
        'data-export-total': '17060.92',
    };
    const documentNode = { getAttribute: (name) => attributes[name] || '' };
    const rows = [
        {
            classList: classList(),
            cells: [cell('DAY 01', ['group-cell'], { exportGroupType: 'day' }), cell('1'), cell('09:00'), cell('双体帆船'), cell('艘'), cell('2'), cell('¥1,400.00'), cell('¥2,800.00'), cell('出海2小时')],
        },
        {
            classList: classList(),
            cells: [cell('2'), cell('18:00'), cell('海边别墅'), cell('晚'), cell('1'), cell('¥1,000.00'), cell('¥1,000.00'), cell('20床')],
        },
        {
            classList: classList('subtotal-row'),
            cells: [cell('DAY 01小计'), cell('¥3,800.00', ['numeric']), cell('')],
        },
        {
            classList: classList(),
            cells: [cell('可改名附加费用', ['group-cell'], { exportGroupType: 'other' }), cell('1'), cell(''), cell('旅游意外险'), cell('位'), cell('20'), cell('¥10.00'), cell('¥200.00'), cell('10万保额')],
        },
        {
            classList: classList('subtotal-row'),
            cells: [cell('可改名附加费用小计'), cell('¥200.00', ['numeric']), cell('')],
        },
    ];
    const table = { querySelectorAll: (selector) => selector === 'tbody tr' ? rows : [] };

    const extracted = JSON.parse(JSON.stringify(quoteExport.extractQuoteModel(documentNode, table)));
    assert.deepEqual(extracted, {
        ...quoteModel(),
        title: '惠州报价',
        customerTitle: '客户行程',
        reminderText: '按实际情况调整。',
        groups: [quoteModel().groups[0], {...quoteModel().groups[1], name: '可改名附加费用'}],
    });
});

test('styled quote workbook preserves the quote structure and numeric money values', () => {
    const { XLSX, quoteExport } = loadQuoteExport();

    assert.ok(quoteExport?.buildQuoteWorkbook, 'quote export API should be available without a rendered preview');
    const workbook = quoteExport.buildQuoteWorkbook(quoteModel());
    const sheet = workbook.Sheets['报价明细'];

    assert.ok(sheet);
    assert.equal(sheet.A1.v, '企业专属定制 · 惠州两天一夜');
    assert.equal(sheet.A2.v, '客户行程报价');
    assert.equal(sheet.A4.v, '策划人：演示策划师');
    assert.equal(sheet.A6.v, '温馨提示');
    assert.equal(sheet.C6.v, '行程顺序可能根据交通情况调整。');
    assert.deepEqual(
        Array.from(sheet['!merges'], (range) => XLSX.utils.encode_range(range)),
        ['A1:I1', 'A2:I2', 'A4:C4', 'D4:F4', 'G4:I4', 'A5:I5', 'A6:B6', 'C6:I6', 'A9:A10', 'A11:G11', 'A13:G13', 'A15:C15', 'D15:F15'],
    );
    assert.equal(sheet.A8.v, '时间/日期');
    assert.equal(sheet.A9.v, 'DAY 01');
    assert.equal(sheet.D9.v, '双体帆船');
    assert.equal(sheet.D10.v, '海边别墅');
    assert.equal(sheet.H11.v, 3800);
    assert.equal(sheet.A12.v, '其他项');
    assert.equal(sheet.H13.v, 200);
    assert.equal(sheet.A15.v, '汇总（人数）：20 人');
    assert.equal(sheet.G15.v, 853.05);
    assert.equal(sheet.I15.v, 17060.92);
    for (const address of ['F9', 'G9', 'H9', 'H11', 'G15', 'I15']) {
        assert.equal(sheet[address].t, 'n', `${address} should remain numeric`);
    }

    const bytes = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
    assert.ok(bytes.byteLength > 0);
});

test('styled quote workbook carries the preview layout and visual styles', () => {
    const { quoteExport } = loadQuoteExport();
    const sheet = quoteExport.buildQuoteWorkbook(quoteModel()).Sheets['报价明细'];

    assert.deepEqual(Array.from(sheet['!cols'], (column) => column.wch), [12, 7, 16, 36, 12, 10, 14, 14, 42]);
    assert.equal(sheet['!rows'][0].hpt, 30);
    assert.equal(sheet['!rows'][5].hpt, 38);
    assert.equal(sheet.A1.s.font.name, 'Microsoft YaHei');
    assert.equal(sheet.A1.s.font.sz, 20);
    assert.equal(sheet.A1.s.font.bold, true);
    assert.equal(sheet.A1.s.font.color.rgb, 'FFFFFF');
    assert.equal(sheet.A1.s.fill.fgColor.rgb, '1F4E78');
    assert.equal(sheet.A1.s.alignment.horizontal, 'center');
    assert.equal(sheet.A8.s.fill.fgColor.rgb, 'F4C542');
    assert.equal(sheet.A8.s.font.color.rgb, '4E410C');
    assert.equal(sheet.A8.s.border.bottom.style, 'thin');
    assert.equal(sheet.A8.s.alignment.wrapText, true);
    assert.equal(sheet.A9.s.fill.fgColor.rgb, 'EAF5FF');
    assert.equal(sheet.A12.s.fill.fgColor.rgb, 'FDECEC');
    assert.equal(sheet.D9.s.alignment.wrapText, true);
    assert.equal(sheet.H11.s.font.bold, true);
    assert.equal(sheet.H11.s.fill.fgColor.rgb, 'FAFAFA');
    assert.equal(sheet.G9.s.numFmt, '¥#,##0.00');
    assert.equal(sheet.H9.s.numFmt, '¥#,##0.00');
    assert.equal(sheet.F9.s.numFmt, '0.##');
    assert.equal(sheet.I15.s.numFmt, '¥#,##0.00');
    assert.equal(sheet.I15.s.font.bold, true);
    assert.equal(sheet.I15.s.fill.fgColor.rgb, 'FFFCF0');
    assert.equal(sheet.I15.s.border.top.style, 'medium');
});
