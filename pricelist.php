<?php
/**
 * @name        Прайс-лист
 * @icon        📦
 * @description Все цены, быстрый поиск, печать PDF
 * @version     1.0
 * @sidebar     true
 * @color       #0ea5e9
 */
if (!isset($moduleDB['pricelist'])) {
    $moduleDB['pricelist'] = [
        ['id'=>1,'category'=>'Фотопечать','name'=>'Фото 10×15','unit'=>'шт','price'=>15,'desc'=>'Глянцевая/матовая'],
        ['id'=>2,'category'=>'Фотопечать','name'=>'Фото 15×21','unit'=>'шт','price'=>35,'desc'=>''],
        ['id'=>3,'category'=>'Фотопечать','name'=>'Фото А4 (21×30)','unit'=>'шт','price'=>80,'desc'=>''],
        ['id'=>4,'category'=>'Фотопечать','name'=>'Фото А3 (30×42)','unit'=>'шт','price'=>150,'desc'=>''],
        ['id'=>5,'category'=>'Копирование','name'=>'Ч/Б копия А4','unit'=>'лист','price'=>5,'desc'=>'Одностороннее'],
        ['id'=>6,'category'=>'Копирование','name'=>'Цветная копия А4','unit'=>'лист','price'=>20,'desc'=>''],
        ['id'=>7,'category'=>'Копирование','name'=>'Ч/Б копия А3','unit'=>'лист','price'=>12,'desc'=>''],
        ['id'=>8,'category'=>'Копирование','name'=>'Цветная копия А3','unit'=>'лист','price'=>45,'desc'=>''],
        ['id'=>9,'category'=>'Широкий формат','name'=>'Баннерная печать','unit'=>'м²','price'=>1200,'desc'=>'Frontlit'],
        ['id'=>10,'category'=>'Широкий формат','name'=>'Плёнка самоклейка','unit'=>'м²','price'=>1200,'desc'=>'Винил'],
        ['id'=>11,'category'=>'Широкий формат','name'=>'Roll-Up 0.85×2м','unit'=>'шт','price'=>3500,'desc'=>'Печать + механизм'],
        ['id'=>12,'category'=>'Ламинация','name'=>'Ламинация А4','unit'=>'лист','price'=>60,'desc'=>'125 мкм'],
        ['id'=>13,'category'=>'Ламинация','name'=>'Ламинация А3','unit'=>'лист','price'=>100,'desc'=>''],
        ['id'=>14,'category'=>'Переплёт','name'=>'Переплёт пружина А4','unit'=>'шт','price'=>120,'desc'=>''],
        ['id'=>15,'category'=>'Переплёт','name'=>'Переплёт клей (термо)','unit'=>'шт','price'=>180,'desc'=>''],
        ['id'=>16,'category'=>'Визитки','name'=>'Визитки 90×50','unit'=>'100шт','price'=>450,'desc'=>'Цифровая печать'],
        ['id'=>17,'category'=>'Дизайн','name'=>'Разработка макета','unit'=>'шт','price'=>1500,'desc'=>''],
        ['id'=>18,'category'=>'Дизайн','name'=>'Правки макета','unit'=>'шт','price'=>300,'desc'=>'1 правка'],
    ];
    writeDB($moduleDB);
}

