<?php
/**
 * @name        Склад PRO
 * @icon        🏭
 * @description Управление складом, материалами, остатками и расходом для типографии
 * @version     3.0
 * @sidebar     true
 * @color       #10b981
 */

if (!isset($moduleDB['wpro_items'])) {
    $moduleDB['wpro_items'] = [
        ['id'=>'w1','sku'=>'БУМ-А4-80','name'=>'Бумага А4 80г/м²','category'=>'Бумага','brand'=>'SvetoCopy','unit'=>'пачка','unit_coeff'=>500,'unit_coeff_label'=>'листов','cost'=>320,'price'=>450,'qty'=>50,'reserved'=>0,'min_qty'=>10,'location'=>'Стеллаж 1, Полка 1','desc'=>'Офисная бумага','weight'=>2.5,'color'=>'белый','size'=>'А4','material'=>'бумага','photo'=>'','barcode'=>'4607143102345','created_at'=>date('Y-m-d'),'batches'=>[]],
        ['id'=>'w2','sku'=>'БУМ-А3-80','name'=>'Бумага А3 80г/м²','category'=>'Бумага','brand'=>'SvetoCopy','unit'=>'пачка','unit_coeff'=>250,'unit_coeff_label'=>'листов','cost'=>580,'price'=>750,'qty'=>20,'reserved'=>0,'min_qty'=>5,'location'=>'Стеллаж 1, Полка 2','desc'=>'','weight'=>2.4,'color'=>'белый','size'=>'А3','material'=>'бумага','photo'=>'','barcode'=>'','created_at'=>date('Y-m-d'),'batches'=>[]],
        ['id'=>'w3','sku'=>'МЕЛ-А4-130','name'=>'Бумага мелованная А4 130г/м²','category'=>'Бумага','brand'=>'Nautilus','unit'=>'пачка','unit_coeff'=>250,'unit_coeff_label'=>'листов','cost'=>890,'price'=>1200,'qty'=>15,'reserved'=>0,'min_qty'=>5,'location'=>'Стеллаж 1, Полка 3','desc'=>'Глянцевая/матовая','weight'=>3.2,'color'=>'белый','size'=>'А4','material'=>'мелованная','photo'=>'','barcode'=>'','created_at'=>date('Y-m-d'),'batches'=>[]],
        ['id'=>'w4','sku'=>'ТОН-ЧБ-01','name'=>'Тонер Ч/Б HP 85A','category'=>'Расходники','brand'=>'HP','unit'=>'шт','unit_coeff'=>1600,'unit_coeff_label'=>'страниц','cost'=>1200,'price'=>1800,'qty'=>8,'reserved'=>0,'min_qty'=>2,'location'=>'Стеллаж 2, Полка 1','desc'=>'Для HP LaserJet P1102','weight'=>0.7,'color'=>'чёрный','size'=>'','material'=>'','photo'=>'','barcode'=>'','created_at'=>date('Y-m-d'),'batches'=>[]],
        ['id'=>'w5','sku'=>'ЛАМ-А4-125','name'=>'Плёнка ламинационная А4 125мкм','category'=>'Расходники','brand'=>'Fellows','unit'=>'уп','unit_coeff'=>100,'unit_coeff_label'=>'листов','cost'=>650,'price'=>900,'qty'=>10,'reserved'=>0,'min_qty'=>3,'location'=>'Стеллаж 2, Полка 2','desc'=>'Глянец','weight'=>0.5,'color'=>'прозрачный','size'=>'А4','material'=>'полиэстер','photo'=>'','barcode'=>'','created_at'=>date('Y-m-d'),'batches'=>[]],
    ];
    writeDB($moduleDB);
}
if (!isset($moduleDB['wpro_movements']))    { $moduleDB['wpro_movements']    = []; writeDB($moduleDB); }
if (!isset($moduleDB['wpro_reservations'])) { $moduleDB['wpro_reservations'] = []; writeDB($moduleDB); }
if (!isset($moduleDB['wpro_categories']))   {
    $moduleDB['wpro_categories'] = [
        ['id'=>'cat1','name'=>'Бумага',      'icon'=>'📄','color'=>'#3b82f6','desc'=>'Офисная, мелованная, дизайнерская'],
        ['id'=>'cat2','name'=>'Расходники',  'icon'=>'🖨️','color'=>'#8b5cf6','desc'=>'Тонер, картриджи, чернила'],
        ['id'=>'cat3','name'=>'Плёнки',      'icon'=>'✨','color'=>'#06b6d4','desc'=>'Ламинационные плёнки'],
        ['id'=>'cat4','name'=>'Переплёт',    'icon'=>'📚','color'=>'#f59e0b','desc'=>'Пружины, термоклей, обложки'],
        ['id'=>'cat5','name'=>'Баннерная',   'icon'=>'🎪','color'=>'#10b981','desc'=>'Баннерная ткань, сетка'],
        ['id'=>'cat6','name'=>'Прочее',      'icon'=>'📦','color'=>'#6b7280','desc'=>'Разное'],
    ];
    writeDB($moduleDB);
}

