(function () {
    'use strict';

    const editor = document.querySelector('[data-quote-editor]');
    if (!editor) return;
    const groupsRoot = editor.querySelector('[data-groups]');
    const informationPanel = editor.querySelector('[data-information-panel]');
    const informationFields = editor.querySelector('#quote-information-fields');
    const informationToggle = editor.querySelector('[data-toggle-information]');

    const money = (number) => `¥${Number(number || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const numericValue = (element) => Number(element?.value || 0) || 0;
    const escapeHtml = (value) => String(value || '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);

    function toggleInformationPanel() {
        if (!informationPanel || !informationFields || !informationToggle) return;
        const collapsed = !informationFields.hidden;
        informationFields.hidden = collapsed;
        informationPanel.classList.toggle('is-collapsed', collapsed);
        informationToggle.setAttribute('aria-expanded', String(!collapsed));
        informationToggle.setAttribute('aria-label', collapsed ? '展开基础信息' : '收起基础信息');
        informationToggle.dataset.tooltip = collapsed ? '展开基础信息' : '收起基础信息';
    }

    informationToggle?.addEventListener('click', toggleInformationPanel);

    function extractRate(item) {
        const text = `${item.querySelector('[data-item-unit]')?.value || ''} ${item.querySelector('[data-item-name]')?.value || ''}`;
        const percentage = text.match(/(\d+(?:\.\d+)?)\s*%/);
        if (percentage) return Number(percentage[1]) / 100;
        const stored = numericValue(item.querySelector('[data-tax-rate]'));
        return stored > 1 ? stored / 100 : stored;
    }

    function hasTaxName(item) {
        const name = item.querySelector('[data-item-name]')?.value || '';
        return /(invoice|发票)/i.test(name);
    }

    function detectTaxItem(item) {
        return item.querySelector('[data-is-tax]')?.value === '1' || hasTaxName(item);
    }

    function calculatedOrdinaryTotal(item) {
        return numericValue(item.querySelector('[data-quantity]')) * numericValue(item.querySelector('[data-unit-price]'));
    }

    function ordinaryItemTotal(item) {
        const actual = item.querySelector('[data-actual-total]');
        if (actual && actual.value !== '') return numericValue(actual);
        return calculatedOrdinaryTotal(item);
    }

    function calculateTaxBase() {
        return Array.from(groupsRoot.querySelectorAll('[data-item]')).reduce((sum, item) => {
            return sum + (detectTaxItem(item) ? 0 : ordinaryItemTotal(item));
        }, 0);
    }

    function updateNames() {
        groupsRoot.querySelectorAll('[data-group]').forEach((group, groupIndex) => {
            group.dataset.groupIndex = groupIndex;
            group.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/^groups\[\d+\]/, `groups[${groupIndex}]`);
            });
            const sort = group.querySelector(`input[name="groups[${groupIndex}][sort_order]"]`);
            if (sort) sort.value = groupIndex;
            group.querySelectorAll('[data-item]').forEach((item, itemIndex) => {
                item.dataset.itemIndex = itemIndex;
                const number = item.querySelector('.item-number');
                if (number) number.textContent = itemIndex + 1;
                const dragHandle = item.querySelector('[data-drag-handle]');
                if (dragHandle) dragHandle.setAttribute('aria-label', `拖动第 ${itemIndex + 1} 项排序`);
                item.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/groups\[\d+\]\[items\]\[\d+\]/, `groups[${groupIndex}][items][${itemIndex}]`);
                });
                const itemSort = item.querySelector(`input[name="groups[${groupIndex}][items][${itemIndex}][sort_order]"]`);
                if (itemSort) itemSort.value = itemIndex;
            });
        });
    }

    function moveOtherGroupsToEnd() {
        Array.from(groupsRoot.querySelectorAll('[data-group]'))
            .filter((group) => group.querySelector('input[name$="[type]"]')?.value === 'other')
            .forEach((group) => groupsRoot.appendChild(group));
    }

    function groupType(items) {
        return items.closest('[data-group]')?.querySelector('input[name$="[type]"]')?.value || 'day';
    }

    function canPullDayItem(to, from) {
        const sourceItems = Array.from(from.el.querySelectorAll('[data-item]'));
        return sourceItems.length > 1;
    }

    function finishItemDrag() {
        updateNames();
        refreshTotals();
    }

    function initializeItemSortables() {
        if (typeof Sortable !== 'function') return;
        groupsRoot.querySelectorAll('[data-items]').forEach((items, index) => {
            if (items.dataset.sortableInitialized === 'true') return;
            const options = {
                draggable: '[data-item]',
                handle: '[data-drag-handle]',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                delay: 0,
                touchStartThreshold: 5,
                onEnd: finishItemDrag,
            };
            if (groupType(items) === 'day') {
                options.group = { name: 'quote-day-items', pull: canPullDayItem, put: true };
            } else {
                options.group = { name: `quote-other-items-${index}`, pull: false, put: false };
            }
            new Sortable(items, options);
            items.dataset.sortableInitialized = 'true';
        });
    }

    function refreshTotals() {
        const taxBase = calculateTaxBase();
        let grandTotal = 0;
        const summary = [];
        groupsRoot.querySelectorAll('[data-group]').forEach((group) => {
            let groupTotal = 0;
            group.querySelectorAll('[data-item]').forEach((item) => {
                const tax = detectTaxItem(item);
                const taxFlag = item.querySelector('[data-is-tax]');
                const taxRate = item.querySelector('[data-tax-rate]');
                const price = item.querySelector('[data-unit-price]');
                const actual = item.querySelector('[data-actual-total]');
                item.dataset.taxItem = tax ? 'true' : 'false';
                item.classList.toggle('tax-row', tax);
                if (taxFlag) taxFlag.value = tax ? 1 : 0;
                if (tax) {
                    const rate = extractRate(item);
                    const total = taxBase * numericValue(item.querySelector('[data-quantity]')) * rate;
                    if (taxRate) taxRate.value = rate;
                    if (price) { price.value = taxBase.toFixed(2); price.readOnly = true; price.title = '税基自动汇总，不可手工修改'; }
                    if (actual) { actual.value = total.toFixed(2); actual.readOnly = true; actual.title = '税额自动计算，不可手工修改'; }
                    const note = item.querySelector('input[name$="[note]"]');
                    if (note && !note.value) note.placeholder = '税基自动汇总 / 税额自动计算';
                    groupTotal += total;
                } else {
                    if (price) { price.readOnly = false; price.title = ''; }
                    if (actual && actual.value === '') actual.value = calculatedOrdinaryTotal(item).toFixed(2);
                    if (actual) { actual.readOnly = false; actual.title = '可手工填写；留空则按数量 × 单价计算'; }
                    groupTotal += ordinaryItemTotal(item);
                }
            });
            grandTotal += groupTotal;
            group.querySelector('[data-group-total]').textContent = money(groupTotal);
            const groupName = group.querySelector('.group-title input:not([type="hidden"])')?.value || '未命名分组';
            summary.push(`<div class="summary-line"><span>${escapeHtml(groupName)}小计</span><strong>${money(groupTotal)}</strong></div>`);
        });
        editor.querySelector('[data-summary-groups]').innerHTML = summary.join('');
        editor.querySelector('[data-grand-total]').textContent = money(grandTotal);
        const people = numericValue(editor.querySelector('[data-people-count]')) || 1;
        editor.querySelector('[data-per-person]').textContent = money(grandTotal / people);
    }

    function itemTemplate(groupIndex, itemIndex) {
        const base = `groups[${groupIndex}][items][${itemIndex}]`;
        return `<div class="item-grid" data-item data-tax-item="false" data-item-index="${itemIndex}">
            <button class="item-drag-handle" type="button" data-drag-handle data-tooltip="拖动排序" aria-label="拖动第 ${itemIndex + 1} 项排序"><span aria-hidden="true"></span></button>
            <span class="item-number">${itemIndex + 1}</span>
            <input name="${base}[time]" aria-label="预估时段" placeholder="如：09:00-11:30">
            <input name="${base}[name]" aria-label="项目名称" data-item-name required placeholder="新增项目">
            <input name="${base}[unit]" aria-label="单位" data-item-unit placeholder="位 / 围 / 晚">
            <input type="number" step="0.01" name="${base}[quantity]" value="0" aria-label="数量" data-quantity>
            <input type="number" step="0.01" name="${base}[unit_price]" value="0" aria-label="单价" data-unit-price>
            <input type="number" step="0.01" name="${base}[actual_total]" aria-label="实际总价" data-actual-total placeholder="自动计算">
            <input name="${base}[note]" aria-label="备注" placeholder="备注 / 其他">
            <input type="hidden" name="${base}[is_tax]" value="0" data-is-tax><input type="hidden" name="${base}[tax_rate]" value="0" data-tax-rate><input type="hidden" name="${base}[sort_order]" value="${itemIndex}">
            <button class="icon-btn danger" type="button" data-remove-item data-tooltip="删除项目" aria-label="删除项目"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6"/></svg></button>
        </div>`;
    }

    function groupTemplate(groupIndex, dayNumber) {
        return `<section class="quote-group" data-group data-group-index="${groupIndex}">
            <header class="group-head"><div class="group-title"><span class="group-dot"></span><input name="groups[${groupIndex}][name]" value="DAY ${String(dayNumber).padStart(2, '0')}" aria-label="分组名称" required><input type="hidden" name="groups[${groupIndex}][type]" value="day"><input type="hidden" name="groups[${groupIndex}][sort_order]" value="${groupIndex}"></div><div class="group-head-actions"><span>小计 <strong data-group-total>¥0.00</strong></span><button class="icon-btn danger" type="button" data-remove-group data-tooltip="删除分组" aria-label="删除分组"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6"/></svg></button></div></header>
            <div class="items-editor" data-items><div class="item-grid item-header"><span aria-hidden="true"></span><span>#</span><span>预估时段</span><span>项目名称</span><span>单位</span><span>数量</span><span>单价</span><span>实际总价</span><span>备注 / 其他</span><span></span></div>${itemTemplate(groupIndex, 0)}</div>
            <footer class="group-foot"><button class="btn ghost small" type="button" data-add-item><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>添加项目</button></footer>
        </section>`;
    }

    editor.addEventListener('click', (event) => {
        const addGroup = event.target.closest('[data-add-group]');
        if (addGroup) {
            const groups = Array.from(groupsRoot.querySelectorAll('[data-group]'));
            const dayNumber = groups.filter((group) => group.querySelector('input[name$="[type]"]')?.value === 'day').length + 1;
            const otherGroup = groups.find((group) => group.querySelector('input[name$="[type]"]')?.value === 'other');
            if (otherGroup) otherGroup.insertAdjacentHTML('beforebegin', groupTemplate(groups.length, dayNumber));
            else groupsRoot.insertAdjacentHTML('beforeend', groupTemplate(groups.length, dayNumber));
            updateNames(); initializeItemSortables(); refreshTotals();
            return;
        }
        const addItem = event.target.closest('[data-add-item]');
        if (addItem) {
            const group = addItem.closest('[data-group]');
            const groupIndex = Array.from(groupsRoot.querySelectorAll('[data-group]')).indexOf(group);
            const items = group.querySelectorAll('[data-item]');
            group.querySelector('[data-items]').insertAdjacentHTML('beforeend', itemTemplate(groupIndex, items.length));
            updateNames(); refreshTotals();
            return;
        }
        const removeItem = event.target.closest('[data-remove-item]');
        if (removeItem) {
            removeItem.closest('[data-item]').remove(); updateNames(); refreshTotals();
            return;
        }
        const removeGroup = event.target.closest('[data-remove-group]');
        if (removeGroup) {
            if (groupsRoot.querySelectorAll('[data-group]').length === 1) {
                window.showWorkspaceToast?.('至少保留一个报价分组', 'error'); return;
            }
            removeGroup.closest('[data-group]').remove(); updateNames(); refreshTotals();
        }
    });

    editor.addEventListener('input', (event) => {
        const item = event.target.closest('[data-item]');
        const wasTax = item?.dataset.taxItem === 'true';
        if (item && event.target.matches('[data-item-name]')) {
            const taxFlag = item.querySelector('[data-is-tax]');
            if (taxFlag) taxFlag.value = hasTaxName(item) ? '1' : '0';
        }
        if (item && (event.target.matches('[data-quantity]') || event.target.matches('[data-unit-price]'))) {
            const actual = item.querySelector('[data-actual-total]');
            if (actual && !detectTaxItem(item)) actual.value = '';
        }
        if (item && wasTax && !detectTaxItem(item)) {
            const actual = item.querySelector('[data-actual-total]');
            if (actual) actual.value = '';
        }
        refreshTotals();
    });
    moveOtherGroupsToEnd();
    updateNames();
    initializeItemSortables();
    refreshTotals();
    window.quoteEditor = { calculateTaxBase, refreshTotals };
})();