switch ($moduleAction) {
    case 'list':
        echo json_encode(['ok' => true, 'data' => $moduleDB['pricelist']]);
        break;

    case 'add':
        $item = [
            'id'       => time() . rand(100,999),
            'category' => $moduleBody['category'] ?? 'Прочее',
            'name'     => $moduleBody['name']     ?? '',
            'unit'     => $moduleBody['unit']     ?? 'шт',
            'price'    => floatval($moduleBody['price'] ?? 0),
            'desc'     => $moduleBody['desc']     ?? '',
        ];
        $moduleDB['pricelist'][] = $item;
        writeDB($moduleDB);
        echo json_encode(['ok' => true, 'data' => $item]);
        break;

    case 'update':
        $id = $moduleBody['id'] ?? null;
        foreach ($moduleDB['pricelist'] as &$item) {
            if ((string)$item['id'] === (string)$id) {
                $item = array_merge($item, $moduleBody);
                break;
            }
        }
        writeDB($moduleDB);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $id = $_GET['id'] ?? $moduleBody['id'] ?? null;
        $moduleDB['pricelist'] = array_values(array_filter($moduleDB['pricelist'], fn($i) => (string)$i['id'] !== (string)$id));
        writeDB($moduleDB);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => true, 'data' => []]);
}
?>
<!--MODULE_JS_START-->
<script>
CRM.registerModule({
  id: 'pricelist', name: 'Прайс-лист', icon: '📦', color: '#0ea5e9',

  _items: [],
  _editId: null,

  page: `
    <div class="page-header">
      <div>
        <div class="page-title">📦 Прайс-лист</div>
        <div class="page-subtitle">Все цены на услуги — быстрый поиск и печать</div>
      </div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-secondary btn-sm" onclick="CRM.modules.pricelist.printPrice()">🖨️ Распечатать</button>
        <button class="btn btn-primary btn-sm"   onclick="CRM.modules.pricelist.openAddModal()">+ Добавить позицию</button>
      </div>
    </div>

    <!-- Поиск -->
    <div style="display:flex;gap:8px;margin-bottom:20px;">
      <div class="search-bar" style="flex:1;">
        <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Найти услугу..." id="price_search" oninput="CRM.modules.pricelist.search()">
      </div>
      <select class="form-select" style="width:180px;" id="price_cat_filter" onchange="CRM.modules.pricelist.search()">
        <option value="">Все категории</option>
      </select>
    </div>

    <div id="price_list"></div>

    <!-- Модал добавления -->
    <div class="modal-overlay" id="priceAddModal">
      <div class="modal modal-sm">
        <div class="modal-header">
          <div class="modal-title" id="price_modal_title">+ Добавить позицию</div>
          <button class="modal-close" onclick="closeModal('priceAddModal')">✕</button>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Категория</label>
            <input class="form-input" id="price_add_cat" placeholder="Фотопечать" list="price_cats_dl">
            <datalist id="price_cats_dl"></datalist>
          </div>
          <div class="form-group">
            <label class="form-label">Единица</label>
            <select class="form-select" id="price_add_unit">
              <option>шт</option><option>лист</option><option>м²</option>
              <option>п.м.</option><option>100шт</option><option>500шт</option><option>услуга</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Наименование</label>
          <input class="form-input" id="price_add_name" placeholder="Фото 10×15">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Цена ₽</label>
            <input class="form-input" type="number" id="price_add_price" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Примечание</label>
            <input class="form-input" id="price_add_desc" placeholder="Глянец/матовый">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('priceAddModal')">Отмена</button>
          <button class="btn btn-primary" id="price_modal_btn" onclick="CRM.modules.pricelist.saveItem()">💾 Добавить</button>
        </div>
      </div>
    </div>
  `,

  async render() {
    const res    = await CRM.api('pricelist', 'list');
    this._items  = res?.data || [];
    this._updateFilters();
    this.search();
  },

  _updateFilters() {
    const cats = [...new Set(this._items.map(i => i.category))];
    const sel  = document.getElementById('price_cat_filter');
    if (sel) {
      sel.innerHTML = '<option value="">Все категории</option>' +
        cats.map(c => `<option>${c}</option>`).join('');
    }
    const dl = document.getElementById('price_cats_dl');
    if (dl) dl.innerHTML = cats.map(c => `<option value="${c}">`).join('');
  },

  search() {
    const q   = (document.getElementById('price_search')?.value || '').toLowerCase();
    const cat = document.getElementById('price_cat_filter')?.value || '';
    let items = this._items;
    if (q)   items = items.filter(i => i.name.toLowerCase().includes(q) || (i.desc||'').toLowerCase().includes(q));
    if (cat) items = items.filter(i => i.category === cat);
    this._renderItems(items);
  },

  _renderItems(items) {
    const cats = [...new Set(items.map(i => i.category))];
    const list = document.getElementById('price_list');
    if (!list) return;

    list.innerHTML = cats.map(cat => `
      <div style="margin-bottom:20px;">
        <div class="section-label">${cat}</div>
        <div class="card" style="padding:0;overflow:hidden;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr>
                <th style="padding:10px 16px;text-align:left;font-size:0.72rem;color:var(--text-muted);background:var(--bg-card2);">Наименование</th>
                <th style="padding:10px 16px;text-align:left;font-size:0.72rem;color:var(--text-muted);background:var(--bg-card2);">Примечание</th>
                <th style="padding:10px 16px;text-align:center;font-size:0.72rem;color:var(--text-muted);background:var(--bg-card2);">Ед.</th>
                <th style="padding:10px 16px;text-align:right;font-size:0.72rem;color:var(--text-muted);background:var(--bg-card2);">Цена</th>
                <th style="padding:10px 16px;background:var(--bg-card2);width:110px;"></th>
              </tr>
            </thead>
            <tbody>
              ${items.filter(i => i.category === cat).map(i => `
                <tr style="border-bottom:1px solid rgba(45,53,86,0.5);" onmouseenter="this.style.background='rgba(124,58,237,0.05)'" onmouseleave="this.style.background=''">
                  <td style="padding:10px 16px;font-weight:600;">${this._escapeHtml(i.name)}</td>
                  <td style="padding:10px 16px;font-size:0.78rem;color:var(--text-muted);">${this._escapeHtml(i.desc||'—')}</td>
                  <td style="padding:10px 16px;text-align:center;font-size:0.78rem;color:var(--text-muted);">${i.unit}</td>
                  <td style="padding:10px 16px;text-align:right;font-weight:800;font-size:1rem;color:var(--accent3);">${formatMoney(i.price)}</td>
                  <td style="padding:10px 16px;">
                    <div style="display:flex;gap:4px;justify-content:flex-end;">
                      <button class="btn btn-primary btn-xs" onclick="CRM.modules.pricelist.useInOrder(${JSON.stringify(i).replace(/"/g,'&quot;')})" title="Использовать в заказе">📋</button>
                      <button class="btn btn-secondary btn-xs" onclick="CRM.modules.pricelist.openEditModal('${i.id}')" title="Редактировать">✏️</button>
                      <button class="btn btn-danger btn-xs"  onclick="CRM.modules.pricelist.deleteItem('${i.id}')" title="Удалить">🗑️</button>
                    </div>
                   </td>
                 </tr>
              `).join('')}
            </tbody>
           </table>
        </div>
      </div>
    `).join('');
  },

  _escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
      if (m === '&') return '&amp;';
      if (m === '<') return '&lt;';
      if (m === '>') return '&gt;';
      return m;
    });
  },

  openAddModal() {
    this._editId = null;
    document.getElementById('price_modal_title').innerText = '+ Добавить позицию';
    document.getElementById('price_modal_btn').innerHTML = '💾 Добавить';
    document.getElementById('price_add_cat').value = '';
    document.getElementById('price_add_name').value = '';
    document.getElementById('price_add_unit').value = 'шт';
    document.getElementById('price_add_price').value = '';
    document.getElementById('price_add_desc').value = '';
    openModal('priceAddModal');
  },

  openEditModal(id) {
    const item = this._items.find(i => String(i.id) === String(id));
    if (!item) return;
    
    this._editId = id;
    document.getElementById('price_modal_title').innerText = '✏️ Редактировать позицию';
    document.getElementById('price_modal_btn').innerHTML = '💾 Сохранить';
    document.getElementById('price_add_cat').value = item.category || '';
    document.getElementById('price_add_name').value = item.name || '';
    document.getElementById('price_add_unit').value = item.unit || 'шт';
    document.getElementById('price_add_price').value = item.price || 0;
    document.getElementById('price_add_desc').value = item.desc || '';
    openModal('priceAddModal');
  },

  async saveItem() {
    const name  = document.getElementById('price_add_name').value.trim();
    const price = parseFloat(document.getElementById('price_add_price').value);
    if (!name || !price) { notify('Заполните название и цену', 'error'); return; }
    
    const data = {
      category: document.getElementById('price_add_cat').value   || 'Прочее',
      unit:     document.getElementById('price_add_unit').value  || 'шт',
      desc:     document.getElementById('price_add_desc').value  || '',
      name, price
    };
    
    if (this._editId) {
      data.id = this._editId;
      await CRM.api('pricelist', 'update', data);
      notify('Позиция обновлена', 'success');
    } else {
      await CRM.api('pricelist', 'add', data);
      notify('Позиция добавлена', 'success');
    }
    
    closeModal('priceAddModal');
    this.render();
  },

  useInOrder(item) {
    openModal('orderModal');
    setTimeout(() => {
      document.getElementById('ord_comment').value = item.name + ' — ' + formatMoney(item.price) + '/' + item.unit;
      document.getElementById('ord_total').value   = item.price;
      if (typeof updateTotalDisplay === 'function') updateTotalDisplay();
      notify('✅ Позиция из прайса добавлена в заказ', 'success');
    }, 200);
  },

  async deleteItem(id) {
    if (!confirm('Удалить позицию?')) return;
    await CRM.api('pricelist', 'delete', null, {id});
    notify('Позиция удалена', 'success');
    this.render();
  },

  printPrice() {
    const cats = [...new Set(this._items.map(i => i.category))];
    const db   = getDB();
    const s    = db.settings || {};
    const html = `<html><head><style>
      body{font-family:Arial,sans-serif;font-size:12px;padding:20px;}
      h1{font-size:20px;margin-bottom:4px;} .sub{color:#666;margin-bottom:20px;}
      h2{font-size:14px;margin:16px 0 6px;background:#f0f0f0;padding:6px 10px;border-radius:4px;}
      table{width:100%;border-collapse:collapse;margin-bottom:12px;}
      td,th{border:1px solid #ddd;padding:6px 10px;}
      th{background:#f8f8f8;font-weight:bold;} .price{font-weight:bold;text-align:right;}
    </style></head><body>
    <h1>${s.company || 'Прайс-лист'}</h1>
    <div class="sub">${s.address||''} • ${s.phone||''} • Актуально на ${new Date().toLocaleDateString('ru')}</div>
    ${cats.map(cat => `
      <h2>${cat}</h2>
      <table>
        <tr><th>Наименование</th><th>Примечание</th><th>Ед.</th><th>Цена, ₽</th></tr>
        ${this._items.filter(i=>i.category===cat).map(i=>`
          <tr><td>${i.name}</td><td>${i.desc||'—'}</td><td style="text-align:center">${i.unit}</td><td class="price">${i.price}</td></tr>
        `).join('')}
      </table>
    `).join('')}
    <script>window.onload=()=>window.print()<\/script></body></html>`;
    const w = window.open('','_blank');
    w.document.write(html);
    w.document.close();
  }
});
</script>