function wpro_log($action, $data) {
    global $moduleDB;
    $moduleDB['wpro_movements'][] = [
        'id'         => 'mv'.time().rand(100,999),
        'action'     => $action,
        'data'       => $data,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function wpro_stats(&$items) {
    $total_cost=0; $low=0; $out=0;
    foreach ($items as $i) {
        $total_cost += (($i['qty']??0)-($i['reserved']??0)) * ($i['cost']??0);
        if (($i['qty']??0) <= ($i['min_qty']??0) && ($i['qty']??0) > 0) $low++;
        if (($i['qty']??0) <= 0) $out++;
    }
    return ['total_cost'=>$total_cost,'low_stock'=>$low,'out_stock'=>$out,'total_items'=>count($items)];
}

function wpro_calc($service, $qty) {
    $c = []; $s = mb_strtolower($service);
    if (str_contains($s,'фото 10')||str_contains($s,'10x15')||str_contains($s,'10×15'))
        $c[] = ['sku'=>'БУМ-А4-80','name'=>'Бумага А4','need_sheets'=>$qty,'unit'=>'листов'];
    if (str_contains($s,'фото а4')||str_contains($s,'21×30'))
        $c[] = ['sku'=>'БУМ-А4-80','name'=>'Бумага А4','need_sheets'=>$qty,'unit'=>'листов'];
    if (str_contains($s,'фото а3')||str_contains($s,'30×42'))
        $c[] = ['sku'=>'БУМ-А3-80','name'=>'Бумага А3','need_sheets'=>$qty,'unit'=>'листов'];
    if (str_contains($s,'копия а4')||str_contains($s,'копирование а4')) {
        $c[] = ['sku'=>'БУМ-А4-80','name'=>'Бумага А4','need_sheets'=>$qty,'unit'=>'листов'];
        $c[] = ['sku'=>'ТОН-ЧБ-01','name'=>'Тонер','need_sheets'=>$qty,'unit'=>'страниц'];
    }
    if (str_contains($s,'ламинация а4'))
        $c[] = ['sku'=>'ЛАМ-А4-125','name'=>'Плёнка А4','need_sheets'=>$qty,'unit'=>'листов'];
    if (str_contains($s,'ламинация а3'))
        $c[] = ['sku'=>'ЛАМ-А4-125','name'=>'Плёнка А4','need_sheets'=>$qty*2,'unit'=>'листов'];
    if (str_contains($s,'визит'))
        $c[] = ['sku'=>'МЕЛ-А4-130','name'=>'Мелованная А4','need_sheets'=>(int)ceil($qty/2),'unit'=>'листов'];
    return $c;
}

switch ($moduleAction) {
    case 'list':
        $items = $moduleDB['wpro_items'] ?? [];
        $cat   = $_GET['category'] ?? '';
        $q     = $_GET['search']   ?? '';
        $st    = $_GET['status']   ?? '';
        if ($cat) $items = array_values(array_filter($items, fn($i)=>$i['category']===$cat));
        if ($q)   $items = array_values(array_filter($items, fn($i)=>mb_stripos($i['name'],$q)!==false||mb_stripos($i['sku'],$q)!==false));
        if ($st==='low') $items = array_values(array_filter($items, fn($i)=>($i['qty']??0)<=($i['min_qty']??0)&&($i['qty']??0)>0));
        if ($st==='out') $items = array_values(array_filter($items, fn($i)=>($i['qty']??0)<=0));
        echo json_encode(['ok'=>true,'data'=>$items,'stats'=>wpro_stats($moduleDB['wpro_items']),'categories'=>$moduleDB['wpro_categories']??[]]);
        break;

    case 'get':
        $id=$_GET['id']??$moduleBody['id']??null; $found=null;
        foreach ($moduleDB['wpro_items'] as $i) { if ((string)$i['id']===(string)$id){$found=$i;break;} }
        echo json_encode(['ok'=>(bool)$found,'data'=>$found]);
        break;

    case 'save':
        $id=$moduleBody['id']??null; $now=date('Y-m-d H:i:s');
        if ($id) {
            foreach ($moduleDB['wpro_items'] as &$item) {
                if ((string)$item['id']===(string)$id) {
                    $old=$item['qty']; $item=array_merge($item,$moduleBody); $item['updated_at']=$now;
                    if(($item['qty']??0)!=$old) wpro_log('adjust',['item_id'=>$id,'sku'=>$item['sku'],'from'=>$old,'to'=>$item['qty']]);
                    break;
                }
            }
            writeDB($moduleDB); echo json_encode(['ok'=>true,'msg'=>'Обновлено']);
        } else {
            $newId='w'.time().rand(100,999);
            $item=['id'=>$newId,'sku'=>$moduleBody['sku']??strtoupper(substr(md5($newId),0,6)),
                'name'=>$moduleBody['name']??'','category'=>$moduleBody['category']??'Прочее',
                'brand'=>$moduleBody['brand']??'','unit'=>$moduleBody['unit']??'шт',
                'unit_coeff'=>intval($moduleBody['unit_coeff']??1),
                'unit_coeff_label'=>$moduleBody['unit_coeff_label']??'',
                'cost'=>floatval($moduleBody['cost']??0),'price'=>floatval($moduleBody['price']??0),
                'qty'=>floatval($moduleBody['qty']??0),'reserved'=>0,
                'min_qty'=>floatval($moduleBody['min_qty']??0),
                'location'=>$moduleBody['location']??'','desc'=>$moduleBody['desc']??'',
                'weight'=>floatval($moduleBody['weight']??0),'color'=>$moduleBody['color']??'',
                'size'=>$moduleBody['size']??'','material'=>$moduleBody['material']??'',
                'photo'=>$moduleBody['photo']??'','barcode'=>$moduleBody['barcode']??'',
                'batches'=>[],'created_at'=>date('Y-m-d')];
            $moduleDB['wpro_items'][]=$item;
            wpro_log('add',['item_id'=>$newId,'sku'=>$item['sku'],'name'=>$item['name'],'qty'=>$item['qty']]);
            writeDB($moduleDB); echo json_encode(['ok'=>true,'data'=>$item]);
        }
        break;

    case 'delete':
        $id=$_GET['id']??$moduleBody['id']??null;
        $moduleDB['wpro_items']=array_values(array_filter($moduleDB['wpro_items'],fn($i)=>(string)$i['id']!==(string)$id));
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'income':
        $id=floatval($moduleBody['item_id']??null); $qty=floatval($moduleBody['qty']??0);
        $note=$moduleBody['note']??''; $batch=$moduleBody['batch']??'';
        foreach ($moduleDB['wpro_items'] as &$item) {
            if ((string)$item['id']===(string)($moduleBody['item_id']??null)) {
                $item['qty']+=$qty;
                if ($batch) $item['batches'][]=['batch'=>$batch,'qty'=>$qty,'date'=>date('Y-m-d'),'note'=>$note];
                wpro_log('income',['item_id'=>$moduleBody['item_id'],'sku'=>$item['sku'],'qty'=>$qty,'note'=>$note]);
                break;
            }
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'writeoff':
        $id=$moduleBody['item_id']??null; $qty=floatval($moduleBody['qty']??0);
        foreach ($moduleDB['wpro_items'] as &$item) {
            if ((string)$item['id']===(string)$id) {
                if(($item['qty']??0)<$qty){echo json_encode(['ok'=>false,'msg'=>'Недостаточно остатка']);exit;}
                $item['qty']-=$qty;
                wpro_log('writeoff',['item_id'=>$id,'sku'=>$item['sku'],'qty'=>$qty,'reason'=>$moduleBody['reason']??'Списание']);
                break;
            }
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'reserve':
        $id=$moduleBody['item_id']??null; $qty=floatval($moduleBody['qty']??0);
        foreach ($moduleDB['wpro_items'] as &$item) {
            if ((string)$item['id']===(string)$id) {
                $avail=($item['qty']??0)-($item['reserved']??0);
                if($avail<$qty){echo json_encode(['ok'=>false,'msg'=>"Доступно только $avail {$item['unit']}"]);exit;}
                $item['reserved']=($item['reserved']??0)+$qty;
                $moduleDB['wpro_reservations'][]=['id'=>'res'.time().rand(100,999),'item_id'=>$id,
                    'sku'=>$item['sku'],'name'=>$item['name'],'qty'=>$qty,
                    'order_id'=>$moduleBody['order_id']??null,'client'=>$moduleBody['client']??'',
                    'status'=>'active','created_at'=>date('Y-m-d H:i:s')];
                wpro_log('reserve',['item_id'=>$id,'sku'=>$item['sku'],'qty'=>$qty]);
                break;
            }
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'unreserve':
        $res_id=$moduleBody['res_id']??null;
        foreach ($moduleDB['wpro_reservations'] as &$res) {
            if ((string)$res['id']===(string)$res_id && $res['status']==='active') {
                $res['status']='cancelled';
                foreach ($moduleDB['wpro_items'] as &$item) {
                    if ((string)$item['id']===(string)$res['item_id']) {
                        $item['reserved']=max(0,($item['reserved']??0)-$res['qty']); break;
                    }
                }
                break;
            }
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'movements':
        $id=$_GET['item_id']??null; $mvs=$moduleDB['wpro_movements']??[];
        if ($id) $mvs=array_values(array_filter($mvs,fn($m)=>($m['data']['item_id']??null)===$id));
        echo json_encode(['ok'=>true,'data'=>array_slice(array_reverse($mvs),0,300)]);
        break;

    case 'calc':
        $service=$moduleBody['service']??''; $qty=intval($moduleBody['qty']??1);
        $result=wpro_calc($service,$qty);
        foreach ($result as &$row) {
            foreach ($moduleDB['wpro_items'] as $i) {
                if ($i['sku']===$row['sku']) {
                    $avail=($i['qty']??0)-($i['reserved']??0); $coeff=$i['unit_coeff']??1;
                    $row['available']=$avail; $row['available_sheets']=$avail*$coeff;
                    $row['need_packs']=$coeff>1?ceil($row['need_sheets']/$coeff):null;
                    $row['enough']=($avail*$coeff)>=$row['need_sheets'];
                    $row['item_id']=$i['id']; break;
                }
            }
            if (!isset($row['enough'])) { $row['available']=0; $row['available_sheets']=0; $row['enough']=false; }
        }
        echo json_encode(['ok'=>true,'data'=>$result]);
        break;

    case 'save_photo':
        $id=$moduleBody['id']??null; $url=$moduleBody['photo']??'';
        foreach ($moduleDB['wpro_items'] as &$item) {
            if ((string)$item['id']===(string)$id) { $item['photo']=$url; break; }
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'reservations':
        $id=$_GET['item_id']??null; $res=$moduleDB['wpro_reservations']??[];
        if ($id) $res=array_values(array_filter($res,fn($r)=>$r['item_id']===$id&&$r['status']==='active'));
        else     $res=array_values(array_filter($res,fn($r)=>$r['status']==='active'));
        echo json_encode(['ok'=>true,'data'=>$res]);
        break;

    case 'inventory':
        $data=$moduleBody['items']??[]; $report=[];
        foreach ($data as $row) {
            $id=$row['id']??null; $actual=floatval($row['actual']??0);
            foreach ($moduleDB['wpro_items'] as &$item) {
                if ((string)$item['id']===(string)$id) {
                    $diff=$actual-$item['qty'];
                    $report[]=['sku'=>$item['sku'],'name'=>$item['name'],'system'=>$item['qty'],'actual'=>$actual,'diff'=>$diff];
                    wpro_log('inventory',['item_id'=>$id,'sku'=>$item['sku'],'system'=>$item['qty'],'actual'=>$actual,'diff'=>$diff]);
                    $item['qty']=$actual; break;
                }
            }
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true,'report'=>$report]);
        break;

    case 'get_categories':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['wpro_categories']??[]]);
        break;

    case 'save_category':
        $id=$moduleBody['id']??null;
        if ($id) {
            foreach ($moduleDB['wpro_categories'] as &$cat) {
                if ((string)$cat['id']===(string)$id) { $cat=array_merge($cat,$moduleBody); break; }
            }
        } else {
            $moduleDB['wpro_categories'][]=['id'=>'cat'.time().rand(10,99),
                'name'=>$moduleBody['name']??'Новая','icon'=>$moduleBody['icon']??'📦',
                'color'=>$moduleBody['color']??'#6b7280','desc'=>$moduleBody['desc']??''];
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'delete_category':
        $id=$_GET['id']??$moduleBody['id']??null;
        $moduleDB['wpro_categories']=array_values(array_filter($moduleDB['wpro_categories'],fn($c)=>(string)$c['id']!==(string)$id));
        writeDB($moduleDB); echo json_encode(['ok'=>true]);
        break;

    case 'import_csv':
        $rows=$moduleBody['rows']??[]; $added=0; $errors=[];
        foreach ($rows as $row) {
            if (empty($row['name'])) continue;
            $newId='w'.time().rand(100,999).rand(10,99);
            $moduleDB['wpro_items'][]=['id'=>$newId,
                'sku'=>$row['sku']??strtoupper(substr(md5($newId),0,6)),
                'name'=>$row['name'],'category'=>$row['category']??'Прочее',
                'brand'=>$row['brand']??'','unit'=>$row['unit']??'шт',
                'unit_coeff'=>intval($row['unit_coeff']??1),
                'unit_coeff_label'=>$row['unit_coeff_label']??'',
                'cost'=>floatval($row['cost']??0),'price'=>floatval($row['price']??0),
                'qty'=>floatval($row['qty']??0),'reserved'=>0,
                'min_qty'=>floatval($row['min_qty']??0),
                'location'=>$row['location']??'','desc'=>$row['desc']??'',
                'weight'=>floatval($row['weight']??0),'color'=>$row['color']??'',
                'size'=>$row['size']??'','material'=>$row['material']??'',
                'photo'=>$row['photo']??'','barcode'=>$row['barcode']??'',
                'batches'=>[],'created_at'=>date('Y-m-d')];
            $added++;
        }
        writeDB($moduleDB); echo json_encode(['ok'=>true,'added'=>$added,'errors'=>$errors]);
        break;

    case 'stats':
        $items=$moduleDB['wpro_items']??[]; $cats=[];
        foreach ($items as $i) {
            $c=$i['category'];
            if (!isset($cats[$c])) $cats[$c]=['count'=>0,'qty'=>0,'cost'=>0];
            $cats[$c]['count']++; $cats[$c]['qty']+=$i['qty']; $cats[$c]['cost']+=$i['qty']*($i['cost']??0);
        }
        usort($items,fn($a,$b)=>($b['qty']*($b['cost']??0))-($a['qty']*($a['cost']??0)));
        echo json_encode(['ok'=>true,'stats'=>wpro_stats($moduleDB['wpro_items']),'categories'=>$cats,'top5'=>array_slice($items,0,5)]);
        break;

    default:
        echo json_encode(['ok'=>true,'data'=>[]]);
}
?>
<!--MODULE_JS_START-->
<script>
(function() {
  // Сбрасываем старый page-warehouse_pro если там чужой HTML
  const _ex = document.getElementById('page-warehouse_pro');
  if (_ex && !_ex.innerHTML.includes('wpro_content')) {
    _ex.innerHTML = '';
  }
})();

CRM.registerModule({
  id: 'warehouse_pro',
  name: 'Склад PRO',
  icon: '🏭',
  color: '#10b981',

  _items: [],
  _stats: {},
  _cats: [],
  _view: 'dashboard', // dashboard | list | map | movements | settings
  _activeCat: '',
  _editId: null,

  page: `
<div class="page-header">
  <div>
    <div class="page-title">🏭 Склад PRO</div>
    <div class="page-subtitle" id="wpro_subtitle">Загрузка...</div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('movements')">📊 Движения</button>
    <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('map')">🗺️ Карта</button>
    <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('settings')">⚙️ Настройки</button>
    <button class="btn btn-primary btn-sm"   onclick="CRM.modules.warehouse_pro._openAdd()">+ Добавить</button>
  </div>
</div>

<!-- Табы -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--bg-card2);border-radius:10px;padding:4px;width:fit-content;">
  <button id="wpro_tab_dashboard" class="btn btn-primary btn-sm" onclick="CRM.modules.warehouse_pro._setView('dashboard')">🏠 Дашборд</button>
  <button id="wpro_tab_list"      class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('list')">☰ Список</button>
  <button id="wpro_tab_map"       class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('map')">🗺️ Карта</button>
  <button id="wpro_tab_settings"  class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('settings')">⚙️ Настройки</button>
</div>

<!-- Статистика -->
<div id="wpro_stat_row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;"></div>

<!-- Поиск (только для list) -->
<div id="wpro_search_bar" style="display:none;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
  <div class="search-bar" style="flex:1;min-width:180px;">
    <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="Поиск по названию, артикулу..." id="wpro_search" oninput="CRM.modules.warehouse_pro._applyFilters()">
  </div>
  <select class="form-select" style="width:160px;" id="wpro_cat_f" onchange="CRM.modules.warehouse_pro._applyFilters()">
    <option value="">Все категории</option>
  </select>
  <select class="form-select" style="width:140px;" id="wpro_status_f" onchange="CRM.modules.warehouse_pro._applyFilters()">
    <option value="">Все</option>
    <option value="low">⚠️ Мало</option>
    <option value="out">🔴 Нет</option>
  </select>
</div>

<!-- Основной контент -->
<div id="wpro_content"></div>

<!-- ══ МОДАЛЫ ══ -->

<!-- Добавить/редактировать товар -->
<div class="modal-overlay" id="wproAddModal">
  <div class="modal" style="max-width:680px;max-height:90vh;overflow-y:auto;">
    <div class="modal-header">
      <div class="modal-title" id="wpro_modal_title">+ Добавить материал</div>
      <button class="modal-close" onclick="closeModal('wproAddModal')">✕</button>
    </div>
    <div style="text-align:center;margin-bottom:16px;">
      <div id="wpro_photo_prev" onclick="CRM.modules.warehouse_pro._triggerPhoto()"
           style="width:120px;height:120px;border-radius:12px;border:2px dashed var(--border);background:var(--bg-card2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto;overflow:hidden;">📷</div>
      <input type="file" id="wpro_photo_input" accept="image/*" style="display:none" onchange="CRM.modules.warehouse_pro._uploadPhoto(this)">
      <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">Нажмите для загрузки фото</div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Артикул (SKU)</label><input class="form-input" id="wpro_sku" placeholder="БУМ-А4-80"></div>
      <div class="form-group"><label class="form-label">Категория</label><input class="form-input" id="wpro_category" list="wpro_cats_dl" placeholder="Бумага"><datalist id="wpro_cats_dl"></datalist></div>
    </div>
    <div class="form-group"><label class="form-label">Наименование *</label><input class="form-input" id="wpro_name" placeholder="Бумага А4 80г/м²"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Бренд</label><input class="form-input" id="wpro_brand" placeholder="SvetoCopy"></div>
      <div class="form-group"><label class="form-label">Единица</label>
        <select class="form-select" id="wpro_unit"><option>шт</option><option>пачка</option><option>рулон</option><option>уп</option><option>кг</option><option>л</option><option>м</option><option>м²</option><option>п.м.</option></select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Кол-во в упаковке</label><input class="form-input" type="number" id="wpro_coeff" value="1"></div>
      <div class="form-group"><label class="form-label">Что внутри (листов...)</label><input class="form-input" id="wpro_coeff_label" placeholder="листов"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Себестоимость ₽</label><input class="form-input" type="number" id="wpro_cost" placeholder="320"></div>
      <div class="form-group"><label class="form-label">Цена продажи ₽</label><input class="form-input" type="number" id="wpro_price" placeholder="450"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Остаток</label><input class="form-input" type="number" id="wpro_qty" value="0"></div>
      <div class="form-group"><label class="form-label">Мин. остаток</label><input class="form-input" type="number" id="wpro_min_qty" value="5"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Цвет</label><input class="form-input" id="wpro_color" placeholder="белый"></div>
      <div class="form-group"><label class="form-label">Размер</label><input class="form-input" id="wpro_size" placeholder="А4"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Материал</label><input class="form-input" id="wpro_material"></div>
      <div class="form-group"><label class="form-label">Вес (кг)</label><input class="form-input" type="number" step="0.01" id="wpro_weight"></div>
    </div>
    <div class="form-group"><label class="form-label">Место хранения</label><input class="form-input" id="wpro_location" placeholder="Стеллаж 1, Полка 2"></div>
    <div class="form-group"><label class="form-label">Штрихкод / EAN</label><input class="form-input" id="wpro_barcode"></div>
    <div class="form-group"><label class="form-label">Описание</label><textarea class="form-input" id="wpro_desc" rows="2"></textarea></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('wproAddModal')">Отмена</button>
      <button class="btn btn-primary" id="wpro_modal_btn" onclick="CRM.modules.warehouse_pro._saveItem()">💾 Сохранить</button>
    </div>
  </div>
</div>

<!-- Детальная карточка -->
<div class="modal-overlay" id="wproDetailModal">
  <div class="modal" style="max-width:720px;max-height:92vh;overflow-y:auto;" id="wpro_detail_body"></div>
</div>

<!-- Приход/Списание -->
<div class="modal-overlay" id="wproOpModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title" id="wpro_op_title">Операция</div>
      <button class="modal-close" onclick="closeModal('wproOpModal')">✕</button>
    </div>
    <input type="hidden" id="wpro_op_type">
    <input type="hidden" id="wpro_op_item_id">
    <div class="form-group"><label class="form-label">Количество</label><input class="form-input" type="number" step="0.01" id="wpro_op_qty"></div>
    <div class="form-group" id="wpro_op_batch_wrap">
      <label class="form-label">Партия / накладная</label>
      <input class="form-input" id="wpro_op_batch" placeholder="ПАРТИЯ-2024-01">
    </div>
    <div class="form-group"><label class="form-label">Комментарий</label><input class="form-input" id="wpro_op_note"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('wproOpModal')">Отмена</button>
      <button class="btn btn-primary" onclick="CRM.modules.warehouse_pro._submitOp()">✅ Выполнить</button>
    </div>
  </div>
</div>

<!-- Расчёт расхода -->
<div class="modal-overlay" id="wproCalcModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">🧮 Расчёт расхода материалов</div>
      <button class="modal-close" onclick="closeModal('wproCalcModal')">✕</button>
    </div>
    <div class="form-group">
      <label class="form-label">Услуга</label>
      <input class="form-input" id="wpro_calc_svc" list="wpro_svc_dl">
      <datalist id="wpro_svc_dl">
        <option value="Ч/Б копия А4"><option value="Цветная копия А4">
        <option value="Ч/Б копия А3"><option value="Ламинация А4">
        <option value="Ламинация А3"><option value="Фото А4">
        <option value="Фото 10×15"><option value="Фото А3">
        <option value="Визитки 90×50">
      </datalist>
    </div>
    <div class="form-group"><label class="form-label">Количество</label><input class="form-input" type="number" id="wpro_calc_qty" value="100"></div>
    <button class="btn btn-primary" style="width:100%;margin-bottom:12px;" onclick="CRM.modules.warehouse_pro._doCalc()">🔍 Рассчитать</button>
    <div id="wpro_calc_result"></div>
  </div>
</div>

<!-- Инвентаризация -->
<div class="modal-overlay" id="wproInvModal">
  <div class="modal" style="max-width:700px;">
    <div class="modal-header">
      <div class="modal-title">📋 Инвентаризация</div>
      <button class="modal-close" onclick="closeModal('wproInvModal')">✕</button>
    </div>
    <div id="wpro_inv_body" style="max-height:55vh;overflow-y:auto;"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('wproInvModal')">Отмена</button>
      <button class="btn btn-primary" onclick="CRM.modules.warehouse_pro._submitInventory()">✅ Сохранить</button>
    </div>
  </div>
</div>

<!-- Импорт CSV/Excel -->
<div class="modal-overlay" id="wproImportModal">
  <div class="modal" style="max-width:700px;">
    <div class="modal-header">
      <div class="modal-title">📥 Импорт товаров</div>
      <button class="modal-close" onclick="closeModal('wproImportModal')">✕</button>
    </div>
    <div style="margin-bottom:16px;">
      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._importTab('file')" id="imp_tab_file">📄 Файл CSV/Excel</button>
        <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._importTab('url')"  id="imp_tab_url">🌐 По URL</button>
        <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._importTab('manual')" id="imp_tab_manual">✏️ Вручную</button>
      </div>
      <div id="imp_tab_file_body">
        <div style="border:2px dashed var(--border);border-radius:10px;padding:24px;text-align:center;cursor:pointer;" onclick="document.getElementById('wpro_import_file').click()">
          <div style="font-size:2rem;margin-bottom:8px;">📄</div>
          <div style="font-weight:600;">Нажмите для выбора файла</div>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">CSV, XLSX — кодировка UTF-8</div>
        </div>
        <input type="file" id="wpro_import_file" accept=".csv,.xlsx,.xls" style="display:none" onchange="CRM.modules.warehouse_pro._parseImportFile(this)">
      </div>
      <div id="imp_tab_url_body" style="display:none;">
        <div class="form-group">
          <label class="form-label">URL файла или страницы с ценами</label>
          <input class="form-input" id="wpro_import_url" placeholder="https://supplier.ru/pricelist.csv">
        </div>
        <button class="btn btn-primary btn-sm" onclick="CRM.modules.warehouse_pro._importFromUrl()">📥 Загрузить</button>
      </div>
      <div id="imp_tab_manual_body" style="display:none;">
        <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">Вставьте данные в формате CSV (разделитель ;)</div>
        <textarea class="form-input" id="wpro_import_manual" rows="8" placeholder="sku;name;category;unit;qty;cost;price;min_qty;location&#10;БУМ-А4-80;Бумага А4 80г;Бумага;пачка;50;320;450;10;Стеллаж 1"></textarea>
        <button class="btn btn-primary btn-sm" style="margin-top:8px;" onclick="CRM.modules.warehouse_pro._parseManualCSV()">📋 Разобрать</button>
      </div>
    </div>
    <div id="wpro_import_preview" style="max-height:40vh;overflow-y:auto;"></div>
    <div class="modal-footer" id="wpro_import_footer" style="display:none;">
      <button class="btn btn-secondary" onclick="closeModal('wproImportModal')">Отмена</button>
      <button class="btn btn-primary" onclick="CRM.modules.warehouse_pro._submitImport()">✅ Импортировать</button>
    </div>
  </div>
</div>
`,

  // ══ RENDER ════════════════════════════════════════════════════════════════
  async render() {
    if (!document.getElementById('wpro_content')) {
      const p = document.getElementById('page-warehouse_pro');
      if (p) p.innerHTML = this.page;
    }
    try {
      const res    = await CRM.api('warehouse_pro', 'list');
      this._items  = res?.data       || [];
      this._stats  = res?.stats      || {};
      this._cats   = res?.categories || [];
      this._fillFilters();
      this._renderStats();
      this._setView(this._view);
    } catch(e) {
      console.error('warehouse_pro render error', e);
      const c = document.getElementById('wpro_content');
      if (c) c.innerHTML = '<div class="empty-state">Ошибка загрузки: ' + e.message + '</div>';
    }
  },

  _setView(v) {
    this._view = v;
    // Табы
    ['dashboard','list','map','settings'].forEach(t => {
      const el = document.getElementById('wpro_tab_'+t);
      if (el) {
        el.className = t === v ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm';
      }
    });
    // Поиск
    const sb = document.getElementById('wpro_search_bar');
    if (sb) sb.style.display = v === 'list' ? 'flex' : 'none';
    // Рендер
    if      (v === 'dashboard') this._renderDashboard();
    else if (v === 'list')      this._applyFilters();
    else if (v === 'map')       this._renderMap(this._items);
    else if (v === 'movements') this._openMovements();
    else if (v === 'settings')  this._renderSettings();
  },

  _fillFilters() {
    const cats = [...new Set(this._items.map(i => i.category))];
    const sel  = document.getElementById('wpro_cat_f');
    const dl   = document.getElementById('wpro_cats_dl');
    if (sel) sel.innerHTML = '<option value="">Все категории</option>' + cats.map(c=>`<option>${c}</option>`).join('');
    if (dl)  dl.innerHTML  = cats.map(c=>`<option value="${c}">`).join('');
  },

  _renderStats() {
    const s   = this._stats;
    const row = document.getElementById('wpro_stat_row');
    const sub = document.getElementById('wpro_subtitle');
    if (sub) sub.textContent = `${s.total_items||0} позиций • Склад: ${formatMoney(s.total_cost||0)} ₽`;
    if (!row) return;
    row.innerHTML = `
    <div class="stat-card">
      <div class="stat-card-label">Позиций</div>
      <div class="stat-card-value">${s.total_items||0}</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-label">Стоимость склада</div>
      <div class="stat-card-value" style="color:var(--accent3)">${formatMoney(s.total_cost||0)} ₽</div>
    </div>
    <div class="stat-card" style="cursor:pointer;" onclick="document.getElementById('wpro_status_f').value='low';CRM.modules.warehouse_pro._setView('list')">
      <div class="stat-card-label">⚠️ Мало на складе</div>
      <div class="stat-card-value" style="color:#f59e0b">${s.low_stock||0}</div>
    </div>
    <div class="stat-card" style="cursor:pointer;" onclick="document.getElementById('wpro_status_f').value='out';CRM.modules.warehouse_pro._setView('list')">
      <div class="stat-card-label">🔴 Нет в наличии</div>
      <div class="stat-card-value" style="color:#ef4444">${s.out_stock||0}</div>
    </div>`;
  },

  // ══ ДАШБОРД — категории + товары ══════════════════════════════════════════
  _renderDashboard() {
    const cont = document.getElementById('wpro_content');
    if (!cont) return;

    const catColors = {};
    this._cats.forEach(c => { catColors[c.name] = {color: c.color, icon: c.icon}; });

    // Группируем товары по категориям
    const grouped = {};
    this._items.forEach(i => {
      if (!grouped[i.category]) grouped[i.category] = [];
      grouped[i.category].push(i);
    });

    const cats = Object.keys(grouped);
    if (!cats.length) {
      cont.innerHTML = `<div class="empty-state">
        <div style="font-size:3rem;margin-bottom:12px;">📦</div>
        <div style="font-weight:600;margin-bottom:8px;">Склад пуст</div>
        <button class="btn btn-primary" onclick="CRM.modules.warehouse_pro._openAdd()">+ Добавить первый товар</button>
      </div>`;
      return;
    }

    // Алерты — мало/нет
    const alerts = this._items.filter(i => (i.qty||0) <= (i.min_qty||0));
    let alertHtml = '';
    if (alerts.length) {
      alertHtml = `<div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <span style="font-size:1.2rem;">⚠️</span>
        <span style="font-weight:600;color:#f59e0b;">Требуют пополнения:</span>
        ${alerts.map(i=>`<span style="background:rgba(245,158,11,.2);color:#f59e0b;padding:2px 10px;border-radius:20px;font-size:0.78rem;cursor:pointer;" onclick="CRM.modules.warehouse_pro._openDetail('${i.id}')">${this._esc(i.name)} — ${i.qty} ${i.unit}</span>`).join('')}
      </div>`;
    }

    cont.innerHTML = alertHtml + cats.map(cat => {
      const ci   = grouped[cat];
      const meta = catColors[cat] || {color:'#6b7280', icon:'📦'};
      const lowCnt = ci.filter(i=>(i.qty||0)<=(i.min_qty||0)&&(i.qty||0)>0).length;
      const outCnt = ci.filter(i=>(i.qty||0)<=0).length;

      return `
      <div style="margin-bottom:32px;">
        <!-- Заголовок категории -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:42px;height:42px;border-radius:10px;background:${meta.color}22;display:flex;align-items:center;justify-content:center;font-size:1.4rem;border:1px solid ${meta.color}44;">${meta.icon}</div>
            <div>
              <div style="font-weight:700;font-size:1rem;">${this._esc(cat)}</div>
              <div style="font-size:0.72rem;color:var(--text-muted);">${ci.length} позиций
                ${outCnt>0?` • <span style="color:#ef4444;">${outCnt} нет</span>`:''}
                ${lowCnt>0?` • <span style="color:#f59e0b;">${lowCnt} мало</span>`:''}
              </div>
            </div>
          </div>
          <button class="btn btn-secondary btn-xs" onclick="CRM.modules.warehouse_pro._filterCat('${this._esc(cat)}')">Все товары →</button>
        </div>

        <!-- Карточки товаров -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
          ${ci.map(i => this._dashCard(i, meta.color)).join('')}
        </div>
      </div>`;
    }).join('');
  },

  _dashCard(i, catColor) {
    const avail = (i.qty||0) - (i.reserved||0);
    const color = avail<=0 ? '#ef4444' : avail<=(i.min_qty||0) ? '#f59e0b' : '#10b981';
    const pct   = (i.min_qty||0)>0 ? Math.min(100, (avail/(i.min_qty||1))*100) : 100;
    const photo = i.photo
      ? `<div style="width:100%;height:110px;overflow:hidden;border-radius:10px 10px 0 0;">
           <img src="${i.photo}" style="width:100%;height:110px;object-fit:cover;">
         </div>`
      : `<div style="width:100%;height:80px;background:linear-gradient(135deg,${catColor}22,${catColor}11);border-radius:10px 10px 0 0;display:flex;align-items:center;justify-content:center;font-size:2.2rem;">📦</div>`;

    return `
    <div style="background:var(--bg-card);border-radius:12px;overflow:hidden;border:1px solid var(--border);cursor:pointer;transition:transform .15s,box-shadow .15s;"
         onmouseenter="this.style.transform='translateY(-3px)';this.style.boxShadow='0 10px 30px rgba(0,0,0,.3)'"
         onmouseleave="this.style.transform='';this.style.boxShadow=''"
         onclick="CRM.modules.warehouse_pro._openDetail('${i.id}')">
      ${photo}
      <div style="padding:10px 12px;">
        <div style="font-size:0.62rem;color:var(--text-muted);margin-bottom:2px;font-family:monospace;">${this._esc(i.sku)}</div>
        <div style="font-weight:700;font-size:0.83rem;line-height:1.3;margin-bottom:6px;">${this._esc(i.name)}</div>
        ${i.brand?`<div style="font-size:0.68rem;color:var(--text-muted);margin-bottom:4px;">${this._esc(i.brand)}</div>`:''}
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
          <span style="font-size:1rem;font-weight:800;color:${color};">${avail} <span style="font-size:0.72rem;">${i.unit}</span></span>
          <span style="font-size:0.65rem;padding:2px 7px;border-radius:20px;background:${color}22;color:${color};">
            ${avail<=0?'Нет':avail<=(i.min_qty||0)?'Мало':'OK'}
          </span>
        </div>
        <div style="height:3px;border-radius:2px;background:var(--bg-card2);overflow:hidden;margin-bottom:8px;">
          <div style="height:100%;width:${Math.min(100,Math.max(0,pct))}%;background:${color};border-radius:2px;transition:width .4s;"></div>
        </div>
        ${i.unit_coeff>1?`<div style="font-size:0.65rem;color:var(--text-muted);margin-bottom:6px;">${avail*i.unit_coeff} ${i.unit_coeff_label}</div>`:''}
        <div style="display:flex;gap:3px;" onclick="event.stopPropagation()">
          <button class="btn btn-primary btn-xs" style="flex:1;" onclick="CRM.modules.warehouse_pro._openOp('income','${i.id}')">⬆️</button>
          <button class="btn btn-danger btn-xs"  style="flex:1;" onclick="CRM.modules.warehouse_pro._openOp('writeoff','${i.id}')">⬇️</button>
          <button class="btn btn-secondary btn-xs" onclick="CRM.modules.warehouse_pro._openEdit('${i.id}')">✏️</button>
        </div>
      </div>
    </div>`;
  },

  _filterCat(cat) {
    const sel = document.getElementById('wpro_cat_f');
    if (sel) sel.value = cat;
    this._setView('list');
  },

  // ══ СПИСОК ════════════════════════════════════════════════════════════════
  _applyFilters() {
    const q   = (document.getElementById('wpro_search')?.value  || '').toLowerCase();
    const cat = document.getElementById('wpro_cat_f')?.value     || '';
    const st  = document.getElementById('wpro_status_f')?.value  || '';
    let items = this._items;
    if (q)          items = items.filter(i => i.name.toLowerCase().includes(q) || (i.sku||'').toLowerCase().includes(q));
    if (cat)        items = items.filter(i => i.category === cat);
    if (st==='low') items = items.filter(i => (i.qty||0)<=(i.min_qty||0) && (i.qty||0)>0);
    if (st==='out') items = items.filter(i => (i.qty||0)<=0);
    this._renderList(items);
  },

  _renderList(items) {
    const cont = document.getElementById('wpro_content');
    if (!cont) return;
    if (!items.length) { cont.innerHTML='<div class="empty-state">Ничего не найдено</div>'; return; }
    cont.innerHTML = `
    <div class="card" style="padding:0;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr>
          <th style="padding:10px 12px;text-align:left;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);width:50px;"></th>
          <th style="padding:10px 12px;text-align:left;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Артикул</th>
          <th style="padding:10px 12px;text-align:left;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Наименование</th>
          <th style="padding:10px 12px;text-align:left;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Категория</th>
          <th style="padding:10px 12px;text-align:right;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Остаток</th>
          <th style="padding:10px 12px;text-align:right;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Доступно</th>
          <th style="padding:10px 12px;text-align:right;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Себест.</th>
          <th style="padding:10px 12px;text-align:left;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Место</th>
          <th style="padding:10px 12px;background:var(--bg-card2);width:120px;"></th>
        </tr></thead>
        <tbody>
          ${items.map(i => {
            const avail = (i.qty||0)-(i.reserved||0);
            const color = avail<=0?'#ef4444':avail<=(i.min_qty||0)?'#f59e0b':'#10b981';
            return `<tr style="border-bottom:1px solid rgba(45,53,86,.5);"
                       onmouseenter="this.style.background='rgba(16,185,129,.04)'"
                       onmouseleave="this.style.background=''">
              <td style="padding:8px 12px;">
                ${i.photo
                  ? `<img src="${i.photo}" style="width:38px;height:38px;object-fit:cover;border-radius:6px;">`
                  : `<div style="width:38px;height:38px;border-radius:6px;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📦</div>`}
              </td>
              <td style="padding:8px 12px;font-family:monospace;font-size:0.72rem;color:var(--text-muted);">${this._esc(i.sku)}</td>
              <td style="padding:8px 12px;font-weight:600;cursor:pointer;" onclick="CRM.modules.warehouse_pro._openDetail('${i.id}')">${this._esc(i.name)}</td>
              <td style="padding:8px 12px;font-size:0.78rem;color:var(--text-muted);">${this._esc(i.category)}</td>
              <td style="padding:8px 12px;text-align:right;font-weight:700;">${i.qty} <span style="font-size:0.68rem;color:var(--text-muted);">${i.unit}</span></td>
              <td style="padding:8px 12px;text-align:right;font-weight:700;color:${color};">${avail} <span style="font-size:0.68rem;">${i.unit}</span></td>
              <td style="padding:8px 12px;text-align:right;color:var(--accent3);">${formatMoney(i.cost||0)}</td>
              <td style="padding:8px 12px;font-size:0.72rem;color:var(--text-muted);">${this._esc(i.location||'—')}</td>
              <td style="padding:8px 12px;">
                <div style="display:flex;gap:3px;justify-content:flex-end;">
                  <button class="btn btn-primary btn-xs"   onclick="CRM.modules.warehouse_pro._openOp('income','${i.id}')"   title="Приход">⬆️</button>
                  <button class="btn btn-danger btn-xs"    onclick="CRM.modules.warehouse_pro._openOp('writeoff','${i.id}')" title="Списание">⬇️</button>
                  <button class="btn btn-secondary btn-xs" onclick="CRM.modules.warehouse_pro._openEdit('${i.id}')"          title="Редактировать">✏️</button>
                  <button class="btn btn-secondary btn-xs" onclick="CRM.modules.warehouse_pro._openCalc('${this._esc(i.name)}')" title="Расчёт">🧮</button>
                </div>
              </td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    </div>`;
  },

  // ══ КАРТА СКЛАДА ══════════════════════════════════════════════════════════
  _renderMap(items) {
    const cont = document.getElementById('wpro_content');
    if (!cont) return;
    const zones = {};
    items.forEach(i => {
      const loc = (i.location||'Без места').split(',')[0].trim();
      if (!zones[loc]) zones[loc] = [];
      zones[loc].push(i);
    });
    cont.innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;">
      ${Object.entries(zones).map(([zone, zi]) => {
        const low = zi.filter(i=>(i.qty||0)<=(i.min_qty||0)&&(i.qty||0)>0).length;
        const out = zi.filter(i=>(i.qty||0)<=0).length;
        const zc  = out>0?'#ef4444':low>0?'#f59e0b':'#10b981';
        return `
        <div class="card" style="border-left:4px solid ${zc};padding:14px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:10px;">
            <div style="font-weight:700;">📦 ${this._esc(zone)}</div>
            <div style="width:10px;height:10px;border-radius:50%;background:${zc};margin-top:4px;"></div>
          </div>
          ${zi.map(i=>{
            const av=(i.qty||0)-(i.reserved||0);
            const c=av<=0?'#ef4444':av<=(i.min_qty||0)?'#f59e0b':'#10b981';
            return `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(45,53,86,.25);cursor:pointer;" onclick="CRM.modules.warehouse_pro._openDetail('${i.id}')">
              <div>
                <div style="font-size:0.78rem;font-weight:600;">${this._esc(i.name)}</div>
                <div style="font-size:0.62rem;color:var(--text-muted);">${this._esc(i.sku)}</div>
              </div>
              <div style="font-weight:800;color:${c};font-size:0.88rem;">${av} ${i.unit}</div>
            </div>`;
          }).join('')}
          <div style="margin-top:8px;font-size:0.7rem;color:${zc};">
            ${out>0?`🔴 ${out} отсутствует`:low>0?`⚠️ ${low} пополнить`:'✅ Норма'}
          </div>
        </div>`;
      }).join('')}
    </div>`;
  },

  // ══ НАСТРОЙКИ ═════════════════════════════════════════════════════════════
  _renderSettings() {
    const cont = document.getElementById('wpro_content');
    if (!cont) return;
    cont.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

      <!-- Категории -->
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <div style="font-weight:700;font-size:0.95rem;">🏷️ Категории</div>
          <button class="btn btn-primary btn-xs" onclick="CRM.modules.warehouse_pro._addCatPrompt()">+ Добавить</button>
        </div>
        <div id="wpro_cats_list">
          ${this._cats.map(c=>`
          <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(45,53,86,.3);">
            <div style="width:32px;height:32px;border-radius:8px;background:${c.color}22;display:flex;align-items:center;justify-content:center;font-size:1.1rem;border:1px solid ${c.color}44;">${c.icon}</div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.85rem;">${this._esc(c.name)}</div>
              <div style="font-size:0.68rem;color:var(--text-muted);">${this._esc(c.desc||'')}</div>
            </div>
            <button class="btn btn-danger btn-xs" onclick="CRM.modules.warehouse_pro._deleteCat('${c.id}')">🗑️</button>
          </div>`).join('')}
        </div>
      </div>

      <!-- Импорт -->
      <div class="card">
        <div style="font-weight:700;font-size:0.95rem;margin-bottom:16px;">📥 Импорт товаров</div>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <button class="btn btn-secondary" onclick="CRM.modules.warehouse_pro._openImport()">
            📄 Импорт из CSV / Excel
          </button>
          <button class="btn btn-secondary" onclick="CRM.modules.warehouse_pro._openImport('url')">
            🌐 Загрузить по URL
          </button>
          <button class="btn btn-secondary" onclick="CRM.modules.warehouse_pro._printInventory()">
            📋 Инвентаризация
          </button>
          <button class="btn btn-secondary" onclick="CRM.modules.warehouse_pro._openCalc('')">
            🧮 Расчёт расхода
          </button>
        </div>
      </div>

      <!-- Инструкция по полям -->
      <div class="card" style="grid-column:1/-1;">
        <div style="font-weight:700;font-size:0.95rem;margin-bottom:16px;">📖 Инструкция — поля товара</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
          ${[
            ['sku','Артикул (SKU)','Уникальный код товара. Пример: БУМ-А4-80'],
            ['name','Наименование','Полное название. Пример: Бумага А4 80г/м²'],
            ['category','Категория','Группа товара: Бумага, Расходники, Плёнки...'],
            ['brand','Бренд','Производитель. Пример: SvetoCopy, HP'],
            ['unit','Единица','шт, пачка, рулон, уп, кг, л, м, м², п.м.'],
            ['unit_coeff','Коэффициент','Кол-во в упаковке. Пачка = 500 листов'],
            ['cost','Себестоимость','Закупочная цена за единицу'],
            ['price','Цена продажи','Цена для клиента'],
            ['qty','Остаток','Текущее количество на складе'],
            ['min_qty','Мин. остаток','При падении ниже — красный сигнал'],
            ['location','Место хранения','Стеллаж 1, Полка 2 — для кладовщика'],
            ['barcode','Штрихкод / EAN','Для сканера. Пример: 4607143102345'],
            ['color','Цвет','Белый, синий, прозрачный...'],
            ['size','Размер','А4, А3, 90×50 мм...'],
            ['material','Материал','Бумага, полиэстер, винил...'],
            ['weight','Вес (кг)','Вес одной единицы'],
            ['photo','Фото','URL фотографии товара'],
            ['desc','Описание','Дополнительное описание'],
          ].map(([f,n,d])=>`
          <div style="background:var(--bg-card2);border-radius:8px;padding:10px 12px;">
            <div style="font-family:monospace;font-size:0.7rem;color:var(--accent3);margin-bottom:3px;">${f}</div>
            <div style="font-weight:600;font-size:0.8rem;margin-bottom:2px;">${n}</div>
            <div style="font-size:0.72rem;color:var(--text-muted);">${d}</div>
          </div>`).join('')}
        </div>

        <!-- Формат CSV -->
        <div style="margin-top:16px;background:var(--bg-card2);border-radius:8px;padding:12px;">
          <div style="font-weight:600;font-size:0.85rem;margin-bottom:8px;">📄 Формат CSV для импорта</div>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:6px;">Разделитель: точка с запятой (;), кодировка: UTF-8, первая строка — заголовки</div>
          <code style="font-size:0.72rem;color:var(--accent3);display:block;background:rgba(0,0,0,.3);padding:8px;border-radius:6px;white-space:pre;">sku;name;category;unit;unit_coeff;unit_coeff_label;cost;price;qty;min_qty;location;barcode;brand;color;size;material;weight;desc
БУМ-А4-80;Бумага А4 80г/м²;Бумага;пачка;500;листов;320;450;50;10;Стеллаж 1, Полка 1;4607143102345;SvetoCopy;белый;А4;бумага;2.5;Офисная бумага
ТОН-ЧБ-01;Тонер Ч/Б HP 85A;Расходники;шт;1600;страниц;1200;1800;8;2;Стеллаж 2;;HP;чёрный;;;0.7;</code>
        </div>
      </div>
    </div>`;
  },

  async _addCatPrompt() {
    const name  = prompt('Название категории:');
    if (!name) return;
    const icon  = prompt('Иконка (эмодзи):', '📦');
    const color = prompt('Цвет (hex):', '#6b7280');
    const desc  = prompt('Описание:', '');
    await CRM.api('warehouse_pro','save_category',{name,icon:icon||'📦',color:color||'#6b7280',desc:desc||''});
    notify('Категория добавлена ✅','success');
    this.render();
  },

  async _deleteCat(id) {
    if (!confirm('Удалить категорию?')) return;
    await CRM.api('warehouse_pro','delete_category',null,{id});
    notify('Удалено','success');
    this.render();
  },

  // ══ ДЕТАЛЬНАЯ КАРТОЧКА ════════════════════════════════════════════════════
  async _openDetail(id) {
    const item = this._items.find(i => String(i.id)===String(id));
    if (!item) return;
    const avail   = (item.qty||0)-(item.reserved||0);
    const color   = avail<=0?'#ef4444':avail<=(item.min_qty||0)?'#f59e0b':'#10b981';
    const resRes  = await CRM.api('warehouse_pro','reservations',null,{item_id:id});
    const resList = resRes?.data||[];

    document.getElementById('wpro_detail_body').innerHTML = `
    <div class="modal-header">
      <div class="modal-title">${this._esc(item.name)}</div>
      <button class="modal-close" onclick="closeModal('wproDetailModal')">✕</button>
    </div>
    <div style="display:grid;grid-template-columns:170px 1fr;gap:20px;margin-bottom:16px;align-items:start;">
      <div>
        <div onclick="CRM.modules.warehouse_pro._editId='${item.id}';CRM.modules.warehouse_pro._triggerPhoto()"
             style="width:170px;height:170px;border-radius:12px;overflow:hidden;background:var(--bg-card2);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:3rem;">
          ${item.photo?`<img src="${item.photo}" style="width:170px;height:170px;object-fit:cover;">`:'📷'}
        </div>
        <button class="btn btn-secondary btn-xs" style="width:170px;margin-top:6px;"
                onclick="CRM.modules.warehouse_pro._editId='${item.id}';CRM.modules.warehouse_pro._triggerPhoto()">📷 Обновить фото</button>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 14px;font-size:0.82rem;">
        <div><span style="color:var(--text-muted);">Артикул:</span> <b style="font-family:monospace;">${this._esc(item.sku)}</b></div>
        <div><span style="color:var(--text-muted);">Категория:</span> ${this._esc(item.category)}</div>
        <div><span style="color:var(--text-muted);">Бренд:</span> ${this._esc(item.brand||'—')}</div>
        <div><span style="color:var(--text-muted);">Единица:</span> ${item.unit}</div>
        ${item.unit_coeff>1?`<div><span style="color:var(--text-muted);">В упак.:</span> ${item.unit_coeff} ${this._esc(item.unit_coeff_label||'')}</div>`:''}
        <div><span style="color:var(--text-muted);">Место:</span> ${this._esc(item.location||'—')}</div>
        ${item.color?`<div><span style="color:var(--text-muted);">Цвет:</span> ${this._esc(item.color)}</div>`:''}
        ${item.size?`<div><span style="color:var(--text-muted);">Размер:</span> ${this._esc(item.size)}</div>`:''}
        ${item.material?`<div><span style="color:var(--text-muted);">Материал:</span> ${this._esc(item.material)}</div>`:''}
        ${item.barcode?`<div style="grid-column:1/-1;"><span style="color:var(--text-muted);">Штрихкод:</span> <code style="font-size:0.73rem;">${this._esc(item.barcode)}</code></div>`:''}
        ${item.desc?`<div style="grid-column:1/-1;color:var(--text-muted);">${this._esc(item.desc)}</div>`:''}
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:12px;">
      <div class="stat-card">
        <div class="stat-card-label">Всего на складе</div>
        <div class="stat-card-value">${item.qty} ${item.unit}</div>
        ${item.unit_coeff>1?`<div style="font-size:0.7rem;color:var(--text-muted);">${(item.qty*(item.unit_coeff||1)).toLocaleString()} ${this._esc(item.unit_coeff_label||'')}</div>`:''}
      </div>
      <div class="stat-card">
        <div class="stat-card-label">🔒 Резерв</div>
        <div class="stat-card-value" style="color:#f59e0b;">${item.reserved||0} ${item.unit}</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Доступно</div>
        <div class="stat-card-value" style="color:${color};">${avail} ${item.unit}</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
      <div class="stat-card">
        <div class="stat-card-label">Себестоимость</div>
        <div class="stat-card-value" style="color:var(--accent3);">${formatMoney(item.cost||0)} ₽</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-label">Сумма на складе</div>
        <div class="stat-card-value">${formatMoney((item.qty||0)*(item.cost||0))} ₽</div>
      </div>
    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
      <button class="btn btn-primary btn-sm"   onclick="CRM.modules.warehouse_pro._openOp('income','${item.id}')">⬆️ Оприходовать</button>
      <button class="btn btn-danger btn-sm"    onclick="CRM.modules.warehouse_pro._openOp('writeoff','${item.id}')">⬇️ Списать</button>
      <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._openCalc('${this._esc(item.name)}')">🧮 Расчёт расхода</button>
      <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._printLabel('${item.id}')">🏷️ Этикетка</button>
      <button class="btn btn-secondary btn-sm" onclick="closeModal('wproDetailModal');CRM.modules.warehouse_pro._openEdit('${item.id}')">✏️ Редактировать</button>
      <button class="btn btn-danger btn-sm"    onclick="CRM.modules.warehouse_pro._deleteItem('${item.id}')">🗑️ Удалить</button>
    </div>

    ${resList.length?`
    <div class="section-label" style="margin-bottom:8px;">🔒 Активные резервы</div>
    <div class="card" style="padding:0;overflow:hidden;margin-bottom:14px;">
      <table style="width:100%;border-collapse:collapse;"><tbody>
        ${resList.map(r=>`<tr style="border-bottom:1px solid rgba(45,53,86,.4);">
          <td style="padding:8px 12px;font-size:0.8rem;">${this._esc(r.client||r.order_id||'—')}</td>
          <td style="padding:8px 12px;font-weight:700;">${r.qty} ${item.unit}</td>
          <td style="padding:8px 12px;font-size:0.72rem;color:var(--text-muted);">${(r.created_at||'').slice(0,10)}</td>
          <td style="padding:8px 12px;"><button class="btn btn-secondary btn-xs" onclick="CRM.modules.warehouse_pro._unreserve('${r.id}')">Снять</button></td>
        </tr>`).join('')}
      </tbody></table>
    </div>`:''}

    ${(item.batches||[]).length?`
    <div class="section-label" style="margin-bottom:8px;">📦 Партии</div>
    <div class="card" style="padding:0;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr>
          <th style="padding:7px 12px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);text-align:left;">Партия</th>
          <th style="padding:7px 12px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);text-align:right;">Кол-во</th>
          <th style="padding:7px 12px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);">Дата</th>
          <th style="padding:7px 12px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);">Примечание</th>
        </tr></thead>
        <tbody>${(item.batches||[]).map(b=>`<tr style="border-bottom:1px solid rgba(45,53,86,.3);">
          <td style="padding:7px 12px;font-family:monospace;font-size:0.75rem;">${this._esc(b.batch||'—')}</td>
          <td style="padding:7px 12px;text-align:right;">${b.qty} ${item.unit}</td>
          <td style="padding:7px 12px;font-size:0.75rem;color:var(--text-muted);">${b.date||''}</td>
          <td style="padding:7px 12px;font-size:0.75rem;color:var(--text-muted);">${this._esc(b.note||'—')}</td>
        </tr>`).join('')}</tbody>
      </table>
    </div>`:''}
    `;
    openModal('wproDetailModal');
  },

  // ══ ОПЕРАЦИИ ══════════════════════════════════════════════════════════════
  _openOp(type, itemId) {
    document.getElementById('wpro_op_type').value    = type;
    document.getElementById('wpro_op_item_id').value = itemId;
    document.getElementById('wpro_op_qty').value     = '';
    document.getElementById('wpro_op_note').value    = '';
    document.getElementById('wpro_op_batch').value   = '';
    document.getElementById('wpro_op_title').innerText = type==='income'?'⬆️ Оприходование':'⬇️ Списание';
    document.getElementById('wpro_op_batch_wrap').style.display = type==='income'?'':'none';
    openModal('wproOpModal');
  },

  async _submitOp() {
    const type   = document.getElementById('wpro_op_type').value;
    const itemId = document.getElementById('wpro_op_item_id').value;
    const qty    = parseFloat(document.getElementById('wpro_op_qty').value);
    const note   = document.getElementById('wpro_op_note').value;
    const batch  = document.getElementById('wpro_op_batch').value;
    if (!qty||qty<=0) { notify('Введите количество','error'); return; }
    const data = {item_id:itemId, qty, note};
    if (type==='income') data.batch=batch; else data.reason=note||'Списание';
    const res = await CRM.api('warehouse_pro', type, data);
    if (res?.ok===false) { notify(res.msg||'Ошибка','error'); return; }
    notify(type==='income'?'Оприходовано ✅':'Списано ✅','success');
    closeModal('wproOpModal');
    closeModal('wproDetailModal');
    this.render();
  },

  // ══ РАСЧЁТ РАСХОДА ════════════════════════════════════════════════════════
  _openCalc(prefill) {
    const el = document.getElementById('wpro_calc_svc');
    if (el) el.value = prefill||'';
    document.getElementById('wpro_calc_result').innerHTML = '';
    openModal('wproCalcModal');
  },

  async _doCalc() {
    const svc = document.getElementById('wpro_calc_svc').value.trim();
    const qty = parseInt(document.getElementById('wpro_calc_qty').value)||1;
    if (!svc) { notify('Введите услугу','error'); return; }
    const res  = await CRM.api('warehouse_pro','calc',{service:svc,qty});
    const rows = res?.data||[];
    const el   = document.getElementById('wpro_calc_result');
    if (!rows.length) { el.innerHTML='<div style="color:var(--text-muted);text-align:center;padding:16px;">Нет данных для этой услуги</div>'; return; }
    el.innerHTML = rows.map(r=>`
    <div class="card" style="padding:12px;margin-bottom:8px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
        <div><div style="font-weight:600;">${this._esc(r.name)}</div><div style="font-size:0.68rem;color:var(--text-muted);">${r.sku}</div></div>
        <div style="font-weight:700;color:${r.enough?'#10b981':'#ef4444'};">${r.enough?'✅ Хватит':'❌ Не хватит'}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr ${r.need_packs?'1fr':''};gap:8px;font-size:0.78rem;">
        <div><span style="color:var(--text-muted);">Нужно:</span> <b>${r.need_sheets} ${r.unit}</b></div>
        <div><span style="color:var(--text-muted);">Доступно:</span> <b>${r.available_sheets||r.available} ${r.unit}</b></div>
        ${r.need_packs?`<div><span style="color:var(--text-muted);">Упаковок:</span> <b>${r.need_packs}</b></div>`:''}
      </div>
    </div>`).join('');
  },

  // ══ ДВИЖЕНИЯ ══════════════════════════════════════════════════════════════
  async _openMovements() {
    const res  = await CRM.api('warehouse_pro','movements');
    const list = res?.data||[];
    const icons  = {income:'⬆️',writeoff:'⬇️',add:'➕',adjust:'🔧',reserve:'🔒',inventory:'📋'};
    const labels = {income:'Приход',writeoff:'Списание',add:'Добавлен',adjust:'Коррекция',reserve:'Резерв',inventory:'Инвентаризация'};
    const cont = document.getElementById('wpro_content');
    if (!cont) return;
    cont.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div style="font-weight:700;">📊 История движений (${list.length})</div>
      <button class="btn btn-secondary btn-sm" onclick="CRM.modules.warehouse_pro._setView('dashboard')">← Назад</button>
    </div>
    <div class="card" style="padding:0;overflow:hidden;">
      ${list.length?`<table style="width:100%;border-collapse:collapse;">
        <thead><tr>
          <th style="padding:8px 12px;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);text-align:left;">Операция</th>
          <th style="padding:8px 12px;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);text-align:left;">Товар</th>
          <th style="padding:8px 12px;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);text-align:right;">Кол-во</th>
          <th style="padding:8px 12px;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Дата</th>
          <th style="padding:8px 12px;font-size:0.7rem;color:var(--text-muted);background:var(--bg-card2);">Примечание</th>
        </tr></thead>
        <tbody>${list.map(m=>`<tr style="border-bottom:1px solid rgba(45,53,86,.4);">
          <td style="padding:7px 12px;font-size:0.78rem;">${icons[m.action]||'•'} ${labels[m.action]||m.action}</td>
          <td style="padding:7px 12px;font-size:0.78rem;">${m.data?.sku||''} ${m.data?.name?'· '+this._esc(m.data.name):''}</td>
          <td style="padding:7px 12px;text-align:right;font-weight:700;font-size:0.83rem;color:${m.action==='income'?'#10b981':m.action==='writeoff'?'#ef4444':'var(--text-muted)'};">
            ${m.action==='income'?'+':m.action==='writeoff'?'-':''}${m.data?.qty??m.data?.diff??''}
          </td>
          <td style="padding:7px 12px;font-size:0.72rem;color:var(--text-muted);">${(m.created_at||'').slice(0,16)}</td>
          <td style="padding:7px 12px;font-size:0.72rem;color:var(--text-muted);">${this._esc(m.data?.note||m.data?.reason||'')}</td>
        </tr>`).join('')}</tbody>
      </table>`:'<div class="empty-state">Движений ещё нет</div>'}
    </div>`;
  },

  // ══ ИНВЕНТАРИЗАЦИЯ ════════════════════════════════════════════════════════
  _printInventory() {
    document.getElementById('wpro_inv_body').innerHTML = `
    <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:10px;">Введите фактические остатки. Пустые поля = без изменений.</div>
    <table style="width:100%;border-collapse:collapse;">
      <thead><tr>
        <th style="padding:7px;font-size:0.68rem;color:var(--text-muted);text-align:left;">Артикул</th>
        <th style="padding:7px;font-size:0.68rem;color:var(--text-muted);text-align:left;">Название</th>
        <th style="padding:7px;font-size:0.68rem;color:var(--text-muted);text-align:right;">В системе</th>
        <th style="padding:7px;font-size:0.68rem;color:var(--text-muted);">Факт</th>
      </tr></thead>
      <tbody>${this._items.map(i=>`
        <tr style="border-bottom:1px solid rgba(45,53,86,.4);">
          <td style="padding:7px;font-family:monospace;font-size:0.72rem;">${this._esc(i.sku)}</td>
          <td style="padding:7px;font-size:0.8rem;">${this._esc(i.name)}</td>
          <td style="padding:7px;text-align:right;font-weight:700;">${i.qty} ${i.unit}</td>
          <td style="padding:7px;"><input type="number" class="form-input" style="width:85px;padding:4px 8px;" data-inv-id="${i.id}" placeholder="${i.qty}" step="0.01"></td>
        </tr>`).join('')}
      </tbody>
    </table>`;
    openModal('wproInvModal');
  },

  async _submitInventory() {
    const inputs = document.querySelectorAll('[data-inv-id]');
    const data   = [];
    inputs.forEach(inp=>{ if(inp.value!=='') data.push({id:inp.dataset.invId,actual:parseFloat(inp.value)}); });
    if (!data.length) { notify('Нет изменений','error'); return; }
    const res = await CRM.api('warehouse_pro','inventory',{items:data});
    notify(`Инвентаризация: обновлено ${res?.report?.length||0} позиций ✅`,'success');
    closeModal('wproInvModal');
    this.render();
  },

  // ══ СНЯТЬ РЕЗЕРВ ══════════════════════════════════════════════════════════
  async _unreserve(resId) {
    if (!confirm('Снять резерв?')) return;
    await CRM.api('warehouse_pro','unreserve',{res_id:resId});
    notify('Резерв снят','success');
    closeModal('wproDetailModal');
    this.render();
  },

  // ══ ЭТИКЕТКА ══════════════════════════════════════════════════════════════
  _printLabel(id) {
    const item = this._items.find(i=>String(i.id)===String(id));
    if (!item) return;
    const avail = (item.qty||0)-(item.reserved||0);
    const html = `<html><head><style>
      body{font-family:Arial,sans-serif;width:90mm;padding:6mm;font-size:11pt;}
      .sku{font-size:9pt;color:#888;} .name{font-size:13pt;font-weight:bold;margin:3px 0;}
      .qty{font-size:15pt;font-weight:bold;color:#10b981;margin-top:5px;}
      hr{border:none;border-top:1px solid #ccc;margin:5px 0;}
    </style></head><body>
      <div class="sku">Арт: ${item.sku}</div>
      <div class="name">${item.name}</div>
      <div>📍 ${item.location||'—'}</div><hr>
      <div class="qty">Остаток: ${avail} ${item.unit}${item.unit_coeff>1?' ('+avail*item.unit_coeff+' '+item.unit_coeff_label+')':''}</div>
      ${item.barcode?`<div style="font-size:9pt;color:#888;margin-top:4px;">Штрихкод: ${item.barcode}</div>`:''}
      <div style="font-size:8pt;color:#aaa;margin-top:4px;">${new Date().toLocaleDateString('ru')}</div>
    <script>window.onload=()=>window.print()<\/script></body></html>`;
    const w=window.open('','_blank','width=380,height=280');
    w.document.write(html); w.document.close();
  },

  // ══ ДОБАВИТЬ / РЕДАКТИРОВАТЬ ══════════════════════════════════════════════
  _openAdd() {
    this._editId = null;
    document.getElementById('wpro_modal_title').innerText = '+ Добавить материал';
    document.getElementById('wpro_modal_btn').innerHTML   = '💾 Добавить';
    ['sku','name','brand','location','barcode','color','size','material','coeff_label'].forEach(f=>{
      const el=document.getElementById('wpro_'+f); if(el) el.value='';
    });
    document.getElementById('wpro_category').value = '';
    document.getElementById('wpro_unit').value     = 'шт';
    document.getElementById('wpro_coeff').value    = '1';
    document.getElementById('wpro_cost').value     = '';
    document.getElementById('wpro_price').value    = '';
    document.getElementById('wpro_qty').value      = '0';
    document.getElementById('wpro_min_qty').value  = '5';
    document.getElementById('wpro_weight').value   = '';
    document.getElementById('wpro_desc').value     = '';
    const prev = document.getElementById('wpro_photo_prev');
    if (prev) { prev.innerHTML='📷'; prev.style.background=''; prev.dataset.photoUrl=''; }
    openModal('wproAddModal');
  },

  _openEdit(id) {
    const item = this._items.find(i=>String(i.id)===String(id));
    if (!item) return;
    this._editId = id;
    document.getElementById('wpro_modal_title').innerText = '✏️ Редактировать';
    document.getElementById('wpro_modal_btn').innerHTML   = '💾 Сохранить';
    const set=(k,v)=>{ const el=document.getElementById('wpro_'+k); if(el) el.value=v||''; };
    set('sku',item.sku); set('name',item.name); set('category',item.category);
    set('brand',item.brand); set('coeff',item.unit_coeff||1); set('coeff_label',item.unit_coeff_label);
    set('cost',item.cost); set('price',item.price); set('qty',item.qty); set('min_qty',item.min_qty);
    set('location',item.location); set('desc',item.desc); set('weight',item.weight);
    set('color',item.color); set('size',item.size); set('material',item.material); set('barcode',item.barcode);
    document.getElementById('wpro_unit').value = item.unit||'шт';
    const prev = document.getElementById('wpro_photo_prev');
    if (prev) {
      if (item.photo) { prev.innerHTML=''; prev.style.background=`url(${item.photo}) center/cover no-repeat`; }
      else { prev.innerHTML='📷'; prev.style.background=''; }
    }
    openModal('wproAddModal');
  },

  async _saveItem() {
    const g  = id=>(document.getElementById(id)?.value||'').trim();
    const gn = id=>parseFloat(document.getElementById(id)?.value||0)||0;
    const name = g('wpro_name');
    if (!name) { notify('Введите наименование','error'); return; }
    const data = {
      sku:g('wpro_sku'), name, category:g('wpro_category')||'Прочее', brand:g('wpro_brand'),
      unit:g('wpro_unit'), unit_coeff:gn('wpro_coeff'), unit_coeff_label:g('wpro_coeff_label'),
      cost:gn('wpro_cost'), price:gn('wpro_price'), qty:gn('wpro_qty'), min_qty:gn('wpro_min_qty'),
      location:g('wpro_location'), desc:g('wpro_desc'), weight:gn('wpro_weight'),
      color:g('wpro_color'), size:g('wpro_size'), material:g('wpro_material'), barcode:g('wpro_barcode'),
    };
    if (this._editId) {
      data.id = this._editId;
      const cur = this._items.find(i=>String(i.id)===String(this._editId));
      if (cur?.photo) data.photo = cur.photo;
    } else {
      const prev = document.getElementById('wpro_photo_prev');
      if (prev?.dataset?.photoUrl) data.photo = prev.dataset.photoUrl;
    }
    await CRM.api('warehouse_pro','save',data);
    notify(this._editId?'Товар обновлён ✅':'Товар добавлен ✅','success');
    closeModal('wproAddModal');
    this.render();
  },

  async _deleteItem(id) {
    if (!confirm('Удалить товар?')) return;
    await CRM.api('warehouse_pro','delete',null,{id});
    notify('Удалено','success');
    closeModal('wproDetailModal');
    this.render();
  },

  // ══ ФОТО ══════════════════════════════════════════════════════════════════
  _triggerPhoto() {
    document.getElementById('wpro_photo_input').click();
  },

  async _uploadPhoto(input) {
    const file = input.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { notify('Только изображения','error'); return; }
    if (file.size>10*1024*1024) { notify('Максимум 10MB','error'); return; }
    const prev   = document.getElementById('wpro_photo_prev');
    const objUrl = URL.createObjectURL(file);
    if (prev) { prev.innerHTML=''; prev.style.background=`url(${objUrl}) center/cover no-repeat`; }
    const API_URL = window.API_URL||'/api/api.php';
    const API_KEY = window.API_KEY||'12345';
    const fd = new FormData(); fd.append('file',file);
    notify('Загрузка фото...','info');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', API_URL+'?action=upload&key='+API_KEY);
    xhr.onload = async () => {
      URL.revokeObjectURL(objUrl);
      try {
        const r = JSON.parse(xhr.responseText);
        if (r.url) {
          if (this._editId) {
            await CRM.api('warehouse_pro','save_photo',{id:this._editId,photo:r.url});
            const idx=this._items.findIndex(i=>String(i.id)===String(this._editId));
            if (idx>=0) this._items[idx].photo=r.url;
            if (prev) { prev.innerHTML=''; prev.style.background=`url(${r.url}) center/cover no-repeat`; }
            notify('Фото сохранено ✅','success');
          } else {
            if (prev) { prev.dataset.photoUrl=r.url; prev.innerHTML=''; prev.style.background=`url(${r.url}) center/cover no-repeat`; }
            notify('Фото готово ✅','success');
          }
        } else { notify('Ошибка загрузки фото','error'); }
      } catch(e) { notify('Ошибка','error'); }
    };
    xhr.onerror=()=>notify('Ошибка сети','error');
    xhr.send(fd); input.value='';
  },

  // ══ ИМПОРТ ════════════════════════════════════════════════════════════════
  _importRows: [],

  _openImport(tab) {
    this._importRows = [];
    document.getElementById('wpro_import_preview').innerHTML = '';
    document.getElementById('wpro_import_footer').style.display = 'none';
    openModal('wproImportModal');
    if (tab) this._importTab(tab);
    else     this._importTab('file');
  },

  _importTab(t) {
    ['file','url','manual'].forEach(k=>{
      const b=document.getElementById('imp_tab_'+k+'_body');
      const btn=document.getElementById('imp_tab_'+k);
      if(b)   b.style.display = k===t?'':'none';
      if(btn) btn.className   = k===t?'btn btn-primary btn-sm':'btn btn-secondary btn-sm';
    });
  },

  _parseImportFile(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const text = e.target.result;
      this._parseCSVText(text);
    };
    reader.readAsText(file, 'UTF-8');
    input.value='';
  },

  async _importFromUrl() {
    const url = document.getElementById('wpro_import_url').value.trim();
    if (!url) { notify('Введите URL','error'); return; }
    notify('Загрузка...','info');
    try {
      const r = await fetch(url);
      const t = await r.text();
      this._parseCSVText(t);
    } catch(e) { notify('Ошибка загрузки: '+e.message,'error'); }
  },

  _parseManualCSV() {
    const text = document.getElementById('wpro_import_manual').value;
    this._parseCSVText(text);
  },

  _parseCSVText(text) {
    const lines = text.trim().split('\n').map(l=>l.trim()).filter(Boolean);
    if (lines.length < 2) { notify('Файл пустой или неверный формат','error'); return; }
    const sep    = lines[0].includes(';') ? ';' : ',';
    const header = lines[0].split(sep).map(h=>h.trim().toLowerCase());
    const rows   = [];
    for (let i=1; i<lines.length; i++) {
      const vals = lines[i].split(sep);
      const row  = {};
      header.forEach((h,idx) => { row[h] = (vals[idx]||'').trim(); });
      if (row.name) rows.push(row);
    }
    this._importRows = rows;
    this._renderImportPreview(rows);
  },

  _renderImportPreview(rows) {
    const el = document.getElementById('wpro_import_preview');
    const ft = document.getElementById('wpro_import_footer');
    if (!rows.length) { el.innerHTML='<div class="empty-state">Нет данных</div>'; return; }
    ft.style.display = 'flex';
    el.innerHTML = `
    <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:8px;">Найдено ${rows.length} позиций:</div>
    <div class="card" style="padding:0;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr>
          <th style="padding:6px 10px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);text-align:left;">Артикул</th>
          <th style="padding:6px 10px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);text-align:left;">Название</th>
          <th style="padding:6px 10px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);">Категория</th>
          <th style="padding:6px 10px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);text-align:right;">Кол-во</th>
          <th style="padding:6px 10px;font-size:0.68rem;color:var(--text-muted);background:var(--bg-card2);text-align:right;">Цена</th>
        </tr></thead>
        <tbody>
          ${rows.slice(0,50).map(r=>`<tr style="border-bottom:1px solid rgba(45,53,86,.3);">
            <td style="padding:6px 10px;font-family:monospace;font-size:0.72rem;">${this._esc(r.sku||'—')}</td>
            <td style="padding:6px 10px;font-size:0.78rem;">${this._esc(r.name)}</td>
            <td style="padding:6px 10px;font-size:0.75rem;color:var(--text-muted);">${this._esc(r.category||'Прочее')}</td>
            <td style="padding:6px 10px;text-align:right;font-size:0.78rem;">${r.qty||0} ${r.unit||'шт'}</td>
            <td style="padding:6px 10px;text-align:right;font-size:0.78rem;color:var(--accent3);">${formatMoney(r.price||0)}</td>
          </tr>`).join('')}
          ${rows.length>50?`<tr><td colspan="5" style="padding:8px;text-align:center;font-size:0.75rem;color:var(--text-muted);">... и ещё ${rows.length-50} позиций</td></tr>`:''}
        </tbody>
      </table>
    </div>`;
  },

  async _submitImport() {
    if (!this._importRows.length) { notify('Нет данных для импорта','error'); return; }
    const res = await CRM.api('warehouse_pro','import_csv',{rows:this._importRows});
    notify(`Импортировано ${res?.added||0} товаров ✅`,'success');
    closeModal('wproImportModal');
    this.render();
  },

  // ══ УТИЛИТЫ ═══════════════════════════════════════════════════════════════
  _esc(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  },
});
</script>