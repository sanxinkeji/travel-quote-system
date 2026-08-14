(function (global) {
    'use strict';

    const documentNode = typeof document === 'undefined' ? null : document.querySelector('[data-quote-document]');
    const exportAttributes = {
        title: 'data-export-title',
        customerTitle: 'data-export-customer-title',
        planner: 'data-export-planner',
        wechat: 'data-export-wechat',
        phone: 'data-export-phone',
        executor: 'data-export-executor',
        reminderTitle: 'data-export-reminder-title',
        reminderText: 'data-export-reminder-text',
        people: 'data-export-people',
        perPerson: 'data-export-per-person',
        total: 'data-export-total',
    };

    function numberValue(value) {
        const normalized = String(value ?? '').replace(/[^\d.-]/g, '');
        const number = Number(normalized);

        return Number.isFinite(number) ? number : 0;
    }

    function textValue(cell) {
        return String(cell?.textContent || '').trim();
    }

    function extractQuoteModel(node = documentNode, table = typeof document === 'undefined' ? null : document.querySelector('[data-export-table-source]')) {
        if (!node || !table) throw new Error('报价预览数据不完整，请刷新页面后重试');

        const groups = [];
        let currentGroup = null;

        Array.from(table.querySelectorAll('tbody tr')).forEach((row) => {
            const cells = Array.from(row.cells || []);
            if (row.classList.contains('subtotal-row')) {
                if (currentGroup) {
                    const subtotalCell = cells.find((cell) => cell.classList.contains('numeric'));
                    currentGroup.subtotal = numberValue(textValue(subtotalCell));
                }
                return;
            }
            if (row.classList.contains('empty-cell') || cells.length === 0) return;

            let itemOffset = 0;
            if (cells[0]?.classList.contains('group-cell')) {
                currentGroup = {
                    name: textValue(cells[0]),
                    type: cells[0].dataset.exportGroupType || (cells[0].classList.contains('other') ? 'other' : 'day'),
                    subtotal: 0,
                    items: [],
                };
                groups.push(currentGroup);
                itemOffset = 1;
            }
            if (!currentGroup || cells.length - itemOffset < 8) return;

            currentGroup.items.push({
                time: textValue(cells[itemOffset + 1]),
                name: textValue(cells[itemOffset + 2]),
                unit: textValue(cells[itemOffset + 3]),
                quantity: numberValue(textValue(cells[itemOffset + 4])),
                unitPrice: numberValue(textValue(cells[itemOffset + 5])),
                total: numberValue(textValue(cells[itemOffset + 6])),
                note: textValue(cells[itemOffset + 7]),
            });
        });

        const attribute = (key) => node.getAttribute(exportAttributes[key]) || '';

        return {
            title: attribute('title'),
            customerTitle: attribute('customerTitle'),
            planner: attribute('planner'),
            wechat: attribute('wechat'),
            phone: attribute('phone'),
            executor: attribute('executor'),
            reminderTitle: attribute('reminderTitle'),
            reminderText: attribute('reminderText'),
            people: numberValue(attribute('people')),
            perPerson: numberValue(attribute('perPerson')),
            total: numberValue(attribute('total')),
            groups,
        };
    }

    const colors = {
        navy: '1F4E78',
        white: 'FFFFFF',
        yellow: 'F4C542',
        yellowText: '4E410C',
        dayFill: 'EAF5FF',
        dayText: '26578F',
        otherFill: 'FDECEC',
        otherText: '8A4545',
        noticeFill: 'FFFCEF',
        noticeText: '635A38',
        metaFill: 'F3F6F9',
        subtotalFill: 'FAFAFA',
        border: 'D9D9D9',
        summaryFill: 'FFFCF0',
        text: '1F2937',
    };
    const currencyFormat = '¥#,##0.00';

    function solidFill(rgb) {
        return { patternType: 'solid', fgColor: { rgb } };
    }

    function border(style = 'thin', color = colors.border) {
        const edge = { style, color: { rgb: color } };

        return { top: edge, right: edge, bottom: edge, left: edge };
    }

    function cellStyle(overrides = {}) {
        return {
            font: { name: 'Microsoft YaHei', sz: 10, color: { rgb: colors.text }, ...(overrides.font || {}) },
            alignment: { vertical: 'center', wrapText: true, ...(overrides.alignment || {}) },
            border: overrides.border || border(),
            ...(overrides.fill ? { fill: overrides.fill } : {}),
            ...(overrides.numFmt ? { numFmt: overrides.numFmt } : {}),
        };
    }

    function styleRange(XLSX, sheet, range, style) {
        const decoded = typeof range === 'string' ? XLSX.utils.decode_range(range) : range;
        for (let row = decoded.s.r; row <= decoded.e.r; row += 1) {
            for (let column = decoded.s.c; column <= decoded.e.c; column += 1) {
                const address = XLSX.utils.encode_cell({ r: row, c: column });
                const cell = sheet[address] || { t: 's', v: '' };
                cell.s = style;
                sheet[address] = cell;
            }
        }
    }

    function applyWorkbookStyles(XLSX, sheet, groupLayouts, summaryRow) {
        sheet['!cols'] = [12, 7, 16, 36, 12, 10, 14, 14, 42].map((wch) => ({ wch }));
        sheet['!rows'] = [];
        sheet['!rows'][0] = { hpt: 30 };
        sheet['!rows'][1] = { hpt: 22 };
        sheet['!rows'][2] = { hpt: 8 };
        sheet['!rows'][3] = { hpt: 22 };
        sheet['!rows'][4] = { hpt: 22 };
        sheet['!rows'][5] = { hpt: 38 };
        sheet['!rows'][6] = { hpt: 8 };
        sheet['!rows'][7] = { hpt: 24 };

        const bounds = XLSX.utils.decode_range(sheet['!ref']);
        styleRange(XLSX, sheet, bounds, cellStyle());
        styleRange(XLSX, sheet, 'A1:I1', cellStyle({
            fill: solidFill(colors.navy),
            font: { sz: 20, bold: true, color: { rgb: colors.white } },
            alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        }));
        styleRange(XLSX, sheet, 'A2:I2', cellStyle({
            fill: solidFill(colors.navy),
            font: { sz: 13, bold: true, color: { rgb: colors.white } },
            alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        }));
        styleRange(XLSX, sheet, 'A4:I5', cellStyle({
            fill: solidFill(colors.metaFill),
            alignment: { horizontal: 'left', vertical: 'center', wrapText: true },
        }));
        styleRange(XLSX, sheet, 'A6:I6', cellStyle({
            fill: solidFill(colors.noticeFill),
            font: { color: { rgb: colors.noticeText } },
            alignment: { horizontal: 'left', vertical: 'center', wrapText: true },
        }));
        styleRange(XLSX, sheet, 'A6:B6', cellStyle({
            fill: solidFill(colors.noticeFill),
            font: { bold: true, color: { rgb: colors.noticeText } },
            alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        }));
        styleRange(XLSX, sheet, 'A8:I8', cellStyle({
            fill: solidFill(colors.yellow),
            font: { bold: true, color: { rgb: colors.yellowText } },
            alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        }));

        groupLayouts.forEach((layout) => {
            for (let row = layout.itemStart; row <= layout.itemEnd; row += 1) {
                sheet['!rows'][row - 1] = { hpt: 30 };
                styleRange(XLSX, sheet, `A${row}:I${row}`, cellStyle());
                styleRange(XLSX, sheet, `B${row}:C${row}`, cellStyle({ alignment: { horizontal: 'center' } }));
                styleRange(XLSX, sheet, `E${row}:H${row}`, cellStyle({ alignment: { horizontal: 'center' } }));
                styleRange(XLSX, sheet, `F${row}:F${row}`, cellStyle({ alignment: { horizontal: 'center' }, numFmt: '0.##' }));
                styleRange(XLSX, sheet, `G${row}:H${row}`, cellStyle({ alignment: { horizontal: 'center' }, numFmt: currencyFormat }));
            }

            const groupFill = layout.type === 'other' ? colors.otherFill : colors.dayFill;
            const groupText = layout.type === 'other' ? colors.otherText : colors.dayText;
            styleRange(XLSX, sheet, `A${layout.itemStart}:A${layout.itemEnd}`, cellStyle({
                fill: solidFill(groupFill),
                font: { bold: true, color: { rgb: groupText } },
                alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
            }));

            sheet['!rows'][layout.subtotalRow - 1] = { hpt: 24 };
            styleRange(XLSX, sheet, `A${layout.subtotalRow}:I${layout.subtotalRow}`, cellStyle({
                fill: solidFill(colors.subtotalFill),
                font: { bold: true },
                alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
            }));
            styleRange(XLSX, sheet, `H${layout.subtotalRow}`, cellStyle({
                fill: solidFill(colors.subtotalFill),
                font: { bold: true },
                alignment: { horizontal: 'center' },
                numFmt: currencyFormat,
            }));
        });

        sheet['!rows'][summaryRow - 2] = { hpt: 8 };
        sheet['!rows'][summaryRow - 1] = { hpt: 28 };
        const summaryBorder = border();
        summaryBorder.top = { style: 'medium', color: { rgb: colors.navy } };
        styleRange(XLSX, sheet, `A${summaryRow}:I${summaryRow}`, cellStyle({
            fill: solidFill(colors.summaryFill),
            font: { bold: true },
            alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
            border: summaryBorder,
        }));
        styleRange(XLSX, sheet, `G${summaryRow}:G${summaryRow}`, cellStyle({
            fill: solidFill(colors.summaryFill),
            font: { sz: 12, bold: true, color: { rgb: colors.navy } },
            alignment: { horizontal: 'center' },
            border: summaryBorder,
            numFmt: currencyFormat,
        }));
        styleRange(XLSX, sheet, `I${summaryRow}:I${summaryRow}`, cellStyle({
            fill: solidFill(colors.summaryFill),
            font: { sz: 12, bold: true, color: { rgb: colors.navy } },
            alignment: { horizontal: 'center' },
            border: summaryBorder,
            numFmt: currencyFormat,
        }));
    }

    function buildQuoteWorkbook(model = extractQuoteModel()) {
        const XLSX = global.XLSX;
        if (!XLSX) throw new Error('表格组件加载失败，请刷新页面重试');

        const rows = [
            [model.title || '行程报价单'],
            [model.customerTitle || '客户行程报价'],
            [],
            [`策划人：${model.planner || '-'}`, '', '', `微信号：${model.wechat || '-'}`, '', '', `联系方式：${model.phone || '-'}`],
            [`执行方：${model.executor || '-'}`],
            [model.reminderTitle || '温馨提示', '', model.reminderText || ''],
            [],
            ['时间/日期', '序号', '预估时段', '项目名称', '单位', '数量', '单价', '总价', '备注/其他'],
        ];
        const merges = [
            'A1:I1', 'A2:I2',
            'A4:C4', 'D4:F4', 'G4:I4',
            'A5:I5', 'A6:B6', 'C6:I6',
        ].map((range) => XLSX.utils.decode_range(range));

        const groupLayouts = [];
        let rowNumber = rows.length + 1;
        (model.groups || []).forEach((group) => {
            const items = group.items || [];
            const itemStart = rowNumber;
            items.forEach((item, itemIndex) => {
                rows.push([
                    itemIndex === 0 ? group.name : '',
                    itemIndex + 1,
                    item.time || '',
                    item.name || '',
                    item.unit || '',
                    numberValue(item.quantity),
                    numberValue(item.unitPrice),
                    numberValue(item.total),
                    item.note || '',
                ]);
                rowNumber += 1;
            });
            if (items.length > 1) {
                merges.push(XLSX.utils.decode_range(`A${itemStart}:A${rowNumber - 1}`));
            }

            rows.push([`${group.name || '本组'}小计`, '', '', '', '', '', '', numberValue(group.subtotal), '']);
            merges.push(XLSX.utils.decode_range(`A${rowNumber}:G${rowNumber}`));
            groupLayouts.push({ type: group.type || 'day', itemStart, itemEnd: rowNumber - 1, subtotalRow: rowNumber });
            rowNumber += 1;
        });

        rows.push([]);
        rowNumber += 1;
        rows.push([
            `汇总（人数）：${numberValue(model.people)} 人`, '', '',
            '人均/位：', '', '', numberValue(model.perPerson),
            '总计：', numberValue(model.total),
        ]);
        merges.push(XLSX.utils.decode_range(`A${rowNumber}:C${rowNumber}`));
        merges.push(XLSX.utils.decode_range(`D${rowNumber}:F${rowNumber}`));

        const sheet = XLSX.utils.aoa_to_sheet(rows);
        sheet['!merges'] = merges;
        applyWorkbookStyles(XLSX, sheet, groupLayouts, rowNumber);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, sheet, '报价明细');

        return workbook;
    }

    global.quoteExport = { buildQuoteWorkbook, extractQuoteModel };
    if (!documentNode) return;

    function filename(extension) {
        const raw = document.querySelector('[data-export-table]')?.dataset.filename || '行程报价';
        return `${raw.replace(/[\\/:*?"<>|]/g, '-')}.${extension}`;
    }

    async function renderCanvas() {
        if (typeof global.html2canvas !== 'function') throw new Error('图片组件加载失败，请刷新页面重试');
        return global.html2canvas(documentNode, { backgroundColor: '#ffffff', scale: 2, useCORS: true, logging: false });
    }

    document.querySelector('[data-copy-image]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const canvas = await renderCanvas();
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
            if (!navigator.clipboard || typeof ClipboardItem === 'undefined') throw new Error('当前浏览器不支持直接复制图片，请使用“下载图片”');
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
            global.showWorkspaceToast?.('报价图片已复制，可直接粘贴发送');
        } catch (error) {
            global.showWorkspaceToast?.(error.message || '复制图片失败', 'error');
        } finally { button.disabled = false; }
    });

    document.querySelector('[data-download-image]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const canvas = await renderCanvas();
            const link = document.createElement('a');
            link.download = filename('png');
            link.href = canvas.toDataURL('image/png');
            link.click();
            global.showWorkspaceToast?.('报价图片已下载');
        } catch (error) {
            global.showWorkspaceToast?.(error.message || '下载图片失败', 'error');
        } finally { button.disabled = false; }
    });

    document.querySelector('[data-export-table]')?.addEventListener('click', () => {
        if (!global.XLSX) {
            global.showWorkspaceToast?.('表格组件加载失败，请刷新页面重试', 'error');
            return;
        }
        try {
            global.XLSX.writeFile(buildQuoteWorkbook(), filename('xlsx'));
            global.showWorkspaceToast?.('报价表格已导出');
        } catch (error) {
            global.showWorkspaceToast?.(error.message || '导出表格失败', 'error');
        }
    });
})(typeof window === 'undefined' ? globalThis : window);
