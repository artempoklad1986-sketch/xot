<?php
/**
 * @name        Касса смены
 * @icon        💳
 * @description Касса, операции, допуслуги, отчёты, ЗП
 * @version     7.0
 * @sidebar     true
 * @color       #f59e0b
 */

if (!isset($moduleDB['shifts']))              $moduleDB['shifts']              = ['current'=>null,'history'=>[]];
if (!isset($moduleDB['finance']))             $moduleDB['finance']             = [];
if (!isset($moduleDB['salary']))              $moduleDB['salary']              = [];
if (!isset($moduleDB['salary']['records']))   $moduleDB['salary']['records']   = [];
if (!isset($moduleDB['salary']['employees'])) $moduleDB['salary']['employees'] = [];
if (!isset($moduleDB['reports']))             $moduleDB['reports']             = [];
if (!isset($moduleDB['shift_buttons']))       $moduleDB['shift_buttons']       = [];

function shiftFinanceExists($finance, $uniqKey) {
    foreach ($finance as $f) {
        if (isset($f['_uniqKey']) && $f['_uniqKey'] === $uniqKey) return true;
    }
    return false;
}

function shiftIsLocalPay($method) {
    return in_array($method, ['Наличные','Карта','Перевод']);
}

function shiftDetectCategory($desc, $type) {
    $d = mb_strtolower(trim($desc), 'UTF-8');
    if ($type === 'expense') {
        if (mb_strpos($d,'аренд',0,'UTF-8')     !==false) return 'Аренда помещения';
        if (mb_strpos($d,'зарплат',0,'UTF-8')   !==false) return 'Зарплата сотрудникам';
        if (mb_strpos($d,'бумаг',0,'UTF-8')     !==false) return 'Расходные материалы';
        if (mb_strpos($d,'чернил',0,'UTF-8')    !==false) return 'Расходные материалы';
        if (mb_strpos($d,'картридж',0,'UTF-8')  !==false) return 'Расходные материалы';
        if (mb_strpos($d,'расходник',0,'UTF-8') !==false) return 'Расходные материалы';
        if (mb_strpos($d,'налог',0,'UTF-8')     !==false) return 'Налоги / Взносы';
        if (mb_strpos($d,'реклам',0,'UTF-8')    !==false) return 'Реклама / Маркетинг';
        if (mb_strpos($d,'ремонт',0,'UTF-8')    !==false) return 'Ремонт оборудования';
        if (mb_strpos($d,'коммунал',0,'UTF-8')  !==false) return 'Коммунальные услуги';
        if (mb_strpos($d,'электр',0,'UTF-8')    !==false) return 'Коммунальные услуги';
        return 'Прочие расходы';
    }
    if (mb_strpos($d,'фото',0,'UTF-8')      !==false) return 'Фотопечать';
    if (mb_strpos($d,'баннер',0,'UTF-8')    !==false) return 'Баннерная печать';
    if (mb_strpos($d,'копи',0,'UTF-8')      !==false) return 'Копирование / Распечатка';
    if (mb_strpos($d,'печат',0,'UTF-8')     !==false) return 'Копирование / Распечатка';
    if (mb_strpos($d,'визит',0,'UTF-8')     !==false) return 'Бизнес-полиграфия';
    if (mb_strpos($d,'листовк',0,'UTF-8')   !==false) return 'Бизнес-полиграфия';
    if (mb_strpos($d,'дизайн',0,'UTF-8')    !==false) return 'Дизайн';
    if (mb_strpos($d,'макет',0,'UTF-8')     !==false) return 'Дизайн';
    if (mb_strpos($d,'ламин',0,'UTF-8')     !==false) return 'Ламинация';
    if (mb_strpos($d,'перепл',0,'UTF-8')    !==false) return 'Переплёт';
    if (mb_strpos($d,'аванс',0,'UTF-8')     !==false) return 'Авансовый платёж';
    if (mb_strpos($d,'предоплат',0,'UTF-8') !==false) return 'Авансовый платёж';
    return 'Выручка кассы';
}

function shiftBuildFinRecord($op, $shift) {
    $manager   = $shift['manager']  ?? 'Менеджер';
    $empId     = $shift['empId']    ?? '';
    $shiftDate = date('d.m.Y', strtotime($shift['openTime'] ?? 'now'));
    $desc      = trim($op['desc']   ?? '');
    $category  = shiftDetectCategory($desc, $op['type']);
    $finDesc   = $desc
        ? '💳 Смена '.$shiftDate.' ['.$manager.'] — '.$desc
        : '💳 Смена '.$shiftDate.' ['.$manager.'] — '.($op['type']==='income' ? 'Поступление' : 'Изъятие');
    $uniqKey = 'shift_op_' . $op['id'];
    if (!empty($op['qrPaymentId'])) $uniqKey = 'qr_payment_' . $op['qrPaymentId'];
    return [
        'id'           => 'shift_op_'.$op['id'],
        '_uniqKey'     => $uniqKey,
        '_qrPaymentId' => $op['qrPaymentId'] ?? null,
        'type'         => $op['type'],
        'date'         => $op['time'],
        'amount'       => floatval($op['amount']),
        'category'     => $category,
        'desc'         => $finDesc,
        'method'       => $op['method'] ?? 'Наличные',
        'client'       => $manager,
        'empId'        => $empId,
        'fromShift'    => true,
        'shiftId'      => $shift['id'] ?? null,
        'shiftDate'    => $shiftDate,
        'manager'      => $manager,
        'createdAt'    => date('Y-m-d H:i:s'),
    ];
}

switch ($moduleAction) {

    case 'list':
    case 'current':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current']]);
        break;

    case 'managers':
        $emps   = $moduleDB['salary']['employees'] ?? [];
        $active = array_values(array_filter($emps, fn($e)=>($e['status']??'active')==='active'));
        echo json_encode(['ok'=>true,'data'=>array_map(fn($e)=>[
            'id'       => $e['id'],
            'name'     => $e['name'],
            'position' => $e['position'] ?? '',
            'color'    => $e['color']    ?? '#f59e0b',
            'bonusPct' => floatval($e['bonusPct'] ?? 0.5),
        ], $active)]);
        break;

    case 'getButtons':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shift_buttons']]);
        break;

    case 'addButton':
        $b = [
            'id'     => 'btn_'.uniqid().'_'.rand(100,999),
            'label'  => trim($moduleBody['label']  ?? ''),
            'amount' => floatval($moduleBody['amount'] ?? 0),
            'type'   => in_array($moduleBody['type']??'income',['income','expense']) ? $moduleBody['type'] : 'income',
            'icon'   => trim($moduleBody['icon']   ?? 'default'),
            'color'  => trim($moduleBody['color']  ?? '#f59e0b'),
        ];
        if (!$b['label']) { echo json_encode(['ok'=>false,'error'=>'Нет названия']); break; }
        $moduleDB['shift_buttons'][] = $b;
        writeDB($moduleDB);
        echo json_encode(['ok'=>true,'data'=>$b]);
        break;

    case 'deleteButton':
        $bid = $moduleBody['id'] ?? $_GET['id'] ?? null;
        if (!$bid) { echo json_encode(['ok'=>false,'error'=>'Нет ID']); break; }
        $moduleDB['shift_buttons'] = array_values(array_filter(
            $moduleDB['shift_buttons'], fn($b)=>(string)$b['id']!==(string)$bid
        ));
        writeDB($moduleDB);
        echo json_encode(['ok'=>true]);
        break;

    case 'open':
        if ($moduleDB['shifts']['current']) { echo json_encode(['ok'=>false,'error'=>'Смена уже открыта']); break; }
        $iKey = trim($moduleBody['iKey'] ?? '');
        if ($iKey) {
            foreach ($moduleDB['shifts']['history'] as $hs) {
                if (($hs['iKey'] ?? '') === $iKey) { echo json_encode(['ok'=>false,'error'=>'Дубль']); break 2; }
            }
        }
        $empId    = trim($moduleBody['empId']   ?? '');
        $manager  = trim($moduleBody['manager'] ?? 'Менеджер');
        $bonusPct = 0.5;
        foreach ($moduleDB['salary']['employees'] as $emp) {
            if ((string)$emp['id'] === (string)$empId) {
                $bonusPct = floatval($emp['bonusPct'] ?? 0.5);
                $manager  = $emp['name'];
                break;
            }
        }
        $shift = [
            'id'           => uniqid('shift_',true),
            'iKey'         => $iKey,
            'empId'        => $empId,
            'manager'      => $manager,
            'startCash'    => floatval($moduleBody['startCash'] ?? 0),
            'cash'         => floatval($moduleBody['startCash'] ?? 0),
            'bonusPct'     => $bonusPct,
            'totalIncome'  => 0,
            'totalExpense' => 0,
            'accruedBonus' => 0,
            'operations'   => [],
            'openTime'     => date('Y-m-d H:i:s'),
            'closeTime'    => null,
            'completed'    => false,
        ];
        $moduleDB['shifts']['current'] = $shift;
        writeDB($moduleDB);
        writeLog('SHIFT_OPEN', $shift['manager'].' | startCash:'.$shift['startCash']);
        echo json_encode(['ok'=>true,'data'=>$shift]);
        break;

    case 'operation':
        if (!$moduleDB['shifts']['current']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Смена не открыта']); break; }
        $iKey = trim($moduleBody['iKey'] ?? '');
        if ($iKey) {
            foreach ($moduleDB['shifts']['current']['operations'] as $existOp) {
                if (($existOp['iKey'] ?? '') === $iKey) {
                    echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current'],'_dup'=>true]);
                    break 2;
                }
            }
        }
        $qrPaymentId = trim($moduleBody['qrPaymentId'] ?? '');
        if ($qrPaymentId) {
            foreach ($moduleDB['shifts']['current']['operations'] as $existOp) {
                if (($existOp['qrPaymentId'] ?? '') === $qrPaymentId) {
                    echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current'],'_dup'=>true]);
                    break 2;
                }
            }
        }
        $type   = $moduleBody['type']   ?? 'income';
        $amount = floatval($moduleBody['amount'] ?? 0);
        $desc   = trim($moduleBody['desc']   ?? '');
        $method = trim($moduleBody['method'] ?? 'Наличные');
        $qty    = max(1, intval($moduleBody['qty'] ?? 1));
        if ($amount <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Сумма должна быть больше нуля']); break; }
        if (!in_array($type,['income','expense'])) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Неверный тип']); break; }
        $finalAmount = round($amount * $qty, 2);
        $finalDesc   = $qty > 1 ? $desc.' × '.$qty : $desc;
        $op = [
            'id'          => uniqid('op_',true),
            'iKey'        => $iKey,
            'type'        => $type,
            'amount'      => $finalAmount,
            'desc'        => $finalDesc,
            'method'      => $method,
            'qty'         => $qty,
            'price'       => $amount,
            'time'        => date('Y-m-d H:i:s'),
            'qrPaymentId' => $qrPaymentId,
        ];
        $moduleDB['shifts']['current']['operations'][] = $op;
        if ($type === 'income') {
            $moduleDB['shifts']['current']['cash']         += $finalAmount;
            $moduleDB['shifts']['current']['totalIncome']  += $finalAmount;
            $bp = floatval($moduleDB['shifts']['current']['bonusPct'] ?? 0.5);
            $moduleDB['shifts']['current']['accruedBonus'] += round($finalAmount * $bp / 100, 2);
        } else {
            $moduleDB['shifts']['current']['cash']         -= $finalAmount;
            $moduleDB['shifts']['current']['totalExpense'] += $finalAmount;
        }
        $finRecord = null;
        if (shiftIsLocalPay($method) && empty($qrPaymentId)) {
            $finRecord = shiftBuildFinRecord($op, $moduleDB['shifts']['current']);
            if (!shiftFinanceExists($moduleDB['finance'], $finRecord['_uniqKey'])) {
                array_unshift($moduleDB['finance'], $finRecord);
            }
        }
        writeDB($moduleDB);
        echo json_encode([
            'ok'           => true,
            'data'         => $moduleDB['shifts']['current'],
            'finance'      => $finRecord,
            'accruedBonus' => $moduleDB['shifts']['current']['accruedBonus'],
        ]);
        break;

    case 'deleteOperation':
        if (!$moduleDB['shifts']['current']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Смена не открыта']); break; }
        $opId = $moduleBody['opId'] ?? null;
        $found = null; $foundIdx = -1;
        foreach ($moduleDB['shifts']['current']['operations'] as $i => $op) {
            if ((string)$op['id'] === (string)$opId) { $found = $op; $foundIdx = $i; break; }
        }
        if (!$found) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Не найдена']); break; }
        if ($found['type'] === 'income') {
            $moduleDB['shifts']['current']['cash']         -= $found['amount'];
            $moduleDB['shifts']['current']['totalIncome']  -= $found['amount'];
            $bp = floatval($moduleDB['shifts']['current']['bonusPct'] ?? 0.5);
            $moduleDB['shifts']['current']['accruedBonus'] -= round($found['amount'] * $bp / 100, 2);
            $moduleDB['shifts']['current']['accruedBonus']  = max(0, $moduleDB['shifts']['current']['accruedBonus']);
        } else {
            $moduleDB['shifts']['current']['cash']         += $found['amount'];
            $moduleDB['shifts']['current']['totalExpense'] -= $found['amount'];
        }
        array_splice($moduleDB['shifts']['current']['operations'], $foundIdx, 1);
        $dk = !empty($found['qrPaymentId']) ? 'qr_payment_'.$found['qrPaymentId'] : 'shift_op_'.$opId;
        $moduleDB['finance'] = array_values(array_filter(
            $moduleDB['finance'], fn($f) => ($f['_uniqKey'] ?? '') !== $dk
        ));
        writeDB($moduleDB);
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current']]);
        break;

    case 'editOperation':
        if (!$moduleDB['shifts']['current']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Смена не открыта']); break; }
        $opId      = trim($moduleBody['opId']   ?? '');
        $newAmt    = floatval($moduleBody['amount']  ?? 0);
        $newDesc   = trim($moduleBody['desc']   ?? '');
        $newMethod = trim($moduleBody['method'] ?? 'Наличные');
        $newQty    = max(1, intval($moduleBody['qty'] ?? 1));
        $newPrice  = floatval($moduleBody['price'] ?? $newAmt);
        if ($newAmt <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Сумма > 0']); break; }
        $found = false;
        foreach ($moduleDB['shifts']['current']['operations'] as &$op) {
            if ((string)$op['id'] !== (string)$opId) continue;
            $oldAmt = $op['amount'];
            $diff   = $newAmt - $oldAmt;
            if ($op['type'] === 'income') {
                $moduleDB['shifts']['current']['cash']         += $diff;
                $moduleDB['shifts']['current']['totalIncome']  += $diff;
                $bp = floatval($moduleDB['shifts']['current']['bonusPct'] ?? 0.5);
                $moduleDB['shifts']['current']['accruedBonus'] += round($newAmt*$bp/100,2) - round($oldAmt*$bp/100,2);
                $moduleDB['shifts']['current']['accruedBonus']  = max(0, $moduleDB['shifts']['current']['accruedBonus']);
            } else {
                $moduleDB['shifts']['current']['cash']         -= $diff;
                $moduleDB['shifts']['current']['totalExpense'] += $diff;
            }
            $op['amount'] = $newAmt; $op['desc'] = $newDesc; $op['method'] = $newMethod;
            $op['price']  = $newPrice; $op['qty'] = $newQty;
            $found = true;
            $ek = !empty($op['qrPaymentId']) ? 'qr_payment_'.$op['qrPaymentId'] : 'shift_op_'.$opId;
            foreach ($moduleDB['finance'] as &$f) {
                if (($f['_uniqKey'] ?? '') === $ek) {
                    $f['amount'] = $newAmt;
                    $f['desc']   = '💳 [Ред.] '.$newDesc;
                    $f['method'] = $newMethod;
                    break;
                }
            }
            unset($f);
            break;
        }
        unset($op);
        if (!$found) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Не найдена']); break; }
        writeDB($moduleDB);
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current']]);
        break;

    case 'close':
        if (!$moduleDB['shifts']['current']) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Смена не открыта']); break; }
        $shift = $moduleDB['shifts']['current'];
        $shift['closeTime']    = date('Y-m-d H:i:s');
        $shift['endCash']      = floatval($moduleBody['endCash'] ?? $shift['cash']);
        $shift['note']         = trim($moduleBody['note'] ?? '');
        $shift['completed']    = true;
        $baseSalary            = floatval($moduleBody['baseSalary'] ?? 0);
        $accruedBonus          = floatval($shift['accruedBonus']    ?? 0);
        $totalSalary           = round($baseSalary + $accruedBonus, 2);
        $shift['baseSalary']   = $baseSalary;
        $shift['accruedBonus'] = $accruedBonus;
        $shift['totalSalary']  = $totalSalary;
        $manager   = $shift['manager'];
        $empId     = $shift['empId'] ?? '';
        $shiftDate = date('d.m.Y', strtotime($shift['openTime']));
        foreach ($shift['operations'] as $op) {
            if (!shiftIsLocalPay($op['method'] ?? '') || !empty($op['qrPaymentId'])) continue;
            $fr = shiftBuildFinRecord($op, $shift);
            if (!shiftFinanceExists($moduleDB['finance'], $fr['_uniqKey'])) {
                array_unshift($moduleDB['finance'], $fr);
            }
        }
        $diff = round($shift['endCash'] - $shift['cash'], 2);
        $shift['cashDiff'] = $diff;
        if (abs($diff) >= 0.01) {
            $dk = 'shift_diff_'.$shift['id'];
            if (!shiftFinanceExists($moduleDB['finance'], $dk)) {
                array_unshift($moduleDB['finance'], [
                    'id'        => $dk, '_uniqKey' => $dk,
                    'type'      => $diff < 0 ? 'expense' : 'income',
                    'date'      => $shift['closeTime'],
                    'amount'    => abs($diff),
                    'category'  => $diff < 0 ? 'Недостача кассы' : 'Излишек кассы',
                    'desc'      => ($diff < 0 ? '⚠️ Недостача' : '✅ Излишек').' | Смена '.$shiftDate.' ['.$manager.']',
                    'method'    => 'Наличные', 'client' => $manager, 'fromShift' => true,
                    'shiftId'   => $shift['id'], 'manager' => $manager, 'isDiff' => true,
                    'createdAt' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        if ($totalSalary > 0) {
            $sk = 'shift_salary_'.$shift['id'];
            if (!shiftFinanceExists($moduleDB['finance'], $sk)) {
                array_unshift($moduleDB['finance'], [
                    'id'        => $sk, '_uniqKey' => $sk, 'type' => 'expense',
                    'date'      => $shift['closeTime'], 'amount' => $totalSalary,
                    'category'  => 'Зарплата и выплаты',
                    'desc'      => '👔 ЗП смены '.$shiftDate.' ['.$manager.'] | оклад: '.$baseSalary.'₽ | бонус '.$shift['bonusPct'].'%: '.$accruedBonus.'₽',
                    'method'    => 'Наличные', 'client' => $manager, 'fromShift' => true,
                    'shiftId'   => $shift['id'], 'manager' => $manager, 'createdAt' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($empId) {
                $sr = [
                    'id'        => 'sal_shift_'.$shift['id'],
                    'staffName' => $manager, 'staffId' => $empId, 'type' => 'salary',
                    'amount'    => $totalSalary,
                    'period'    => date('Y-m', strtotime($shift['openTime'])),
                    'note'      => 'Смена '.$shiftDate.' | оклад: '.$baseSalary.'₽ | бонус: '.$accruedBonus.'₽',
                    'date'      => $shift['closeTime'], 'revenue' => $shift['totalIncome'] ?? 0,
                ];
                $ex = false;
                foreach ($moduleDB['salary']['records'] as $r2) {
                    if ($r2['id'] === $sr['id']) { $ex = true; break; }
                }
                if (!$ex) array_unshift($moduleDB['salary']['records'], $sr);
            }
        }
        $mt = [];
        foreach ($shift['operations'] as $op) {
            $m = $op['method'] ?? 'Наличные';
            if (!isset($mt[$m])) $mt[$m] = ['income'=>0,'expense'=>0];
            $mt[$m][$op['type']] += $op['amount'];
        }
        $report = [
            'id'             => 'report_'.$shift['id'],
            'type'           => 'shift_z',
            'shiftId'        => $shift['id'],
            'date'           => $shift['closeTime'],
            'shiftDate'      => $shiftDate,
            'manager'        => $manager,
            'empId'          => $empId,
            'openTime'       => $shift['openTime'],
            'closeTime'      => $shift['closeTime'],
            'startCash'      => $shift['startCash'],
            'endCash'        => $shift['endCash'],
            'calcCash'       => $shift['cash'],
            'cashDiff'       => $diff,
            'totalIncome'    => $shift['totalIncome']  ?? 0,
            'totalExpense'   => $shift['totalExpense'] ?? 0,
            'profit'         => ($shift['totalIncome'] ?? 0) - ($shift['totalExpense'] ?? 0),
            'baseSalary'     => $baseSalary,
            'bonusPct'       => $shift['bonusPct'] ?? 0.5,
            'accruedBonus'   => $accruedBonus,
            'totalSalary'    => $totalSalary,
            'operationsCount'=> count($shift['operations']),
            'operations'     => $shift['operations'],
            'methodTotals'   => $mt,
            'note'           => $shift['note'],
            'createdAt'      => date('Y-m-d H:i:s'),
        ];
        array_unshift($moduleDB['reports'], $report);
        if (count($moduleDB['reports']) > 200) $moduleDB['reports'] = array_slice($moduleDB['reports'], 0, 200);
        $moduleDB['shifts']['history'][] = $shift;
        $moduleDB['shifts']['current']   = null;
        writeDB($moduleDB);
        writeLog('SHIFT_CLOSE', $manager.' | доход:'.$shift['totalIncome'].' | зп:'.$totalSalary.' | расхождение:'.$diff);
        echo json_encode(['ok'=>true,'data'=>$shift,'report'=>$report]);
        break;

    case 'history':
        echo json_encode(['ok'=>true,'data'=>array_slice(array_reverse($moduleDB['shifts']['history'] ?? []), 0, 50)]);
        break;

    case 'reports':
        echo json_encode(['ok'=>true,'data'=>array_slice($moduleDB['reports'] ?? [], 0, 50)]);
        break;

    case 'lastEndCash':
        $empId = trim($moduleBody['empId'] ?? $_GET['empId'] ?? '');
        $hist  = array_reverse($moduleDB['shifts']['history'] ?? []);
        $last  = null;
        foreach ($hist as $hs) {
            if (!$empId || (string)($hs['empId'] ?? '') === (string)$empId) { $last = $hs; break; }
        }
        echo json_encode([
            'ok'      => true,
            'endCash' => $last ? ($last['endCash'] ?? $last['cash'] ?? 0) : 0,
            'date'    => $last ? ($last['closeTime'] ?? null) : null,
        ]);
        break;

    case 'getWarehouse':
        $wh  = $moduleDB['warehouse'] ?? [];
        $out = array_values(array_filter($wh, function($w) {
            return floatval($w['price'] ?? $w['cost'] ?? 0) > 0;
        }));
        echo json_encode(['ok'=>true,'data'=>$out]);
        break;

     default:
        echo json_encode(['ok'=>true,'data'=>null]);
}
?>
<style id="sh_page_fix">
  /* Принудительное отображение страницы модуля */
  .page#page-shift.active { display: block !important; }
  #page-shift { display: block !important; padding: 0 !important; margin: 0 !important; }
  #page-shift .sh-wrap { width: 100% !important; min-height: 100vh !important; box-sizing: border-box !important; }
  
  /* Базовые стили (дубль на случай если JS стили не загрузятся) */
  #page-shift {
    --sh-bg: #1e1e1e;
    --sh-card: #252526;
    --sh-border: #3e3e42;
    --sh-text: #d4d4d4;
    --sh-green: #4caf82;
    --sh-red: #f47067;
    --sh-purple: #c792ea;
  }
  #page-shift .sh-wrap { background: var(--sh-bg); color: var(--sh-text); }
  #page-shift .sh-card { background: var(--sh-card); border: 1px solid var(--sh-border); border-radius: 16px; padding: 20px; }
</style>
<!--MODULE_JS_START-->
<script>
(function() {
'use strict';

// === ПРИНУДИТЕЛЬНАЯ ИНИЦИАЛИЗАЦИЯ ===
// Ждём пока CRM загрузится
if (window.CRM && CRM.modules && CRM.modules.shift) {
  // Если модуль уже зарегистрирован, переопределяем его render
  var originalInit = CRM.modules.shift.render;
  CRM.modules.shift.render = async function() {
    console.log('🔄 Shift: принудительный рендер');
    var result = originalInit ? await originalInit.call(this) : null;
    
    // Принудительно загружаем данные
    setTimeout(async function() {
      var mod = CRM.modules.shift;
      if (mod._current) {
        if (mod._updateStats) mod._updateStats();
        if (mod._renderOps) mod._renderOps();
      }
      // Загружаем историю
      var hist = await CRM.api('shift', 'history');
      if (hist && hist.ok && mod._loadHistory) mod._loadHistory();
      // Загружаем прайс
      var price = await CRM.api('pricelist', 'list');
      if (price && price.ok && mod._priceItems) {
        mod._priceItems = price.data;
        if (mod._buildTopButtons) mod._buildTopButtons();
        if (mod._renderQuickBtns) mod._renderQuickBtns();
      }
    }, 100);
    
    return result;
  };
}

/* ================================================================
   КАССА СМЕНЫ v7.0
================================================================ */

/* --- применяем тему сразу --- */
var _shTheme = localStorage.getItem('shift_theme') || 'dark';
document.documentElement.setAttribute('data-shift-theme', _shTheme);

/* ================================================================
   SVG ИКОНКИ
================================================================ */
var SH_ICONS = {
  photo:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="15" rx="3"/><circle cx="12" cy="12.5" r="3.5"/><path d="M9 5l1.5-2h3L15 5"/></svg>',
  copy:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>',
  print:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
  lam:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M3 15h18"/></svg>',
  bind:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5a2 2 0 012-2h13a1 1 0 010 2H6a1 1 0 000 2h13a1 1 0 010 2H6a1 1 0 000 2h13a1 1 0 010 2H6a1 1 0 000 2h13a1 1 0 010 2H6a2 2 0 01-2-2z"/></svg>',
  card:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
  scan:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/><line x1="3" y1="12" x2="21" y2="12"/></svg>',
  design:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="22"/><line x1="2" y1="12" x2="8" y2="12"/><line x1="16" y1="12" x2="22" y2="12"/></svg>',
  banner:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="8" y1="6" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="18"/></svg>',
  frame:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2"/><rect x="6" y="6" width="12" height="12" rx="1"/></svg>',
  file:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  staple:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17v-6a4 4 0 014-4h10a4 4 0 014 4v6"/><line x1="3" y1="17" x2="21" y2="17"/></svg>',
  delivery: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
  souvenir: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V22H4V12"/><path d="M22 7H2v5h20V7z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>',
  flash:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
  income:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
  expense:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
  edit:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  trash:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>',
  cash:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/><circle cx="12" cy="15" r="2"/></svg>',
  qr:       '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="8" height="8" rx="1"/><rect x="14" y="2" width="8" height="8" rx="1"/><rect x="2" y="14" width="8" height="8" rx="1"/><rect x="4" y="4" width="4" height="4"/><rect x="16" y="4" width="4" height="4"/><rect x="4" y="16" width="4" height="4"/><path d="M14 14h2v2h-2zM18 14h4M18 18v4M14 18h2v2"/></svg>',
  transfer: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 10h2M6 14h2M8 10l2 2-2 2"/></svg>',
  close_x:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  lock:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
  unlock:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 019.9-1"/></svg>',
  user:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  refresh:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>',
  star:     '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
  gift:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>',
  chart:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  clock:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  check:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
  warn:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  hotkey:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M8 12h.01M12 12h.01M16 12h.01M7 16h10"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
  report:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
  plus:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  minus:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  upsell:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>',
  chevron:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>',
  sun:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
  moon:     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>',
  box:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
  'default':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
};

function shIcon(name, size, color) {
  size  = size  || 18;
  color = color || 'currentColor';
  var svg = SH_ICONS[name] || SH_ICONS['default'];
  return svg.replace('<svg ', '<svg width="' + size + '" height="' + size + '" style="color:' + color + ';flex-shrink:0;vertical-align:middle;" ');
}

/* ================================================================
   CSS
================================================================ */
(function() {
  if (document.getElementById('sh_styles_v70')) return;
  var st = document.createElement('style');
  st.id  = 'sh_styles_v70';
  st.textContent = ''
    + '[data-shift-theme="dark"] .sh-wrap{'
    + '--sh-bg:#1e1e1e;--sh-card:#252526;--sh-card2:#2d2d30;--sh-card3:#333337;'
    + '--sh-border:#3e3e42;--sh-text:#d4d4d4;--sh-muted:#858585;'
    + '--sh-accent:#4fc3f7;--sh-green:#4caf82;--sh-red:#f47067;'
    + '--sh-amber:#f59e0b;--sh-purple:#c792ea;--sh-shadow:rgba(0,0,0,.5);}'
    + '[data-shift-theme="light"] .sh-wrap{'
    + '--sh-bg:#f0f2f7;--sh-card:#fff;--sh-card2:#f7f9fc;--sh-card3:#eef1f6;'
    + '--sh-border:#e2e8f0;--sh-text:#1e2640;--sh-muted:#64748b;'
    + '--sh-accent:#3b82f6;--sh-green:#10b981;--sh-red:#ef4444;'
    + '--sh-amber:#f59e0b;--sh-purple:#8b5cf6;--sh-shadow:rgba(0,0,0,.08);}'
    + '.sh-wrap{background:var(--sh-bg);min-height:100%;padding:0;color:var(--sh-text);transition:background .2s,color .2s;}'
    + '.sh-card{background:var(--sh-card);border:1px solid var(--sh-border);border-radius:16px;padding:20px;}'
    + '.sh-card-sm{background:var(--sh-card);border:1px solid var(--sh-border);border-radius:12px;padding:14px 16px;}'
    + '.sh-card-title{font-size:.82rem;font-weight:700;color:var(--sh-text);margin-bottom:14px;display:flex;align-items:center;gap:7px;}'
    + '.sh-tabs{display:flex;gap:4px;background:var(--sh-card2);border:1px solid var(--sh-border);border-radius:12px;padding:4px;}'
    + '.sh-tab{display:flex;align-items:center;gap:6px;padding:7px 16px;border-radius:9px;font-size:.78rem;font-weight:600;color:var(--sh-muted);cursor:pointer;border:none;background:none;transition:all .18s;white-space:nowrap;font-family:inherit;}'
    + '.sh-tab:hover{background:rgba(79,195,247,.07);color:var(--sh-accent);}'
    + '.sh-tab.active{background:var(--sh-card3);color:var(--sh-accent);font-weight:700;box-shadow:0 2px 8px var(--sh-shadow);}'
    + '.sh-kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;}'
    + '.sh-kpi-item{background:var(--sh-card);border:1px solid var(--sh-border);border-radius:14px;padding:16px;text-align:center;transition:transform .15s;}'
    + '.sh-kpi-item:hover{transform:translateY(-2px);}'
    + '.sh-kpi-val{font-size:1.35rem;font-weight:900;line-height:1.1;}'
    + '.sh-kpi-lbl{font-size:.63rem;color:var(--sh-muted);margin-top:4px;text-transform:uppercase;letter-spacing:.8px;font-weight:600;}'
    + '.sh-kpi-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}'
    + '.sh-label{font-size:.72rem;font-weight:700;color:var(--sh-muted);margin-bottom:5px;display:block;}'
    + '.sh-input{width:100%;background:var(--sh-card2);border:1.5px solid var(--sh-border);border-radius:10px;padding:9px 12px;font-size:.88rem;color:var(--sh-text);font-family:inherit;transition:border .15s;outline:none;box-sizing:border-box;}'
    + '.sh-input:focus{border-color:var(--sh-accent);background:var(--sh-card3);}'
    + '.sh-input.big{font-size:1.2rem;font-weight:700;text-align:center;padding:11px;}'
    + '.sh-select{width:100%;background:var(--sh-card2);border:1.5px solid var(--sh-border);border-radius:10px;padding:9px 12px;font-size:.85rem;color:var(--sh-text);font-family:inherit;outline:none;box-sizing:border-box;cursor:pointer;}'
    + '.sh-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 18px;border-radius:10px;font-size:.83rem;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:all .15s;}'
    + '.sh-btn:disabled{opacity:.5;cursor:not-allowed;}'
    + '.sh-btn-success{background:var(--sh-green);color:#fff;}'
    + '.sh-btn-success:hover:not(:disabled){filter:brightness(1.1);box-shadow:0 4px 14px rgba(76,175,130,.35);}'
    + '.sh-btn-danger{background:var(--sh-red);color:#fff;}'
    + '.sh-btn-danger:hover:not(:disabled){filter:brightness(1.1);}'
    + '.sh-btn-ghost{background:var(--sh-card2);color:var(--sh-text);border:1.5px solid var(--sh-border);}'
    + '.sh-btn-ghost:hover:not(:disabled){background:var(--sh-card3);}'
    + '.sh-btn-primary{background:var(--sh-accent);color:#fff;}'
    + '.sh-btn-primary:hover:not(:disabled){filter:brightness(1.1);}'
    + '.sh-btn-sm{padding:6px 12px;font-size:.75rem;border-radius:8px;}'
    + '.sh-btn-xs{padding:4px 8px;font-size:.7rem;border-radius:7px;}'
    + '.sh-btn-icon{width:34px;height:34px;padding:0;border-radius:9px;}'
    + '.sh-btn-icon.sm{width:28px;height:28px;border-radius:7px;}'
    + '.sh-method-btn{display:flex;align-items:center;gap:5px;justify-content:center;padding:8px 6px;border-radius:9px;border:1.5px solid var(--sh-border);background:var(--sh-card2);cursor:pointer;font-size:.72rem;font-weight:600;color:var(--sh-muted);transition:all .15s;font-family:inherit;}'
    + '.sh-method-btn.active{border-color:var(--sh-accent);background:rgba(79,195,247,.1);color:var(--sh-accent);font-weight:700;}'
    + '.sh-type-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;border:1.5px solid var(--sh-border);background:var(--sh-card2);cursor:pointer;font-size:.8rem;font-weight:700;color:var(--sh-muted);transition:all .15s;font-family:inherit;}'
    + '.sh-type-btn.income.active{border-color:var(--sh-green);background:rgba(76,175,130,.12);color:var(--sh-green);}'
    + '.sh-type-btn.expense.active{border-color:var(--sh-red);background:rgba(244,112,103,.12);color:var(--sh-red);}'
    + '.sh-op-row{display:flex;align-items:center;gap:8px;padding:9px 12px;border-bottom:1px solid var(--sh-border);transition:background .12s;}'
    + '.sh-op-row:last-child{border-bottom:none;}'
    + '.sh-op-row:hover{background:rgba(79,195,247,.04);}'
    + '.sh-op-row.editing{background:rgba(245,158,11,.08);}'
    + '.sh-op-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}'
    + '.sh-earn-card{background:linear-gradient(135deg,rgba(200,150,234,.15),rgba(200,150,234,.05));border:1px solid rgba(200,150,234,.3);border-radius:14px;padding:14px 16px;}'
    + '.sh-earn-bar-wrap{height:6px;background:rgba(200,150,234,.15);border-radius:3px;overflow:hidden;margin:8px 0 4px;}'
    + '.sh-earn-bar{height:100%;background:linear-gradient(90deg,var(--sh-purple),#a78bfa);border-radius:3px;transition:width .4s cubic-bezier(.4,0,.2,1);}'
    + '.sh-collapse-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none;background:var(--sh-card);border:1px solid var(--sh-border);border-radius:14px;transition:background .15s;}'
    + '.sh-collapse-hdr:hover{background:var(--sh-card2);}'
    + '.sh-collapse-hdr.open{border-radius:14px 14px 0 0;border-bottom-color:transparent;}'
    + '.sh-collapse-body{background:var(--sh-card);border:1px solid var(--sh-border);border-top:none;border-radius:0 0 14px 14px;overflow:hidden;max-height:0;transition:max-height .35s ease;}'
    + '.sh-collapse-body.open{max-height:3000px;}'
    + '.sh-chevron{transition:transform .25s;display:flex;}'
    + '.sh-chevron.open{transform:rotate(180deg);}'
    + '.sh-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99990;align-items:center;justify-content:center;backdrop-filter:blur(4px);}'
    + '.sh-modal{background:var(--sh-card);border-radius:20px;padding:28px 24px;box-shadow:0 24px 60px rgba(0,0,0,.4);border:1px solid var(--sh-border);position:relative;max-height:90vh;overflow-y:auto;}'
    + '.sh-modal-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;}'
    + '.sh-modal-title{font-size:1.05rem;font-weight:900;color:var(--sh-text);}'
    + '.sh-modal-close{width:32px;height:32px;border-radius:8px;border:none;background:var(--sh-card2);cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--sh-muted);transition:all .15s;}'
    + '.sh-modal-close:hover{background:var(--sh-card3);}'
    + '.sh-upsell-svc{display:flex;flex-direction:column;align-items:center;gap:6px;padding:12px 6px;border-radius:14px;border:2px solid var(--sh-border);background:var(--sh-card2);cursor:pointer;text-align:center;transition:all .18s;font-family:inherit;}'
    + '.sh-upsell-svc:hover{border-color:var(--sh-accent);background:rgba(79,195,247,.06);transform:translateY(-2px);}'
    + '.sh-upsell-svc.selected{border-color:var(--sh-green);background:rgba(76,175,130,.1);}'
    + '.sh-upsell-svc .svc-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto;}'
    + '.sh-upsell-svc .svc-label{font-size:.68rem;font-weight:700;color:var(--sh-text);line-height:1.25;}'
    + '.sh-upsell-svc .svc-price{font-size:.78rem;font-weight:900;color:var(--sh-green);}'
    + '.sh-upsell-svc .svc-check{width:20px;height:20px;border-radius:50%;background:var(--sh-green);display:none;align-items:center;justify-content:center;margin:0 auto;}'
    + '.sh-upsell-svc.selected .svc-check{display:flex;}'
    + '.sh-timer-bar{height:3px;background:var(--sh-border);border-radius:2px;overflow:hidden;}'
    + '.sh-timer-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--sh-amber),var(--sh-green));transition:width .1s linear;}'
    + '.sh-salary-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--sh-border);font-size:.82rem;}'
    + '.sh-salary-row:last-child{border-bottom:none;font-weight:800;font-size:.9rem;}'
    + '.sh-diff-ok{background:rgba(76,175,130,.1);color:var(--sh-green);border:1px solid rgba(76,175,130,.25);border-radius:8px;padding:6px 10px;font-size:.78rem;font-weight:700;}'
    + '.sh-diff-neg{background:rgba(244,112,103,.1);color:var(--sh-red);border:1px solid rgba(244,112,103,.25);border-radius:8px;padding:6px 10px;font-size:.78rem;font-weight:700;}'
    + '.sh-diff-pos{background:rgba(245,158,11,.1);color:var(--sh-amber);border:1px solid rgba(245,158,11,.25);border-radius:8px;padding:6px 10px;font-size:.78rem;font-weight:700;}'
    + '.sh-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.65rem;font-weight:700;}'
    + '.sh-badge-green{background:rgba(76,175,130,.15);color:var(--sh-green);}'
    + '.sh-badge-red{background:rgba(244,112,103,.15);color:var(--sh-red);}'
    + '.sh-badge-amber{background:rgba(245,158,11,.15);color:var(--sh-amber);}'
    + '.sh-badge-blue{background:rgba(79,195,247,.15);color:var(--sh-accent);}'
    + '.sh-table{width:100%;border-collapse:collapse;font-size:.8rem;}'
    + '.sh-table th{padding:8px 12px;text-align:left;font-size:.68rem;font-weight:700;color:var(--sh-muted);text-transform:uppercase;letter-spacing:.6px;background:var(--sh-card2);border-bottom:1px solid var(--sh-border);}'
    + '.sh-table td{padding:9px 12px;border-bottom:1px solid var(--sh-border);color:var(--sh-text);}'
    + '.sh-table tr:last-child td{border-bottom:none;}'
    + '.sh-table tr:hover td{background:rgba(79,195,247,.03);}'
    + '.sh-price-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--sh-card);border:1.5px solid var(--sh-border);border-radius:12px;box-shadow:0 8px 32px var(--sh-shadow);z-index:9999;max-height:280px;overflow-y:auto;}'
    + '.sh-price-opt{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;cursor:pointer;transition:background .1s;font-size:.82rem;}'
    + '.sh-price-opt:hover,.sh-price-opt.focused{background:rgba(79,195,247,.08);}'
    + '.sh-price-opt-group{padding:5px 14px 3px;font-size:.62rem;font-weight:700;color:var(--sh-muted);text-transform:uppercase;letter-spacing:.8px;border-top:1px solid var(--sh-border);margin-top:2px;}'
    + '.sh-price-opt-group:first-child{border-top:none;margin-top:0;}'
    + '.sh-quick-btn{display:flex;flex-direction:column;align-items:center;gap:4px;padding:9px 5px;border-radius:12px;border:1.5px solid;cursor:pointer;transition:all .15s;position:relative;background:var(--sh-card);min-height:72px;font-family:inherit;}'
    + '.sh-quick-btn:hover{transform:translateY(-2px);box-shadow:0 4px 16px var(--sh-shadow);}'
    + '.sh-report-card{background:var(--sh-card);border:1px solid var(--sh-border);border-radius:14px;margin-bottom:12px;overflow:hidden;}'
    + '.sh-report-hdr{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;background:var(--sh-card2);border-bottom:1px solid var(--sh-border);}'
    + '.sh-theme-toggle{display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:20px;border:1.5px solid var(--sh-border);background:var(--sh-card2);cursor:pointer;font-size:.75rem;font-weight:700;color:var(--sh-muted);font-family:inherit;transition:all .15s;}'
    + '.sh-theme-toggle:hover{border-color:var(--sh-accent);color:var(--sh-accent);}'
    + '@keyframes sh_spin{to{transform:rotate(360deg)}}'
    + '.sh-spin{animation:sh_spin .7s linear infinite;display:inline-block;}'
    + '@media(max-width:900px){.sh-kpi{grid-template-columns:repeat(2,1fr);}}'
    + '@media(max-width:700px){.sh-two-col{grid-template-columns:1fr !important;}.sh-quick-grid{grid-template-columns:repeat(3,1fr) !important;}}'
    ;
  document.head.appendChild(st);
})();

/* ================================================================
   РЕГИСТРАЦИЯ МОДУЛЯ
================================================================ */
CRM.registerModule({
  id:    'shift',
  name:  'Касса смены',
  icon:  '💳',
  color: '#f59e0b',

  _current:          null,
  _managers:         [],
  _buttons:          [],
  _priceItems:       [],
  _warehouseItems:   [],
  _topButtons:       [],
  _opType:           'income',
  _opMethod:         'Наличные',
  _activeTab:        'cashier',
  _durationInterval: null,
  _editingOpId:      null,
  _submitLock:       false,
  _openLock:         false,
  _closeLock:        false,
  _longShiftWarned:  false,
  _opsFilter:        '',
  _btnUsage:         {},
  _hotkeyHandler:    null,
  _histOpen:         false,
  _descDropIdx:      -1,
  _priceDropVisible: false,
  _theme:            'dark',

  page: '<div id="sh_root" class="sh-wrap"></div>',

  /* ================================================================
     ТЕМА
  ================================================================ */
  _toggleTheme: function() {
    this._theme = (this._theme === 'dark') ? 'light' : 'dark';
    localStorage.setItem('shift_theme', this._theme);
    document.documentElement.setAttribute('data-shift-theme', this._theme);
    this._updateThemeBtn();
  },

  _updateThemeBtn: function() {
    var btn = document.getElementById('sh_theme_btn');
    if (!btn) return;
    if (this._theme === 'dark') {
      btn.innerHTML = shIcon('sun', 15, '#f59e0b') + ' Светлая';
    } else {
      btn.innerHTML = shIcon('moon', 15, '#8b5cf6') + ' Тёмная';
    }
  },

  /* ================================================================
     ТОПОВЫЕ КНОПКИ ИЗ ПРАЙСА
  ================================================================ */
  _TOP_PRICE_IDS: [1, 5, 6, 3, 12, 14, 16, 2, 7, 4],

  _buildTopButtons: function() {
    var self = this;
    var top  = [];
    this._TOP_PRICE_IDS.forEach(function(pid) {
      var item = null;
      for (var i = 0; i < self._priceItems.length; i++) {
        if (String(self._priceItems[i].id) === String(pid)) { item = self._priceItems[i]; break; }
      }
      if (item && top.length < 10) {
        top.push({
          id:            'price_top_' + item.id,
          label:         item.name,
          amount:        item.price,
          type:          'income',
          icon:          self._guessIcon(item.name, item.category),
          color:         self._catColor(item.category),
          fromPricelist: true,
          unit:          item.unit,
        });
      }
    });
    this._topButtons = top;
  },

  _guessIcon: function(name, cat) {
    var n = ((name || '') + ' ' + (cat || '')).toLowerCase();
    if (n.indexOf('фото')    >= 0) return 'photo';
    if (n.indexOf('баннер')  >= 0) return 'banner';
    if (n.indexOf('широк')   >= 0) return 'banner';
    if (n.indexOf('копи')    >= 0) return 'copy';
    if (n.indexOf('ксерок')  >= 0) return 'copy';
    if (n.indexOf('печат')   >= 0) return 'print';
    if (n.indexOf('ламин')   >= 0) return 'lam';
    if (n.indexOf('перепл')  >= 0) return 'bind';
    if (n.indexOf('визит')   >= 0) return 'card';
    if (n.indexOf('скан')    >= 0) return 'scan';
    if (n.indexOf('дизайн')  >= 0) return 'design';
    if (n.indexOf('макет')   >= 0) return 'design';
    if (n.indexOf('доставк') >= 0) return 'delivery';
    if (n.indexOf('рамк')    >= 0) return 'frame';
    if (n.indexOf('файл')    >= 0) return 'file';
    return 'default';
  },

  _catColor: function(cat) {
    var map = {
      'Фотопечать':     '#f59e0b',
      'Копирование':    '#10b981',
      'Широкий формат': '#3b82f6',
      'Ламинация':      '#8b5cf6',
      'Переплёт':       '#ec4899',
      'Визитки':        '#06b6d4',
      'Дизайн':         '#f97316',
    };
    return map[cat] || '#64748b';
  },

  /* ================================================================
     RENDER
  ================================================================ */
  render: async function() {
    this._theme = localStorage.getItem('shift_theme') || 'dark';
    document.documentElement.setAttribute('data-shift-theme', this._theme);

    var root = document.getElementById('sh_root');
    if (!root) return;
    root.innerHTML = this._buildPage();

    var results = await Promise.all([
      CRM.api('shift',     'managers'),
      CRM.api('shift',     'getButtons'),
      CRM.api('shift',     'current'),
      CRM.api('pricelist', 'list').catch(function() { return {ok:false,data:[]}; }),
      CRM.api('shift',     'getWarehouse').catch(function() { return {ok:false,data:[]}; }),
    ]);

    this._managers       = (results[0] && results[0].data) ? results[0].data : [];
    this._buttons        = (results[1] && results[1].data) ? results[1].data : [];
    this._current        = (results[2] && results[2].data) ? results[2].data : null;
    this._priceItems     = (results[3] && results[3].data) ? results[3].data : [];
    this._warehouseItems = (results[4] && results[4].data) ? results[4].data : [];

    this._buildTopButtons();
    this._btnUsage = JSON.parse(localStorage.getItem('shift_btn_usage') || '{}');

    this._fillManagerSelect();
    this._tab(this._activeTab, true);
    this._updateThemeBtn();

    if (this._current) {
      this._showActive();
    } else {
      this._showOpen();
    }

    this._renderQuickBtns();

    if (this._durationInterval) clearInterval(this._durationInterval);
    if (this._current) {
      var self = this;
      this._durationInterval = setInterval(function() { self._tick(); }, 1000);
    }

    await this._loadHistory();
    await this._loadReports();
    this._renderBtnsSettings();

    this._setMethod('Наличные');
    this._setupHotkeys();
    this._setupDescClose();
    if (this._current) this._restoreDraft();
  },

  /* ================================================================
     HTML СТРАНИЦЫ — все вычисления вынесены в переменные
  ================================================================ */
  _buildPage: function() {

    /* --- переменные-строки (никаких тернарников внутри конкатенации) --- */
    var iconKeys    = ['photo','copy','print','lam','bind','card','scan','design','banner','frame','file','staple','delivery','souvenir','flash','default'];
    var iconOptions = '';
    for (var ki = 0; ki < iconKeys.length; ki++) {
      iconOptions += '<option value="' + iconKeys[ki] + '">' + iconKeys[ki] + '</option>';
    }

    var quickAmounts = [5, 10, 15, 20, 50, 100, 200, 500];
    var amountBtns   = '';
    for (var ai = 0; ai < quickAmounts.length; ai++) {
      amountBtns += '<button class="sh-btn sh-btn-ghost sh-btn-xs"'
        + ' onclick="document.getElementById(\'sh_op_amount\').value=' + quickAmounts[ai]
        + ';CRM.modules.shift._calcTotal()">' + quickAmounts[ai] + '</button>';
    }

    var swatchColors = ['#f59e0b','#10b981','#3b82f6','#8b5cf6','#ef4444','#ec4899','#06b6d4','#84cc16'];
    var colorSwatches = '';
    for (var ci = 0; ci < swatchColors.length; ci++) {
      var sc = swatchColors[ci];
      colorSwatches += '<div'
        + ' onclick="document.getElementById(\'sb_color\').value=\'' + sc + '\'"'
        + ' style="width:20px;height:20px;border-radius:5px;background:' + sc + ';cursor:pointer;border:2px solid transparent;"'
        + ' onmouseover="this.style.borderColor=\'#fff\'"'
        + ' onmouseout="this.style.borderColor=\'transparent\'"></div>';
    }

    var methodDefs = [
      ['sh_m_cash',  'Наличные', 'cash',     'Наличные'],
      ['sh_m_card',  'Карта',    'card',      'Карта'],
      ['sh_m_qr',    'QR/СБП',   'qr',        'QR / СБП'],
      ['sh_m_trans', 'Перевод',  'transfer',  'Перевод'],
    ];
    var methodBtns = '';
    for (var mi = 0; mi < methodDefs.length; mi++) {
      var md = methodDefs[mi];
      methodBtns += '<button id="' + md[0] + '" class="sh-method-btn"'
        + ' onclick="CRM.modules.shift._setMethod(\'' + md[3] + '\')">'
        + shIcon(md[2], 14)
        + '<span style="font-size:.68rem;">' + md[1] + '</span></button>';
    }

    var hotkeyDefs = [
      ['Ctrl+Enter', 'Провести операцию'],
      ['Esc',        'Очистить форму'],
      ['Ctrl+G',     'Фокус на сумму'],
      ['+ / =',      'Переключить приход'],
      ['−',          'Переключить расход'],
      ['1 — 9',      'Быстрые кнопки'],
    ];
    var hotkeyRows = '';
    for (var hi = 0; hi < hotkeyDefs.length; hi++) {
      var hd = hotkeyDefs[hi];
      hotkeyRows += '<div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--sh-card2);border-radius:9px;">'
        + '<span style="font-size:.8rem;color:var(--sh-muted);">' + hd[1] + '</span>'
        + '<kbd style="background:rgba(79,195,247,.1);border:1.5px solid rgba(79,195,247,.25);border-radius:7px;padding:3px 10px;font-size:.75rem;font-weight:700;color:var(--sh-accent);font-family:monospace;">' + hd[0] + '</kbd>'
        + '</div>';
    }

    /* тема — только переменная, никаких тернарников в html */
    var themeLabel = '';
    if ((localStorage.getItem('shift_theme') || 'dark') === 'dark') {
      themeLabel = shIcon('sun', 15, '#f59e0b') + ' Светлая';
    } else {
      themeLabel = shIcon('moon', 15, '#8b5cf6') + ' Тёмная';
    }

    /* ================================================================
       СБОРКА HTML
    ================================================================ */
    var h = '';

    h += '<div style="max-width:1280px;margin:0 auto;padding:20px 16px 40px;">';

    /* --- ШАПКА --- */
    h += '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:22px;">';
    h +=   '<div>';
    h +=     '<div style="font-size:1.3rem;font-weight:900;color:var(--sh-text);display:flex;align-items:center;gap:10px;">';
    h +=       shIcon('cash', 22, '#f59e0b');
    h +=       ' Касса смены <span class="sh-badge sh-badge-amber">v7.0</span>';
    h +=     '</div>';
    h +=     '<div style="font-size:.75rem;color:var(--sh-muted);margin-top:3px;">Синхронизация с финансами · Прайс-лист · Склад</div>';
    h +=   '</div>';
    h +=   '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
    h +=     '<div class="sh-tabs">';
    h +=       '<button class="sh-tab active" id="sh_tab_btn_cashier"  onclick="CRM.modules.shift._tab(\'cashier\')">'  + shIcon('cash',14)     + ' Касса</button>';
    h +=       '<button class="sh-tab"        id="sh_tab_btn_reports"  onclick="CRM.modules.shift._tab(\'reports\')">'  + shIcon('report',14)   + ' Отчёты</button>';
    h +=       '<button class="sh-tab"        id="sh_tab_btn_settings" onclick="CRM.modules.shift._tab(\'settings\')">' + shIcon('settings',14) + ' Кнопки</button>';
    h +=     '</div>';
    h +=     '<button class="sh-theme-toggle" id="sh_theme_btn" onclick="CRM.modules.shift._toggleTheme()">' + themeLabel + '</button>';
    h +=     '<button class="sh-btn sh-btn-ghost sh-btn-icon" onclick="CRM.modules.shift._openHotkeysModal()" title="Горячие клавиши">' + shIcon('hotkey',16) + '</button>';
    h +=     '<button class="sh-btn sh-btn-ghost sh-btn-icon" onclick="CRM.modules.shift.render()" title="Обновить">' + shIcon('refresh',16) + '</button>';
    h +=   '</div>';
    h += '</div>';

    /* === ТАБ КАССА === */
    h += '<div id="sh_tab_cashier">';

    /* Открытие смены */
    h += '<div id="sh_open_block" style="display:none;">';
    h +=   '<div style="max-width:460px;margin:0 auto;">';
    h +=     '<div class="sh-card" style="text-align:center;padding:36px 28px;">';
    h +=       '<div style="width:68px;height:68px;border-radius:18px;background:rgba(245,158,11,.12);border:2px solid rgba(245,158,11,.3);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">' + shIcon('unlock',30,'#f59e0b') + '</div>';
    h +=       '<div style="font-size:1.35rem;font-weight:900;color:var(--sh-text);margin-bottom:6px;">Открыть смену</div>';
    h +=       '<div style="font-size:.78rem;color:var(--sh-muted);margin-bottom:26px;">Операции автоматически попадают в финансовый журнал</div>';
    h +=       '<div style="text-align:left;margin-bottom:12px;">';
    h +=         '<label class="sh-label">' + shIcon('user',13,'var(--sh-muted)') + ' Менеджер смены</label>';
    h +=         '<select class="sh-select" id="sh_manager_sel"><option value="">⏳ Загрузка...</option></select>';
    h +=         '<div id="sh_no_emp" style="display:none;margin-top:8px;"><input class="sh-input" id="sh_manager_manual" placeholder="Введите имя вручную"></div>';
    h +=       '</div>';
    h +=       '<div style="text-align:left;margin-bottom:12px;">';
    h +=         '<label class="sh-label">' + shIcon('cash',13,'var(--sh-muted)') + ' Начальная сумма ₽</label>';
    h +=         '<input class="sh-input big" type="number" id="sh_start_cash" placeholder="0" value="0">';
    h +=         '<div id="sh_start_cash_hint" style="font-size:.7rem;color:var(--sh-green);margin-top:4px;"></div>';
    h +=       '</div>';
    h +=       '<div id="sh_bonus_hint" style="display:none;background:rgba(200,150,234,.1);border:1px solid rgba(200,150,234,.25);border-radius:12px;padding:11px 14px;margin-bottom:16px;text-align:left;">';
    h +=         '<div style="font-size:.72rem;font-weight:700;color:var(--sh-purple);">' + shIcon('gift',13,'var(--sh-purple)') + ' Бонус с продаж</div>';
    h +=         '<div id="sh_bonus_hint_text" style="font-size:.76rem;color:var(--sh-purple);margin-top:4px;opacity:.85;"></div>';
    h +=       '</div>';
    h +=       '<button class="sh-btn sh-btn-success" id="sh_open_btn" style="width:100%;padding:13px;font-size:.92rem;justify-content:center;" onclick="CRM.modules.shift.openShift()">' + shIcon('unlock',17) + ' Открыть смену</button>';
    h +=     '</div>';
    h +=   '</div>';
    h += '</div>';

    /* Активная смена */
    h += '<div id="sh_active_block" style="display:none;">';

    /* KPI */
    h += '<div class="sh-kpi">';
    h +=   '<div class="sh-kpi-item"><div class="sh-kpi-icon" style="background:rgba(79,195,247,.12);">'    + shIcon('cash',20,'var(--sh-accent)')  + '</div><div class="sh-kpi-val" style="color:var(--sh-accent);"  id="sh_stat_cash">0 ₽</div><div class="sh-kpi-lbl">В кассе</div></div>';
    h +=   '<div class="sh-kpi-item"><div class="sh-kpi-icon" style="background:rgba(76,175,130,.12);">'    + shIcon('income',20,'var(--sh-green)')  + '</div><div class="sh-kpi-val" style="color:var(--sh-green);"  id="sh_stat_income">0 ₽</div><div class="sh-kpi-lbl">Доход</div></div>';
    h +=   '<div class="sh-kpi-item"><div class="sh-kpi-icon" style="background:rgba(244,112,103,.12);">'   + shIcon('expense',20,'var(--sh-red)')   + '</div><div class="sh-kpi-val" style="color:var(--sh-red);"   id="sh_stat_expense">0 ₽</div><div class="sh-kpi-lbl">Расход</div></div>';
    h +=   '<div class="sh-kpi-item"><div class="sh-kpi-icon" style="background:rgba(200,150,234,.12);">'   + shIcon('clock',20,'var(--sh-purple)')  + '</div><div class="sh-kpi-val" style="color:var(--sh-purple);" id="sh_stat_dur">0ч 00м</div><div class="sh-kpi-lbl">Время</div></div>';
    h += '</div>';

    /* Менеджер + заработок */
    h += '<div style="display:grid;grid-template-columns:1fr auto;gap:12px;margin-bottom:18px;align-items:start;" class="sh-two-col">';
    h +=   '<div class="sh-card-sm" style="display:flex;align-items:center;gap:12px;">';
    h +=     '<div style="width:40px;height:40px;border-radius:11px;background:rgba(245,158,11,.12);border:1.5px solid rgba(245,158,11,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;">' + shIcon('user',20,'#f59e0b') + '</div>';
    h +=     '<div style="flex:1;min-width:0;"><div style="font-weight:800;font-size:.92rem;color:var(--sh-text);" id="sh_hdr_manager">—</div><div style="font-size:.7rem;color:var(--sh-muted);" id="sh_hdr_time">—</div></div>';
    h +=   '</div>';
    h +=   '<div class="sh-earn-card" style="min-width:210px;">';
    h +=     '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">';
    h +=       '<div style="font-size:.7rem;font-weight:700;color:var(--sh-purple);display:flex;align-items:center;gap:4px;">' + shIcon('star',12,'var(--sh-purple)') + ' Заработок</div>';
    h +=       '<div style="font-size:1.05rem;font-weight:900;color:var(--sh-purple);" id="sh_earn_total">0 ₽</div>';
    h +=     '</div>';
    h +=     '<div class="sh-earn-bar-wrap"><div class="sh-earn-bar" id="sh_earn_bar" style="width:0%"></div></div>';
    h +=     '<div style="display:flex;justify-content:space-between;font-size:.67rem;color:var(--sh-purple);">';
    h +=       '<span>Бонус <b id="sh_earn_pct">0.5</b>% · <span id="sh_earn_income">0 ₽</span></span>';
    h +=       '<span id="sh_earn_bonus_lbl">+0 ₽</span>';
    h +=     '</div>';
    h +=     '<div style="font-size:.67rem;color:var(--sh-purple);margin-top:2px;">+ оклад: <b id="sh_earn_base_preview">—</b></div>';
    h +=   '</div>';
    h += '</div>';

    /* 2 колонки */
    h += '<div style="display:grid;grid-template-columns:1fr 350px;gap:14px;align-items:start;" class="sh-two-col">';

    /* ЛЕВАЯ */
    h += '<div>';

    /* Быстрые кнопки из прайса */
    h += '<div style="margin-bottom:14px;">';
    h +=   '<div style="font-size:.67rem;font-weight:700;color:var(--sh-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">' + shIcon('flash',12,'#f59e0b') + ' Ходовые услуги</div>';
    h +=   '<div id="sh_quick_btns_top" class="sh-quick-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-bottom:10px;"></div>';
    h +=   '<div style="font-size:.67rem;font-weight:700;color:var(--sh-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;display:flex;align-items:center;gap:5px;">' + shIcon('settings',12,'var(--sh-muted)') + ' Мои кнопки</div>';
    h +=   '<div id="sh_quick_btns" class="sh-quick-grid" style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;"></div>';
    h += '</div>';

    /* Форма операции */
    h += '<div class="sh-card">';
    h +=   '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">';
    h +=     '<div style="font-size:.83rem;font-weight:800;color:var(--sh-text);display:flex;align-items:center;gap:6px;" id="sh_form_title">' + shIcon('edit',16,'var(--sh-muted)') + ' Новая операция</div>';
    h +=     '<div style="display:flex;gap:6px;">';
    h +=       '<button id="sh_btn_income"  class="sh-type-btn income active"  onclick="CRM.modules.shift._setType(\'income\')">'  + shIcon('income',14)  + ' Приход</button>';
    h +=       '<button id="sh_btn_expense" class="sh-type-btn expense"        onclick="CRM.modules.shift._setType(\'expense\')">' + shIcon('expense',14) + ' Расход</button>';
    h +=     '</div>';
    h +=   '</div>';
    h +=   '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">';
    h +=     '<div>';
    h +=       '<label class="sh-label">Цена за 1 шт. ₽</label>';
    h +=       '<input class="sh-input big" type="number" id="sh_op_amount" placeholder="0" oninput="CRM.modules.shift._calcTotal()">';
    h +=       '<div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">' + amountBtns + '</div>';
    h +=     '</div>';
    h +=     '<div>';
    h +=       '<label class="sh-label">Количество</label>';
    h +=       '<div style="display:flex;align-items:center;gap:8px;">';
    h +=         '<button class="sh-btn sh-btn-ghost sh-btn-icon" onclick="CRM.modules.shift._changeQty(-1)">' + shIcon('minus',14) + '</button>';
    h +=         '<input class="sh-input" type="number" id="sh_op_qty" value="1" min="1" style="text-align:center;font-size:1.1rem;font-weight:700;width:60px;padding:8px;" oninput="CRM.modules.shift._calcTotal()">';
    h +=         '<button class="sh-btn sh-btn-ghost sh-btn-icon" onclick="CRM.modules.shift._changeQty(1)">'  + shIcon('plus',14)  + '</button>';
    h +=       '</div>';
    h +=       '<div id="sh_op_total_preview" style="margin-top:6px;font-size:.82rem;font-weight:800;text-align:center;min-height:18px;"></div>';
    h +=     '</div>';
    h +=   '</div>';
    h +=   '<div style="margin-bottom:12px;position:relative;">';
    h +=     '<label class="sh-label">Услуга / описание</label>';
    h +=     '<input class="sh-input" id="sh_op_desc" placeholder="Начните вводить — покажем прайс..." autocomplete="off"';
    h +=       ' oninput="CRM.modules.shift._onDescInput(this.value)"';
    h +=       ' onkeydown="CRM.modules.shift._onDescKey(event)"';
    h +=       ' onfocus="CRM.modules.shift._onDescFocus()">';
    h +=     '<div id="sh_price_dropdown" class="sh-price-dropdown" style="display:none;"></div>';
    h +=   '</div>';
    h +=   '<div style="margin-bottom:12px;">';
    h +=     '<label class="sh-label">Метод оплаты</label>';
    h +=     '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">' + methodBtns + '</div>';
    h +=     '<input type="hidden" id="sh_op_method" value="Наличные">';
    h +=   '</div>';
    h +=   '<div id="sh_change_block" style="background:rgba(76,175,130,.07);border:1.5px solid rgba(76,175,130,.2);border-radius:10px;padding:11px;margin-bottom:12px;">';
    h +=     '<div style="font-size:.7rem;font-weight:700;color:var(--sh-green);margin-bottom:8px;">' + shIcon('cash',13,'var(--sh-green)') + ' Расчёт сдачи</div>';
    h +=     '<div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:10px;">';
    h +=       '<div><label class="sh-label" style="font-size:.64rem;">Клиент дал ₽</label><input class="sh-input" type="number" id="sh_given_cash" placeholder="0" oninput="CRM.modules.shift._calcChange()" style="text-align:center;font-weight:700;"></div>';
    h +=       '<div style="font-size:1.1rem;color:var(--sh-muted);">→</div>';
    h +=       '<div style="text-align:center;"><div style="font-size:.64rem;color:var(--sh-muted);">Сдача</div><div id="sh_change_result" style="font-size:1.35rem;font-weight:900;color:var(--sh-green);">— ₽</div></div>';
    h +=     '</div>';
    h +=   '</div>';
    h +=   '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;">';
    h +=     '<button class="sh-btn sh-btn-success" id="sh_submit_btn" style="padding:11px;font-size:.88rem;justify-content:center;" onclick="CRM.modules.shift.submitOp()">' + shIcon('income',16) + ' Провести приход</button>';
    h +=     '<button class="sh-btn sh-btn-ghost sh-btn-icon" style="width:42px;height:42px;" onclick="CRM.modules.shift._clearForm()" title="Очистить">' + shIcon('trash',16,'var(--sh-red)') + '</button>';
    h +=   '</div>';
    h +=   '<div id="sh_edit_cancel_row" style="display:none;margin-top:8px;">';
    h +=     '<button class="sh-btn sh-btn-ghost" style="width:100%;justify-content:center;font-size:.76rem;" onclick="CRM.modules.shift._cancelEdit()">' + shIcon('close_x',13) + ' Отменить редактирование</button>';
    h +=   '</div>';
    h += '</div>';

    /* Закрытие смены */
    h += '<div class="sh-card" style="margin-top:14px;border:1.5px solid rgba(244,112,103,.2);">';
    h +=   '<div class="sh-card-title" style="color:var(--sh-red);">' + shIcon('lock',17,'var(--sh-red)') + ' Закрыть смену</div>';
    h +=   '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
    h +=     '<div>';
    h +=       '<label class="sh-label">Фактически в кассе ₽</label>';
    h +=       '<input class="sh-input big" type="number" id="sh_end_cash" placeholder="0" oninput="CRM.modules.shift._onEndCashInput(this.value)">';
    h +=       '<div style="font-size:.7rem;color:var(--sh-muted);margin-top:4px;" id="sh_expected_hint">Ожидается: 0 ₽</div>';
    h +=       '<div id="sh_diff_indicator" style="margin-top:6px;display:none;"></div>';
    h +=     '</div>';
    h +=     '<div>';
    h +=       '<label class="sh-label">Оклад за день ₽ <span id="sh_close_bonus_lbl" style="color:var(--sh-purple);font-size:.68rem;font-weight:400;"></span></label>';
    h +=       '<input class="sh-input big" type="number" id="sh_base_salary" placeholder="0" value="0" oninput="CRM.modules.shift._updateSalaryPreview()">';
    h +=     '</div>';
    h +=     '<div style="grid-column:1/-1;">';
    h +=       '<label class="sh-label">Заметки</label>';
    h +=       '<input class="sh-input" id="sh_close_note" placeholder="Передать заказы, материалы...">';
    h +=     '</div>';
    h +=   '</div>';
    h +=   '<div style="margin:12px 0;background:var(--sh-card2);border:1px solid var(--sh-border);border-radius:12px;overflow:hidden;">';
    h +=     '<div style="padding:9px 14px;background:var(--sh-card3);border-bottom:1px solid var(--sh-border);font-size:.7rem;font-weight:700;color:var(--sh-muted);">' + shIcon('chart',13) + ' Расчёт выплаты</div>';
    h +=     '<div id="sh_salary_rows" style="padding:0 14px;"></div>';
    h +=     '<div style="padding:11px 14px;border-top:1px solid var(--sh-border);">';
    h +=       '<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">';
    h +=         '<input type="checkbox" id="sh_salary_paid_cb" style="width:16px;height:16px;margin-top:2px;cursor:pointer;accent-color:var(--sh-green);">';
    h +=         '<div>';
    h +=           '<div style="font-size:.78rem;font-weight:700;color:var(--sh-text);">Подтверждаю: зарплата выплачена</div>';
    h +=           '<div style="font-size:.7rem;color:var(--sh-muted);margin-top:2px;" id="sh_salary_confirm_text">Итого: —</div>';
    h +=         '</div>';
    h +=       '</label>';
    h +=       '<div id="sh_salary_zero_note" style="display:none;font-size:.7rem;color:var(--sh-muted);padding:7px 10px;background:var(--sh-card2);border-radius:8px;margin-top:8px;">' + shIcon('warn',12,'var(--sh-amber)') + ' Оклад = 0 ₽ — ЗП не записывается в расходы</div>';
    h +=     '</div>';
    h +=   '</div>';
    h +=   '<button class="sh-btn sh-btn-danger" id="sh_close_btn" style="padding:12px 20px;font-size:.88rem;justify-content:center;width:100%;" onclick="CRM.modules.shift.closeShift()">' + shIcon('lock',16) + ' Закрыть смену и сформировать Z-отчёт</button>';
    h += '</div>';

    h += '</div>'; /* конец левой колонки */

    /* ПРАВАЯ — журнал */
    h += '<div>';
    h +=   '<div class="sh-card" style="position:sticky;top:80px;padding:14px;">';
    h +=     '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">';
    h +=       '<div style="font-weight:800;font-size:.83rem;color:var(--sh-text);display:flex;align-items:center;gap:6px;">' + shIcon('report',15) + ' Операции</div>';
    h +=       '<span style="font-size:.7rem;color:var(--sh-muted);" id="sh_ops_summary"></span>';
    h +=     '</div>';
    h +=     '<input class="sh-input" id="sh_ops_search" placeholder="Поиск..." style="font-size:.76rem;padding:7px 10px;margin-bottom:8px;" oninput="CRM.modules.shift._opsFilter=this.value;CRM.modules.shift._renderOps()">';
    h +=     '<div id="sh_ops_stats" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:8px;"></div>';
    h +=     '<div id="sh_ops_list" style="max-height:calc(100vh - 340px);overflow-y:auto;"></div>';
    h +=   '</div>';
    h += '</div>';

    h += '</div>'; /* конец 2-col grid */
    h += '</div>'; /* конец sh_active_block */

    /* История */
    h += '<div style="margin-top:20px;">';
    h +=   '<div class="sh-collapse-hdr" id="sh_hist_hdr" onclick="CRM.modules.shift._toggleHistory()">';
    h +=     '<div style="display:flex;align-items:center;gap:8px;font-weight:800;color:var(--sh-text);">' + shIcon('clock',16,'var(--sh-muted)') + ' История смен <span class="sh-badge sh-badge-blue" id="sh_hist_count">0</span></div>';
    h +=     '<div class="sh-chevron" id="sh_hist_chevron">' + shIcon('chevron',16) + '</div>';
    h +=   '</div>';
    h +=   '<div class="sh-collapse-body" id="sh_hist_body_wrap">';
    h +=     '<div style="padding:2px 4px 6px;overflow-x:auto;">';
    h +=       '<table class="sh-table"><thead><tr>';
    h +=         '<th>Дата</th><th>Менеджер</th><th>Длит.</th><th>Доход</th><th>Расход</th><th>Прибыль</th><th>Факт</th><th>Расхожд.</th><th>ЗП</th><th></th>';
    h +=       '</tr></thead><tbody id="sh_history_body"></tbody></table>';
    h +=     '</div>';
    h +=   '</div>';
    h += '</div>';

    h += '</div>'; /* конец sh_tab_cashier */

    /* === ТАБ ОТЧЁТЫ === */
    h += '<div id="sh_tab_reports" style="display:none;"><div id="sh_reports_list"></div></div>';

    /* === ТАБ КНОПКИ === */
    h += '<div id="sh_tab_settings" style="display:none;">';
    h +=   '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="sh-two-col">';

    h +=     '<div class="sh-card">';
    h +=       '<div class="sh-card-title">' + shIcon('settings',16,'var(--sh-amber)') + ' Мои кнопки</div>';
    h +=       '<div id="sh_btns_list" style="margin-bottom:14px;"></div>';
    h +=       '<div style="background:var(--sh-card2);border-radius:12px;padding:14px;border:1px solid var(--sh-border);">';
    h +=         '<div style="font-weight:700;font-size:.8rem;margin-bottom:11px;color:var(--sh-text);">Добавить кнопку</div>';
    h +=         '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
    h +=           '<div><label class="sh-label">Название</label><input class="sh-input" id="sb_label" placeholder="Фото 10×15" onkeydown="if(event.key===\'Enter\')CRM.modules.shift._addButton()"></div>';
    h +=           '<div><label class="sh-label">Цена ₽</label><input class="sh-input" type="number" id="sb_amount" placeholder="0" onkeydown="if(event.key===\'Enter\')CRM.modules.shift._addButton()"></div>';
    h +=           '<div><label class="sh-label">Тип</label><select class="sh-select" id="sb_type"><option value="income">▲ Приход</option><option value="expense">▼ Расход</option></select></div>';
    h +=           '<div><label class="sh-label">Иконка</label><select class="sh-select" id="sb_icon">' + iconOptions + '</select></div>';
    h +=           '<div style="grid-column:1/-1;">';
    h +=             '<label class="sh-label">Цвет</label>';
    h +=             '<div style="display:flex;gap:8px;align-items:center;">';
    h +=               '<input type="color" id="sb_color" value="#f59e0b" style="width:38px;height:32px;border-radius:7px;border:1.5px solid var(--sh-border);cursor:pointer;padding:2px;">';
    h +=               '<div style="display:flex;gap:4px;flex-wrap:wrap;">' + colorSwatches + '</div>';
    h +=             '</div>';
    h +=           '</div>';
    h +=         '</div>';
    h +=         '<button class="sh-btn sh-btn-success sh-btn-sm" style="width:100%;margin-top:10px;justify-content:center;" onclick="CRM.modules.shift._addButton()">' + shIcon('plus',14) + ' Добавить</button>';
    h +=       '</div>';
    h +=     '</div>';

    h +=     '<div class="sh-card">';
    h +=       '<div class="sh-card-title">' + shIcon('check',16,'var(--sh-green)') + ' Превью</div>';
    h +=       '<div id="sh_btns_preview" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;"></div>';
    h +=       '<div id="sh_btns_preview_empty" style="display:none;text-align:center;padding:28px;color:var(--sh-muted);font-size:.78rem;">Добавьте кнопки слева</div>';
    h +=     '</div>';

    h +=   '</div>';
    h += '</div>';

    h += '</div>'; /* max-width */

    /* === МОДАЛКИ === */

    /* UPSELL */
    h += '<div class="sh-overlay" id="sh_upsell_overlay">';
    h +=   '<div class="sh-modal" style="width:520px;max-width:96vw;">';
    h +=     '<div class="sh-modal-hdr">';
    h +=       '<div style="display:flex;align-items:center;gap:10px;">';
    h +=         '<div style="width:38px;height:38px;border-radius:11px;background:rgba(245,158,11,.15);border:1.5px solid rgba(245,158,11,.35);display:flex;align-items:center;justify-content:center;">' + shIcon('upsell',20,'#f59e0b') + '</div>';
    h +=         '<div><div class="sh-modal-title">Предложите клиенту доп. услугу</div><div id="sh_upsell_tip" style="font-size:.72rem;color:var(--sh-muted);"></div></div>';
    h +=       '</div>';
    h +=       '<button class="sh-modal-close" onclick="CRM.modules.shift._upsellSkip()">' + shIcon('close_x',14) + '</button>';
    h +=     '</div>';
    h +=     '<div class="sh-timer-bar" style="margin-bottom:14px;"><div class="sh-timer-fill" id="sh_upsell_timer_bar" style="width:100%;"></div></div>';
    h +=     '<div style="background:var(--sh-card2);border:1.5px solid var(--sh-border);border-radius:11px;padding:11px 14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">';
    h +=       '<div><div style="font-size:.67rem;color:var(--sh-muted);">Основная услуга</div><div style="font-weight:700;color:var(--sh-text);font-size:.86rem;" id="sh_upsell_base_label">—</div></div>';
    h +=       '<div style="font-size:1.05rem;font-weight:900;color:var(--sh-green);" id="sh_upsell_base_price">—</div>';
    h +=     '</div>';
    h +=     '<div id="sh_upsell_source_badge" style="font-size:.65rem;color:var(--sh-muted);margin-bottom:8px;"></div>';
    h +=     '<div id="sh_upsell_list" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;"></div>';
    h +=     '<div id="sh_upsell_selected_block" style="display:none;background:rgba(76,175,130,.07);border:1.5px solid rgba(76,175,130,.2);border-radius:11px;padding:11px 14px;margin-bottom:12px;">';
    h +=       '<div style="font-size:.7rem;font-weight:700;color:var(--sh-green);margin-bottom:5px;">' + shIcon('check',13,'var(--sh-green)') + ' Добавлено</div>';
    h +=       '<div id="sh_upsell_selected_list"></div>';
    h +=     '</div>';
    h +=     '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">';
    h +=       '<button class="sh-btn sh-btn-success" id="sh_upsell_confirm_btn" style="justify-content:center;padding:11px;" onclick="CRM.modules.shift._upsellConfirm()">' + shIcon('check',16) + ' Оплатить всё</button>';
    h +=       '<button class="sh-btn sh-btn-ghost" style="justify-content:center;" onclick="CRM.modules.shift._upsellSkip()">Без допов →</button>';
    h +=     '</div>';
    h +=     '<div style="text-align:center;margin-top:8px;font-size:.66rem;color:var(--sh-muted);">Esc или таймер — продолжит автоматически</div>';
    h +=   '</div>';
    h += '</div>';

    /* QR */
    h += '<div class="sh-overlay" id="sh_qr_overlay">';
    h +=   '<div class="sh-modal" style="width:360px;max-width:95vw;text-align:center;">';
    h +=     '<button class="sh-modal-close" style="position:absolute;top:14px;right:14px;" onclick="CRM.modules.shift._closeQR()">' + shIcon('close_x',14) + '</button>';
    h +=     '<div style="font-size:.63rem;text-transform:uppercase;letter-spacing:1.5px;color:var(--sh-muted);font-weight:700;margin-bottom:8px;">Оплата QR / СБП</div>';
    h +=     '<div style="font-size:2rem;font-weight:900;color:var(--sh-green);margin-bottom:4px;" id="sh_qr_amount_lbl">—</div>';
    h +=     '<div style="font-size:.78rem;color:var(--sh-muted);margin-bottom:16px;" id="sh_qr_desc_lbl">—</div>';
    h +=     '<div id="sh_qr_box" style="width:220px;height:220px;margin:0 auto 16px;background:white;border-radius:16px;padding:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 24px rgba(0,0,0,.3);border:2px solid var(--sh-border);position:relative;overflow:hidden;">';
    h +=       '<div id="sh_qr_loader" style="text-align:center;"><div style="width:32px;height:32px;border:3px solid rgba(79,195,247,.2);border-top-color:var(--sh-accent);border-radius:50%;margin:0 auto 8px;" class="sh-spin"></div><div style="font-size:.7rem;color:#999;">Создаём...</div></div>';
    h +=       '<img id="sh_qr_img" src="" alt="QR" style="display:none;width:200px;height:200px;border-radius:6px;">';
    h +=       '<div id="sh_qr_paid_overlay" style="display:none;position:absolute;inset:0;background:rgba(76,175,130,.94);border-radius:14px;flex-direction:column;align-items:center;justify-content:center;">';
    h +=         '<div style="width:48px;height:48px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:8px;">' + shIcon('check',24,'#10b981') + '</div>';
    h +=         '<div style="font-weight:900;color:#fff;font-size:.95rem;">Оплачено!</div>';
    h +=       '</div>';
    h +=     '</div>';
    h +=     '<div id="sh_qr_status" class="sh-badge sh-badge-amber" style="margin-bottom:14px;padding:6px 18px;">Создаём...</div>';
    h +=     '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:11px;">';
    h +=       '<button class="sh-btn sh-btn-primary sh-btn-sm" id="sh_qr_check_btn" onclick="CRM.modules.shift._qrCheckOnce()" disabled style="justify-content:center;">' + shIcon('refresh',13) + ' Проверить</button>';
    h +=       '<button class="sh-btn sh-btn-ghost sh-btn-sm"   id="sh_qr_print_btn" onclick="CRM.modules.shift._qrPrint()"    disabled style="justify-content:center;">Распечатать QR</button>';
    h +=     '</div>';
    h +=     '<label style="cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-size:.72rem;color:var(--sh-muted);">';
    h +=       '<input type="checkbox" id="sh_qr_autopoll_cb" onchange="CRM.modules.shift._qrTogglePoll(this.checked)"> Автопроверка каждые 10 сек.';
    h +=     '</label>';
    h +=     '<div id="sh_qr_poll_status" style="font-size:.68rem;color:var(--sh-muted);margin-top:4px;min-height:14px;"></div>';
    h +=   '</div>';
    h += '</div>';

    /* ГОРЯЧИЕ КЛАВИШИ */
    h += '<div class="sh-overlay" id="sh_hotkeys_overlay">';
    h +=   '<div class="sh-modal" style="width:380px;max-width:95vw;">';
    h +=     '<div class="sh-modal-hdr"><div class="sh-modal-title" style="display:flex;align-items:center;gap:8px;">' + shIcon('hotkey',18) + ' Горячие клавиши</div><button class="sh-modal-close" onclick="CRM.modules.shift._closeHotkeysModal()">' + shIcon('close_x',14) + '</button></div>';
    h +=     '<div style="display:flex;flex-direction:column;gap:7px;">' + hotkeyRows + '</div>';
    h +=   '</div>';
    h += '</div>';

    return h;
  },

  /* ================================================================
     ПРАЙС ДРОПДАУН
  ================================================================ */
  _setupDescClose: function() {
    var self = this;
    document.addEventListener('click', function(e) {
      var dd  = document.getElementById('sh_price_dropdown');
      var inp = document.getElementById('sh_op_desc');
      if (dd && !dd.contains(e.target) && e.target !== inp) {
        dd.style.display = 'none';
        self._priceDropVisible = false;
      }
    });
  },

  _onDescFocus: function() {
    var val = document.getElementById('sh_op_desc') ? document.getElementById('sh_op_desc').value : '';
    this._showPriceDrop(val);
  },

  _onDescInput: function(val) {
    this._saveDraft();
    this._calcTotal();
    this._showPriceDrop(val);
  },

  _showPriceDrop: function(query) {
    var dd = document.getElementById('sh_price_dropdown');
    if (!dd) return;
    var q     = (query || '').trim().toLowerCase();
    var items = this._priceItems.slice();
    if (q) {
      items = items.filter(function(i) {
        return i.name.toLowerCase().indexOf(q) >= 0
          || (i.category || '').toLowerCase().indexOf(q) >= 0
          || (i.desc     || '').toLowerCase().indexOf(q) >= 0;
      });
    }
    if (!items.length) { dd.style.display = 'none'; this._priceDropVisible = false; return; }

    var cats = [];
    for (var i = 0; i < items.length; i++) {
      if (cats.indexOf(items[i].category) < 0) cats.push(items[i].category);
    }

    var self = this;
    var html = '';
    for (var ci = 0; ci < cats.length; ci++) {
      var cat = cats[ci];
      html += '<div class="sh-price-opt-group">' + cat + '</div>';
      for (var ii = 0; ii < items.length; ii++) {
        var item = items[ii];
        if (item.category !== cat) continue;
        var dj = JSON.stringify(item).replace(/"/g, '&quot;');
        html += '<div class="sh-price-opt" onclick="CRM.modules.shift._selectPriceItem(' + dj + ')">'
          + '<div>'
          +   '<div style="font-weight:600;">' + self._esc(item.name) + '</div>'
          +   (item.desc ? '<div style="font-size:.62rem;color:var(--sh-muted);">' + self._esc(item.desc) + '</div>' : '')
          + '</div>'
          + '<div style="font-weight:800;color:var(--sh-green);white-space:nowrap;">'
          +   formatMoney(item.price)
          +   '<span style="font-size:.63rem;color:var(--sh-muted);font-weight:400;">/' + item.unit + '</span>'
          + '</div>'
          + '</div>';
      }
    }

    dd.innerHTML = html;
    dd.style.display = 'block';
    this._priceDropVisible = true;
    this._descDropIdx = -1;
  },

  _selectPriceItem: function(item) {
    var descEl = document.getElementById('sh_op_desc');
    var amtEl  = document.getElementById('sh_op_amount');
    var dd     = document.getElementById('sh_price_dropdown');
    if (descEl) descEl.value = item.name;
    if (amtEl)  amtEl.value  = item.price;
    if (dd)     dd.style.display = 'none';
    this._priceDropVisible = false;
    this._calcTotal();
    this._saveDraft();
    var cat = (item.category || '').toLowerCase();
    if (cat.indexOf('расход') >= 0 || cat.indexOf('аренд') >= 0 || cat.indexOf('налог') >= 0) {
      this._setType('expense');
    } else {
      this._setType('income');
    }
  },

  _onDescKey: function(e) {
    var dd = document.getElementById('sh_price_dropdown');
    if (!this._priceDropVisible || !dd) {
      if (e.key === 'Enter') { CRM.modules.shift.submitOp(); }
      return;
    }
    var opts = dd.querySelectorAll('.sh-price-opt');
    if (!opts.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      this._descDropIdx = Math.min(this._descDropIdx + 1, opts.length - 1);
      for (var i = 0; i < opts.length; i++) {
        opts[i].className = 'sh-price-opt' + (i === this._descDropIdx ? ' focused' : '');
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      this._descDropIdx = Math.max(this._descDropIdx - 1, 0);
      for (var j = 0; j < opts.length; j++) {
        opts[j].className = 'sh-price-opt' + (j === this._descDropIdx ? ' focused' : '');
      }
    } else if (e.key === 'Enter' && this._descDropIdx >= 0) {
      e.preventDefault();
      if (opts[this._descDropIdx]) opts[this._descDropIdx].click();
    } else if (e.key === 'Escape') {
      dd.style.display = 'none';
      this._priceDropVisible = false;
    }
  },

  /* ================================================================
     UPSELL — склад → fallback
  ================================================================ */
  _upsellRules: [
    { match:['фото','снимок','10×15','15×21','21×30','паспорт','виза','3×4'], tip:'Фото — предложите рамку или ламинацию',
      services:[{icon:'frame',label:'Рамка 10×15',price:150,color:'#f59e0b'},{icon:'lam',label:'Ламинация А4',price:60,color:'#8b5cf6'},{icon:'file',label:'Файл А4',price:10,color:'#3b82f6'},{icon:'flash',label:'Запись флешку',price:100,color:'#10b981'},{icon:'scan',label:'Сканирование',price:30,color:'#06b6d4'}]},
    { match:['копи','ксерокс','копир'], tip:'Копирование — сшивка, файл, ламинация',
      services:[{icon:'staple',label:'Степлерование',price:20,color:'#f59e0b'},{icon:'file',label:'Файл А4',price:10,color:'#3b82f6'},{icon:'lam',label:'Ламинация А4',price:60,color:'#8b5cf6'},{icon:'bind',label:'Переплёт',price:120,color:'#10b981'}]},
    { match:['печат','распечат','договор','деклар','заявл','чертёж'], tip:'Документ — переплёт, ламинация',
      services:[{icon:'bind',label:'Переплёт пружина',price:120,color:'#10b981'},{icon:'staple',label:'Степлерование',price:20,color:'#f59e0b'},{icon:'lam',label:'Ламинация А4',price:60,color:'#8b5cf6'},{icon:'file',label:'Файл А4',price:10,color:'#3b82f6'}]},
    { match:['баннер','широкоформ','плакат','постер','стенд'], tip:'Широкоформат — люверсы или доставка',
      services:[{icon:'staple',label:'Люверсы',price:200,color:'#f59e0b'},{icon:'lam',label:'Ламинация А2',price:200,color:'#8b5cf6'},{icon:'delivery',label:'Доставка',price:500,color:'#3b82f6'}]},
    { match:['визит','листовк','флаер','буклет','наклейк'], tip:'Полиграфия — ламинация или дизайн',
      services:[{icon:'lam',label:'Ламинация визиток',price:150,color:'#8b5cf6'},{icon:'design',label:'Дизайн/правка',price:300,color:'#f59e0b'},{icon:'delivery',label:'Доставка',price:300,color:'#06b6d4'}]},
    { match:['ламин'], tip:'Ламинация — обрезка или папка',
      services:[{icon:'staple',label:'Обрезка краёв',price:50,color:'#f59e0b'},{icon:'file',label:'Папка-уголок',price:30,color:'#3b82f6'}]},
    { match:['перепл'], tip:'Переплёт — цветная обложка',
      services:[{icon:'print',label:'Обложка А4 цвет',price:30,color:'#3b82f6'},{icon:'staple',label:'Подрезка',price:80,color:'#f59e0b'}]},
    { match:['дизайн','макет'], tip:'Дизайн — печать макета',
      services:[{icon:'print',label:'Печать А4 цвет',price:20,color:'#3b82f6'},{icon:'card',label:'Визитки 100шт',price:450,color:'#10b981'},{icon:'lam',label:'Ламинация',price:60,color:'#8b5cf6'}]},
  ],

  _getUpsellsFromWarehouse: function(desc) {
    if (!this._warehouseItems || !this._warehouseItems.length) return null;
    var d = (desc || '').toLowerCase();
    var words = d.split(/\s+/).filter(function(w) { return w.length > 3; });
    if (!words.length) return null;
    var scored = [];
    for (var i = 0; i < this._warehouseItems.length; i++) {
      var item = this._warehouseItems[i];
      var n    = ((item.name || item.title || '')).toLowerCase();
      var score = 0;
      for (var j = 0; j < words.length; j++) {
        if (n.indexOf(words[j]) >= 0) score++;
      }
      if (score > 0 && parseFloat(item.price || item.cost || 0) > 0) {
        scored.push({ score: score, item: item });
      }
    }
    if (!scored.length) return null;
    scored.sort(function(a, b) { return b.score - a.score; });
    var services = [];
    for (var k = 0; k < Math.min(5, scored.length); k++) {
      var it = scored[k].item;
      services.push({
        icon:          'box',
        label:         it.name || it.title || 'Товар',
        price:         parseFloat(it.price || it.cost || 0),
        color:         '#06b6d4',
        desc:          it.desc || it.description || '',
        fromWarehouse: true,
      });
    }
    return { tip: 'Сопутствующие товары со склада', services: services, fromWarehouse: true };
  },

  _getUpsells: function(desc) {
    var wh = this._getUpsellsFromWarehouse(desc);
    if (wh) return wh;
    if (!desc) return null;
    var d    = desc.toLowerCase();
    var best = null, bestScore = 0;
    for (var i = 0; i < this._upsellRules.length; i++) {
      var rule  = this._upsellRules[i];
      var score = 0;
      for (var j = 0; j < rule.match.length; j++) {
        if (d.indexOf(rule.match[j]) >= 0) score++;
      }
      if (score > bestScore) { bestScore = score; best = rule; }
    }
    return best;
  },

  _upsellPending:  null,
  _upsellSelected: [],
  _upsellTimerIv:  null,
  _upsellTimeLeft: 9,
  _upsellMainAmt:  0,

  _upsellShow: function(desc, amount, onConfirm) {
    var rule = this._getUpsells(desc);
    if (!rule) { onConfirm([]); return; }

    this._upsellSelected = [];
    this._upsellPending  = onConfirm;
    this._upsellMainAmt  = amount;

    var el = function(id) { return document.getElementById(id); };
    if (el('sh_upsell_tip'))          el('sh_upsell_tip').textContent          = rule.tip;
    if (el('sh_upsell_base_label'))   el('sh_upsell_base_label').textContent   = desc;
    if (el('sh_upsell_base_price'))   el('sh_upsell_base_price').textContent   = formatMoney(amount);
    if (el('sh_upsell_selected_block')) el('sh_upsell_selected_block').style.display = 'none';
    if (el('sh_upsell_confirm_btn'))  el('sh_upsell_confirm_btn').innerHTML    = shIcon('check',16) + ' Оплатить всё';
    if (el('sh_upsell_source_badge')) {
      el('sh_upsell_source_badge').innerHTML = rule.fromWarehouse
        ? '<span class="sh-badge sh-badge-blue">' + shIcon('box',11,'var(--sh-accent)') + ' Из склада</span>'
        : '<span class="sh-badge sh-badge-amber">' + shIcon('flash',11,'var(--sh-amber)') + ' Рекомендации</span>';
    }

    var listEl = el('sh_upsell_list');
    if (listEl) {
      var html = '';
      for (var i = 0; i < rule.services.length; i++) {
        var svc = rule.services[i];
        var dj  = JSON.stringify(svc).replace(/"/g, '&quot;');
        html += '<button class="sh-upsell-svc" id="sh_usvc_' + i + '" onclick="CRM.modules.shift._upsellToggle(' + i + ',' + dj + ')">'
          + '<div class="svc-icon" style="background:' + svc.color + '18;border:1.5px solid ' + svc.color + '40;">' + shIcon(svc.icon || 'box', 20, svc.color) + '</div>'
          + '<div class="svc-label">' + svc.label + '</div>'
          + '<div class="svc-price">+' + formatMoney(svc.price) + '</div>'
          + '<div class="svc-check">' + shIcon('check',12,'#fff') + '</div>'
          + '</button>';
      }
      listEl.innerHTML = html;
    }

    this._upsellTimeLeft = 9;
    var bar  = el('sh_upsell_timer_bar');
    if (bar) bar.style.width = '100%';
    if (this._upsellTimerIv) clearInterval(this._upsellTimerIv);
    var self = this;
    this._upsellTimerIv = setInterval(function() {
      self._upsellTimeLeft -= 0.1;
      var pct = Math.max(0, (self._upsellTimeLeft / 9) * 100);
      if (bar) bar.style.width = pct.toFixed(1) + '%';
      if (self._upsellTimeLeft <= 0) self._upsellSkip();
    }, 100);

    var ov = el('sh_upsell_overlay');
    if (ov) ov.style.display = 'flex';
  },

  _upsellToggle: function(idx, svc) {
    var btn   = document.getElementById('sh_usvc_' + idx);
    var exist = -1;
    for (var i = 0; i < this._upsellSelected.length; i++) {
      if (this._upsellSelected[i].label === svc.label) { exist = i; break; }
    }
    if (exist >= 0) {
      this._upsellSelected.splice(exist, 1);
      if (btn) btn.classList.remove('selected');
    } else {
      this._upsellSelected.push(svc);
      if (btn) btn.classList.add('selected');
      if (this._upsellTimerIv) {
        clearInterval(this._upsellTimerIv);
        this._upsellTimerIv = null;
        var bar = document.getElementById('sh_upsell_timer_bar');
        if (bar) bar.style.width = '100%';
      }
    }
    this._upsellRenderSelected();
  },

  _upsellRenderSelected: function() {
    var blk = document.getElementById('sh_upsell_selected_block');
    var lst = document.getElementById('sh_upsell_selected_list');
    var cb  = document.getElementById('sh_upsell_confirm_btn');
    if (!blk || !lst) return;
    if (!this._upsellSelected.length) { blk.style.display = 'none'; return; }
    blk.style.display = 'block';
    var total = 0;
    for (var i = 0; i < this._upsellSelected.length; i++) total += this._upsellSelected[i].price;
    var html = '';
    for (var j = 0; j < this._upsellSelected.length; j++) {
      var s = this._upsellSelected[j];
      html += '<div style="display:flex;justify-content:space-between;font-size:.76rem;padding:3px 0;">'
        + '<span>' + shIcon(s.icon || 'box', 12, s.color) + ' ' + s.label + '</span>'
        + '<span style="font-weight:700;color:var(--sh-green);">+' + formatMoney(s.price) + '</span>'
        + '</div>';
    }
    html += '<div style="display:flex;justify-content:space-between;font-size:.8rem;font-weight:900;border-top:1px solid rgba(76,175,130,.2);margin-top:5px;padding-top:5px;">'
      + '<span>Доп. итого</span><span style="color:var(--sh-green);">+' + formatMoney(total) + '</span></div>';
    lst.innerHTML = html;
    if (cb) cb.innerHTML = shIcon('check',16) + ' Оплатить всё — ' + formatMoney(this._upsellMainAmt + total);
  },

  _upsellClose: function() {
    if (this._upsellTimerIv) { clearInterval(this._upsellTimerIv); this._upsellTimerIv = null; }
    var ov = document.getElementById('sh_upsell_overlay');
    if (ov) ov.style.display = 'none';
  },

  _upsellSkip: function() {
    var cb = this._upsellPending;
    this._upsellPending = null;
    this._upsellClose();
    if (cb) cb([]);
  },

  _upsellConfirm: function() {
    var sel = this._upsellSelected.slice();
    var cb  = this._upsellPending;
    this._upsellPending = null;
    this._upsellClose();
    if (cb) cb(sel);
  },

  /* ================================================================
     ГОРЯЧИЕ КЛАВИШИ
  ================================================================ */
  _setupHotkeys: function() {
    var self = this;
    if (this._hotkeyHandler) document.removeEventListener('keydown', this._hotkeyHandler);
    this._hotkeyHandler = function(e) {
      if (!self._current) return;
      var tag     = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
      var isInput = (tag === 'input' || tag === 'textarea' || tag === 'select');
      if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); self.submitOp(); return; }
      if (e.key === 'Escape' && !isInput) { self._clearForm(); return; }
      if (e.ctrlKey && e.key === 'g') {
        e.preventDefault();
        var el = document.getElementById('sh_op_amount');
        if (el) el.focus();
        return;
      }
      if (!isInput) {
        if (e.key === '+' || e.key === '=') { self._setType('income');  return; }
        if (e.key === '-')                  { self._setType('expense'); return; }
        var n = parseInt(e.key);
        if (n >= 1 && n <= 9) {
          var btn = self._topButtons[n - 1] || self._buttons[n - 1];
          if (btn) { self._fillFromBtn(btn); return; }
        }
      }
    };
    document.addEventListener('keydown', this._hotkeyHandler);
  },

  _openHotkeysModal:  function() { var e = document.getElementById('sh_hotkeys_overlay'); if (e) e.style.display = 'flex'; },
  _closeHotkeysModal: function() { var e = document.getElementById('sh_hotkeys_overlay'); if (e) e.style.display = 'none'; },

  /* ================================================================
     ЧЕРНОВИК
  ================================================================ */
  _saveDraft: function() {
    if (!this._current) return;
    try {
      var d = {
        amount: document.getElementById('sh_op_amount') ? document.getElementById('sh_op_amount').value : '',
        qty:    document.getElementById('sh_op_qty')    ? document.getElementById('sh_op_qty').value    : '1',
        desc:   document.getElementById('sh_op_desc')   ? document.getElementById('sh_op_desc').value   : '',
        method: this._opMethod,
        type:   this._opType,
      };
      localStorage.setItem('shift_draft_' + this._current.id, JSON.stringify(d));
    } catch(e) {}
  },

  _restoreDraft: function() {
    if (!this._current) return;
    try {
      var raw = localStorage.getItem('shift_draft_' + this._current.id);
      if (!raw) return;
      var d = JSON.parse(raw);
      if (!d.amount) return;
      var aEl = document.getElementById('sh_op_amount');
      var qEl = document.getElementById('sh_op_qty');
      var dEl = document.getElementById('sh_op_desc');
      if (aEl) aEl.value = d.amount;
      if (qEl) qEl.value = d.qty;
      if (dEl) dEl.value = d.desc;
      this._setType(d.type   || 'income');
      this._setMethod(d.method || 'Наличные');
      this._calcTotal();
      notify('Черновик восстановлен', 'info');
    } catch(e) {}
  },

  _clearDraft: function() {
    if (!this._current) return;
    try { localStorage.removeItem('shift_draft_' + this._current.id); } catch(e) {}
  },

  /* ================================================================
     ТАБЫ
  ================================================================ */
  _tab: function(name, silent) {
    this._activeTab = name;
    var tabs = ['cashier', 'reports', 'settings'];
    for (var i = 0; i < tabs.length; i++) {
      var t   = tabs[i];
      var el  = document.getElementById('sh_tab_' + t);
      var btn = document.getElementById('sh_tab_btn_' + t);
      if (el)  el.style.display = (t === name) ? 'block' : 'none';
      if (btn) btn.classList.toggle('active', t === name);
    }
    if (!silent) {
      if (name === 'settings') this._renderBtnsSettings();
      if (name === 'reports')  this._loadReports();
      if (name === 'cashier')  this._loadHistory();
    }
  },

  _toggleHistory: function() {
    this._histOpen = !this._histOpen;
    var hdr  = document.getElementById('sh_hist_hdr');
    var body = document.getElementById('sh_hist_body_wrap');
    var chev = document.getElementById('sh_hist_chevron');
    if (hdr)  hdr.classList.toggle('open',  this._histOpen);
    if (body) body.classList.toggle('open', this._histOpen);
    if (chev) chev.classList.toggle('open', this._histOpen);
  },

  /* ================================================================
     МЕНЕДЖЕР
  ================================================================ */
  _fillManagerSelect: function() {
    var sel = document.getElementById('sh_manager_sel');
    if (!sel) return;
    var self = this;
    if (!this._managers.length) {
      sel.style.display = 'none';
      var no = document.getElementById('sh_no_emp');
      if (no) no.style.display = 'block';
      return;
    }
    var html = '<option value="">— Выберите менеджера —</option>';
    for (var i = 0; i < this._managers.length; i++) {
      var m = this._managers[i];
      html += '<option value="' + m.id + '" data-name="' + m.name + '" data-bonus="' + m.bonusPct + '">'
        + m.name + (m.position ? ' (' + m.position + ')' : '') + '</option>';
    }
    sel.innerHTML = html;
    sel.onchange  = function() { self._onManagerChange(); };
  },

  _onManagerChange: function() {
    var sel = document.getElementById('sh_manager_sel');
    if (!sel || !sel.value) return;
    var opt      = sel.options[sel.selectedIndex];
    var bonusPct = parseFloat(opt.getAttribute('data-bonus') || '0.5');
    var hint = document.getElementById('sh_bonus_hint');
    var txt  = document.getElementById('sh_bonus_hint_text');
    if (hint) hint.style.display = 'block';
    if (txt)  txt.textContent    = opt.getAttribute('data-name') + ' — ' + bonusPct + '% с каждого прихода';
    CRM.api('shift', 'lastEndCash', { empId: sel.value }).then(function(res) {
      if (res && res.ok && res.endCash > 0) {
        var sc = document.getElementById('sh_start_cash');
        var h  = document.getElementById('sh_start_cash_hint');
        if (sc && !sc.value) sc.value = res.endCash;
        if (h) h.textContent = '↑ Остаток прошлой смены ' + (res.date ? new Date(res.date).toLocaleDateString('ru') : '');
      }
    });
  },

  /* ================================================================
     БЛОКИ ОТКРЫТИЕ/АКТИВНАЯ
  ================================================================ */
  _showOpen: function() {
    var ob = document.getElementById('sh_open_block');
    var ab = document.getElementById('sh_active_block');
    if (ob) ob.style.display = 'block';
    if (ab) ab.style.display = 'none';
    if (this._durationInterval) { clearInterval(this._durationInterval); this._durationInterval = null; }
  },

  _showActive: function() {
    var ob = document.getElementById('sh_open_block');
    var ab = document.getElementById('sh_active_block');
    if (ob) ob.style.display = 'none';
    if (ab) ab.style.display = 'block';
    this._updateStats();
    this._renderOps();
    this._updateSalaryPreview();
    this._setMethod('Наличные');
    this._longShiftWarned = false;
  },

  /* ================================================================
     БЫСТРЫЕ КНОПКИ
  ================================================================ */
  _renderQuickBtns: function() {
    var self = this;

    /* Топ из прайса */
    var topEl = document.getElementById('sh_quick_btns_top');
    if (topEl) {
      if (!this._topButtons.length) {
        topEl.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:10px;color:var(--sh-muted);font-size:.72rem;border:2px dashed var(--sh-border);border-radius:10px;">Прайс-лист пуст</div>';
      } else {
        var html = '';
        for (var ti = 0; ti < this._topButtons.length; ti++) {
          var btn   = this._topButtons[ti];
          var usage = self._btnUsage[btn.id] || 0;
          var dj    = JSON.stringify(btn).replace(/"/g, '&quot;');
          var unitStr = btn.unit ? '/' + btn.unit : '';
          html += '<button class="sh-quick-btn" style="border-color:' + btn.color + '55;" onclick="CRM.modules.shift._fillFromBtn(' + dj + ')" title="' + btn.label + ' · ' + formatMoney(btn.amount) + unitStr + '">'
            + '<span style="position:absolute;top:3px;left:5px;font-size:.48rem;font-weight:900;color:' + btn.color + ';">' + (ti < 9 ? ti + 1 : '') + '</span>'
            + shIcon(btn.icon || 'default', 20, btn.color)
            + '<span style="font-size:.58rem;font-weight:700;text-align:center;max-width:68px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;line-height:1.2;">' + btn.label + '</span>'
            + '<span style="font-size:.7rem;font-weight:900;color:var(--sh-green);">+' + formatMoney(btn.amount) + '</span>'
            + (usage > 0 ? '<span style="font-size:.5rem;color:' + btn.color + ';opacity:.7;">x' + usage + '</span>' : '')
            + '</button>';
        }
        topEl.innerHTML = html;
      }
    }

    /* Пользовательские */
    var el = document.getElementById('sh_quick_btns');
    if (!el) return;
    if (!this._buttons.length) {
      el.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:10px;color:var(--sh-muted);font-size:.72rem;border:2px dashed var(--sh-border);border-radius:10px;">Нет кнопок — добавьте во вкладке «Кнопки»</div>';
    } else {
      var html2 = '';
      for (var bi = 0; bi < this._buttons.length; bi++) {
        var b   = this._buttons[bi];
        var u   = self._btnUsage[b.id] || 0;
        var dj2 = JSON.stringify(b).replace(/"/g, '&quot;');
        var clr = b.type === 'income' ? 'var(--sh-green)' : 'var(--sh-red)';
        var pfx = b.type === 'income' ? '+' : '-';
        html2 += '<button class="sh-quick-btn" style="border-color:' + b.color + '55;" onclick="CRM.modules.shift._fillFromBtn(' + dj2 + ')" title="' + b.label + '">'
          + shIcon(b.icon || 'default', 20, b.color)
          + '<span style="font-size:.58rem;font-weight:700;text-align:center;max-width:68px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + b.label + '</span>'
          + '<span style="font-size:.7rem;font-weight:900;color:' + clr + ';">' + pfx + formatMoney(b.amount) + '</span>'
          + (u > 0 ? '<span style="font-size:.5rem;color:' + b.color + ';opacity:.7;">x' + u + '</span>' : '')
          + '</button>';
      }
      el.innerHTML = html2;
    }
  },

  _fillFromBtn: function(btn) {
    this._setType(btn.type || 'income');
    var amtEl  = document.getElementById('sh_op_amount');
    var descEl = document.getElementById('sh_op_desc');
    var qtyEl  = document.getElementById('sh_op_qty');
    if (amtEl)  amtEl.value  = btn.amount;
    if (descEl) descEl.value = btn.label;
    if (qtyEl)  qtyEl.value  = 1;
    this._calcTotal();
    if (qtyEl) { qtyEl.select(); qtyEl.focus(); }
    var today = new Date().toDateString();
    if (localStorage.getItem('shift_btn_usage_date') !== today) {
      this._btnUsage = {};
      localStorage.setItem('shift_btn_usage_date', today);
    }
    this._btnUsage[btn.id] = (this._btnUsage[btn.id] || 0) + 1;
    localStorage.setItem('shift_btn_usage', JSON.stringify(this._btnUsage));
    this._renderQuickBtns();
  },

  /* ================================================================
     ФОРМА
  ================================================================ */
  _setType: function(type) {
    this._opType = type;
    var ib = document.getElementById('sh_btn_income');
    var eb = document.getElementById('sh_btn_expense');
    var sb = document.getElementById('sh_submit_btn');
    var cb = document.getElementById('sh_change_block');
    if (ib) ib.className = 'sh-type-btn income'  + (type === 'income'  ? ' active' : '');
    if (eb) eb.className = 'sh-type-btn expense' + (type === 'expense' ? ' active' : '');
    if (sb) {
      if (type === 'income') {
        sb.className  = 'sh-btn sh-btn-success';
        sb.style.cssText = 'padding:11px;font-size:.88rem;justify-content:center;';
        sb.innerHTML  = shIcon('income',16) + ' Провести приход';
      } else {
        sb.className  = 'sh-btn sh-btn-danger';
        sb.style.cssText = 'padding:11px;font-size:.88rem;justify-content:center;';
        sb.innerHTML  = shIcon('expense',16) + ' Провести расход';
      }
    }
    var method = document.getElementById('sh_op_method') ? document.getElementById('sh_op_method').value : 'Наличные';
    if (cb) cb.style.display = (type === 'income' && method === 'Наличные') ? 'block' : 'none';
  },

  _setMethod: function(method) {
    this._opMethod = method;
    var hid = document.getElementById('sh_op_method');
    if (hid) hid.value = method;
    var map = { 'Наличные':'sh_m_cash','Карта':'sh_m_card','QR / СБП':'sh_m_qr','Перевод':'sh_m_trans' };
    for (var m in map) {
      var b = document.getElementById(map[m]);
      if (b) b.classList.toggle('active', m === method);
    }
    var cb = document.getElementById('sh_change_block');
    if (cb) cb.style.display = (method === 'Наличные' && this._opType === 'income') ? 'block' : 'none';
  },

  _changeQty: function(delta) {
    var el = document.getElementById('sh_op_qty');
    if (!el) return;
    el.value = Math.max(1, (parseInt(el.value) || 1) + delta);
    this._calcTotal();
  },

  _calcTotal: function() {
    var amt = parseFloat(document.getElementById('sh_op_amount') ? document.getElementById('sh_op_amount').value : 0) || 0;
    var qty = Math.max(1, parseInt(document.getElementById('sh_op_qty') ? document.getElementById('sh_op_qty').value : 1) || 1);
    var el  = document.getElementById('sh_op_total_preview');
    if (!el) return;
    var color = this._opType === 'income' ? 'var(--sh-green)' : 'var(--sh-red)';
    if (qty > 1 && amt > 0) {
      el.style.color = color;
      el.textContent = qty + ' × ' + formatMoney(amt) + ' = ' + formatMoney(amt * qty);
    } else if (amt > 0) {
      el.style.color = color;
      el.textContent = 'Итого: ' + formatMoney(amt);
    } else {
      el.textContent = '';
    }
    this._calcChange();
    this._saveDraft();
  },

  _calcChange: function() {
    var amt   = parseFloat(document.getElementById('sh_op_amount') ? document.getElementById('sh_op_amount').value : 0) || 0;
    var qty   = Math.max(1, parseInt(document.getElementById('sh_op_qty') ? document.getElementById('sh_op_qty').value : 1) || 1);
    var total = amt * qty;
    var given = parseFloat(document.getElementById('sh_given_cash') ? document.getElementById('sh_given_cash').value : 0) || 0;
    var resEl = document.getElementById('sh_change_result');
    if (!resEl) return;
    if (given > 0 && total > 0) {
      var change = given - total;
      resEl.textContent = (change >= 0 ? '' : '-') + formatMoney(Math.abs(change));
      resEl.style.color = change >= 0 ? 'var(--sh-green)' : 'var(--sh-red)';
    } else {
      resEl.textContent = '— ₽';
      resEl.style.color = 'var(--sh-green)';
    }
  },

  _clearForm: function() {
    var ids = ['sh_op_amount', 'sh_op_desc'];
    for (var i = 0; i < ids.length; i++) {
      var e = document.getElementById(ids[i]);
      if (e) e.value = '';
    }
    var q  = document.getElementById('sh_op_qty');        if (q)  q.value = 1;
    var p  = document.getElementById('sh_op_total_preview'); if (p)  p.textContent = '';
    var g  = document.getElementById('sh_given_cash');    if (g)  g.value = '';
    var r  = document.getElementById('sh_change_result'); if (r)  { r.textContent = '— ₽'; r.style.color = 'var(--sh-green)'; }
    var dd = document.getElementById('sh_price_dropdown'); if (dd) dd.style.display = 'none';
    this._cancelEdit();
    this._clearDraft();
  },

  _onEndCashInput: function(val) {
    var s = this._current;
    if (!s) return;
    var fact = parseFloat(val) || 0;
    var diff = fact - (s.cash || 0);
    var ind  = document.getElementById('sh_diff_indicator');
    if (!ind) return;
    if (!val) { ind.style.display = 'none'; return; }
    ind.style.display = 'block';
    if (Math.abs(diff) < 0.01) {
      ind.className = 'sh-diff-ok';
      ind.innerHTML = shIcon('check',13,'var(--sh-green)') + ' Совпадает';
    } else if (diff < 0) {
      ind.className = 'sh-diff-neg';
      ind.innerHTML = shIcon('warn',13,'var(--sh-red)') + ' Недостача: ' + Math.abs(diff).toFixed(2) + ' ₽';
    } else {
      ind.className = 'sh-diff-pos';
      ind.innerHTML = shIcon('warn',13,'var(--sh-amber)') + ' Излишек: ' + diff.toFixed(2) + ' ₽';
    }
  },

  /* ================================================================
     ОТКРЫТЬ СМЕНУ
  ================================================================ */
  openShift: async function() {
    if (this._openLock) return;
    var sel    = document.getElementById('sh_manager_sel');
    var manual = document.getElementById('sh_manager_manual');
    var empId = '', manager = '';
    if (sel && sel.value) {
      var opt = sel.options[sel.selectedIndex];
      empId   = sel.value;
      manager = opt.getAttribute('data-name') || opt.text;
    } else if (manual && manual.value.trim()) {
      manager = manual.value.trim();
    }
    if (!manager) { notify('Выберите или введите менеджера', 'error'); return; }
    var startCash = parseFloat(document.getElementById('sh_start_cash') ? document.getElementById('sh_start_cash').value : 0) || 0;
    var iKey = 'open_' + empId + '_' + new Date().toDateString();
    this._openLock = true;
    var btn = document.getElementById('sh_open_btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Открываем...'; }
    try {
      var res = await CRM.api('shift', 'open', { empId:empId, manager:manager, startCash:startCash, iKey:iKey });
      if (res && res.ok) {
        this._current = res.data;
        notify('Смена открыта! Удачной работы, ' + manager, 'success');
        this._showActive();
        var self = this;
        if (this._durationInterval) clearInterval(this._durationInterval);
        this._durationInterval = setInterval(function() { self._tick(); }, 1000);
        this._setupHotkeys();
      } else {
        notify((res && res.error) || 'Ошибка открытия смены', 'error');
      }
    } finally {
      this._openLock = false;
      if (btn) { btn.disabled = false; btn.innerHTML = shIcon('unlock',17) + ' Открыть смену'; }
    }
  },

  /* ================================================================
     ЗАКРЫТЬ СМЕНУ
  ================================================================ */
  closeShift: async function() {
    if (!this._current || this._closeLock) return;
    var endCash    = parseFloat(document.getElementById('sh_end_cash')    ? document.getElementById('sh_end_cash').value    : '');
    var baseSalary = parseFloat(document.getElementById('sh_base_salary') ? document.getElementById('sh_base_salary').value : 0) || 0;
    var note       = document.getElementById('sh_close_note')  ? document.getElementById('sh_close_note').value.trim()  : '';
    var salaryPaid = document.getElementById('sh_salary_paid_cb') ? document.getElementById('sh_salary_paid_cb').checked : false;
    if (isNaN(endCash)) { notify('Введите фактическую сумму в кассе', 'error'); return; }
    var s     = this._current;
    var bonus = s.accruedBonus || 0;
    var total = baseSalary + bonus;
    if (total > 0 && !salaryPaid) {
      notify('Подтвердите выплату ЗП (' + formatMoney(total) + ') — отметьте чекбокс', 'error');
      var cb = document.getElementById('sh_salary_paid_cb');
      if (cb) cb.scrollIntoView({ behavior:'smooth', block:'center' });
      return;
    }
    var diff = endCash - s.cash;
    var msg  = 'Закрыть смену?\n\n'
      + 'Менеджер: '      + s.manager               + '\n'
      + 'Операций: '      + s.operations.length      + '\n'
      + 'Доход: '         + formatMoney(s.totalIncome  || 0) + '\n'
      + 'Расход: '        + formatMoney(s.totalExpense || 0) + '\n'
      + 'Касса расчёт: '  + formatMoney(s.cash)       + '\n'
      + 'Касса факт: '    + formatMoney(endCash)       + '\n'
      + (Math.abs(diff) >= 0.01
          ? '\n' + (diff < 0 ? 'Недостача' : 'Излишек') + ': ' + Math.abs(diff).toFixed(2) + ' ₽\n'
          : '\nРасхождений нет\n')
      + '\nЗП: ' + formatMoney(baseSalary) + ' + бонус ' + formatMoney(bonus) + ' = ' + formatMoney(total);
    if (!confirm(msg)) return;
    this._closeLock = true;
    var btn = document.getElementById('sh_close_btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Закрываем...'; }
    try {
      var res = await CRM.api('shift', 'close', { endCash:endCash, baseSalary:baseSalary, note:note });
      if (res && res.ok) {
        if (this._durationInterval) { clearInterval(this._durationInterval); this._durationInterval = null; }
        this._clearDraft();
        this._btnUsage = {};
        localStorage.removeItem('shift_btn_usage');
        this._current = null;
        this._longShiftWarned = false;
        if (typeof forceRefresh === 'function') await forceRefresh();
        if (res.report) this._printZ(res.report);
        notify('Смена закрыта. Z-отчёт сформирован!', 'success');
        this._showOpen();
        this._fillManagerSelect();
        await this._loadHistory();
        await this._loadReports();
      } else {
        notify((res && res.error) || 'Ошибка закрытия', 'error');
      }
    } finally {
      this._closeLock = false;
      if (btn) { btn.disabled = false; btn.innerHTML = shIcon('lock',16) + ' Закрыть смену и сформировать Z-отчёт'; }
    }
  },

  /* ================================================================
     ПРОВЕСТИ ОПЕРАЦИЮ
  ================================================================ */
  submitOp: async function() {
    if (this._submitLock) return;
    var amount = parseFloat(document.getElementById('sh_op_amount') ? document.getElementById('sh_op_amount').value : 0);
    var qty    = Math.max(1, parseInt(document.getElementById('sh_op_qty') ? document.getElementById('sh_op_qty').value : 1) || 1);
    var desc   = document.getElementById('sh_op_desc')    ? document.getElementById('sh_op_desc').value.trim() : '';
    var method = document.getElementById('sh_op_method')  ? document.getElementById('sh_op_method').value      : 'Наличные';
    if (!amount || amount <= 0) { notify('Введите сумму больше нуля', 'error'); return; }
    var dd = document.getElementById('sh_price_dropdown');
    if (dd) dd.style.display = 'none';
    if (this._editingOpId) { await this._saveEdit(amount * qty, desc, method, qty, amount); return; }
    var self = this;
    if (method === 'QR / СБП' && this._opType === 'income') {
      this._upsellShow(desc, amount * qty, async function(extras) {
        await self._openQRModal(amount * qty, desc, amount, qty, extras);
      });
      return;
    }
    this._upsellShow(desc, amount * qty, async function(extras) {
      await self._doSubmitOp(amount, qty, desc, method, extras);
    });
  },

  _doSubmitOp: async function(amount, qty, desc, method, extras) {
    extras = extras || [];
    this._submitLock = true;
    var btn  = document.getElementById('sh_submit_btn');
    var orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    var iKey = 'op_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
    try {
      var res = await CRM.api('shift', 'operation', { type:this._opType, amount:amount, qty:qty, desc:desc, method:method, iKey:iKey });
      if (res && res._dup) { notify('Дубль — уже проведено', 'warning'); return; }
      if (res && res.ok) {
        this._current = res.data;
        for (var i = 0; i < extras.length; i++) {
          var svc  = extras[i];
          var eKey = 'op_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
          var eRes = await CRM.api('shift', 'operation', { type:'income', amount:svc.price, qty:1, desc:svc.desc || svc.label, method:method, iKey:eKey });
          if (eRes && eRes.ok) this._current = eRes.data;
        }
        this._clearForm();
        this._updateStats();
        this._renderOps();
        this._updateSalaryPreview();
        var sum = 0;
        for (var j = 0; j < extras.length; j++) sum += extras[j].price;
        var extraTxt = extras.length ? ' + ' + extras.length + ' доп. (+' + formatMoney(sum) + ')' : '';
        notify((this._opType === 'income' ? '▲' : '▼') + ' ' + formatMoney(amount * qty) + extraTxt + ' ✓',
          this._opType === 'income' ? 'success' : 'info');
      } else {
        notify((res && res.error) || 'Ошибка', 'error');
      }
    } finally {
      this._submitLock = false;
      if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
  },

  /* ================================================================
     РЕДАКТИРОВАНИЕ / УДАЛЕНИЕ
  ================================================================ */
  _startEdit: function(op) {
    this._editingOpId = op.id;
    var aEl = document.getElementById('sh_op_amount');
    var qEl = document.getElementById('sh_op_qty');
    var dEl = document.getElementById('sh_op_desc');
    if (aEl) aEl.value = op.price  || op.amount;
    if (qEl) qEl.value = op.qty    || 1;
    if (dEl) dEl.value = op.desc   || '';
    this._setType(op.type);
    this._setMethod(op.method || 'Наличные');
    this._calcTotal();
    var cr = document.getElementById('sh_edit_cancel_row');
    if (cr) cr.style.display = 'block';
    var ft = document.getElementById('sh_form_title');
    if (ft) ft.innerHTML = shIcon('edit',16,'var(--sh-amber)') + ' Редактирование';
    var el = document.getElementById('sh_op_amount');
    if (el) { el.scrollIntoView({ behavior:'smooth', block:'center' }); el.focus(); }
  },

  _cancelEdit: function() {
    this._editingOpId = null;
    var cr = document.getElementById('sh_edit_cancel_row');
    if (cr) cr.style.display = 'none';
    var ft = document.getElementById('sh_form_title');
    if (ft) ft.innerHTML = shIcon('edit',16,'var(--sh-muted)') + ' Новая операция';
    this._setType('income');
  },

  _saveEdit: async function(newAmount, newDesc, newMethod, newQty, newPrice) {
    if (this._submitLock) return;
    this._submitLock = true;
    var btn  = document.getElementById('sh_submit_btn');
    var orig = btn ? btn.innerHTML : '';
    if (btn) btn.disabled = true;
    try {
      var res = await CRM.api('shift', 'editOperation', { opId:this._editingOpId, amount:newAmount, desc:newDesc, method:newMethod, qty:newQty || 1, price:newPrice || newAmount });
      if (res && res.ok) {
        this._current = res.data;
        this._cancelEdit();
        this._clearForm();
        this._updateStats();
        this._renderOps();
        this._updateSalaryPreview();
        notify('Операция обновлена', 'success');
      } else {
        notify((res && res.error) || 'Ошибка', 'error');
      }
    } finally {
      this._submitLock = false;
      if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
  },

  _deleteOp: async function(opId) {
    if (!confirm('Удалить операцию?\nБаланс будет скорректирован.')) return;
    var res = await CRM.api('shift', 'deleteOperation', { opId:opId });
    if (res && res.ok) {
      this._current = res.data;
      this._updateStats();
      this._renderOps();
      this._updateSalaryPreview();
      notify('Операция удалена', 'info');
    } else {
      notify((res && res.error) || 'Ошибка', 'error');
    }
  },

  /* ================================================================
     ОБНОВЛЕНИЕ UI
  ================================================================ */
  _updateStats: function() {
    var s = this._current;
    if (!s) return;
    var set = function(id, v) { var e = document.getElementById(id); if (e) e.textContent = v; };
    set('sh_hdr_manager',  s.manager);
    set('sh_hdr_time',     'Открыта: ' + new Date(s.openTime).toLocaleString('ru'));
    set('sh_stat_cash',    formatMoney(s.cash));
    set('sh_stat_income',  formatMoney(s.totalIncome  || 0));
    set('sh_stat_expense', formatMoney(s.totalExpense || 0));
    var hint = document.getElementById('sh_expected_hint');
    if (hint) hint.textContent = 'Ожидается: ' + formatMoney(s.cash);
    var endEl = document.getElementById('sh_end_cash');
    if (endEl && !endEl.value) endEl.value = Math.round(s.cash);
    this._updateEarnedBlock();
    this._updateSalaryPreview();
    try { localStorage.setItem('shift_backup_' + s.id, JSON.stringify(s)); } catch(e) {}
  },

  _tick: function() {
    if (!this._current) return;
    var el = document.getElementById('sh_stat_dur');
    if (!el) return;
    var ms = Date.now() - new Date(this._current.openTime);
    var h  = Math.floor(ms / 3600000);
    var m  = Math.floor((ms % 3600000) / 60000);
    el.textContent = h + 'ч ' + String(m).padStart(2, '0') + 'м';
    if (ms > 9 * 3600000 && !this._longShiftWarned) {
      this._longShiftWarned = true;
      notify('Смена идёт уже более 9 часов!', 'warning');
    }
  },

  _updateEarnedBlock: function() {
    var s = this._current;
    if (!s) return;
    var pct    = s.bonusPct     || 0.5;
    var income = s.totalIncome  || 0;
    var bonus  = s.accruedBonus || 0;
    var base   = parseFloat(document.getElementById('sh_base_salary') ? document.getElementById('sh_base_salary').value : 0) || 0;
    var total  = base + bonus;
    var set = function(id, v) { var e = document.getElementById(id); if (e) e.textContent = v; };
    set('sh_earn_pct',        pct);
    set('sh_earn_income',     formatMoney(income));
    set('sh_earn_bonus_lbl',  '+' + formatMoney(bonus) + ' бонус');
    set('sh_earn_total',      formatMoney(total));
    set('sh_earn_base_preview', base > 0 ? formatMoney(base) : 'не указан');
    var bar = document.getElementById('sh_earn_bar');
    if (bar) bar.style.width = Math.min(100, (total / 5000) * 100).toFixed(1) + '%';
  },

  _updateSalaryPreview: function() {
    var s = this._current;
    if (!s) return;
    var base  = parseFloat(document.getElementById('sh_base_salary') ? document.getElementById('sh_base_salary').value : 0) || 0;
    var bonus = s.accruedBonus || 0;
    var total = base + bonus;
    var pct   = s.bonusPct || 0.5;
    var lbl = document.getElementById('sh_close_bonus_lbl');
    if (lbl) lbl.textContent = '+ бонус ' + pct + '% = +' + formatMoney(bonus);
    var rows = document.getElementById('sh_salary_rows');
    if (rows) {
      rows.innerHTML = ''
        + '<div class="sh-salary-row"><span style="color:var(--sh-muted);">Оклад за день</span><span style="font-weight:700;">'  + formatMoney(base)  + '</span></div>'
        + '<div class="sh-salary-row"><span style="color:var(--sh-muted);">Бонус ' + pct + '% с дохода ' + formatMoney(s.totalIncome || 0) + '</span><span style="font-weight:700;color:var(--sh-purple);">+' + formatMoney(bonus) + '</span></div>'
        + '<div class="sh-salary-row"><span>ИТОГО к выплате</span><span style="color:var(--sh-green);font-size:1rem;">' + formatMoney(total) + '</span></div>';
    }
    var ct = document.getElementById('sh_salary_confirm_text');
    if (ct) ct.innerHTML = 'Итого к выплате: <b style="color:var(--sh-green);">' + formatMoney(total) + '</b>';
    var zn = document.getElementById('sh_salary_zero_note');
    if (zn) zn.style.display = (total === 0) ? 'block' : 'none';
    var cb = document.getElementById('sh_salary_paid_cb');
    if (cb && total === 0) cb.checked = true;
    this._updateEarnedBlock();
  },

  /* ================================================================
     РЕНДЕР ОПЕРАЦИЙ
  ================================================================ */
  _renderOps: function() {
    var allOps = (this._current && this._current.operations)
      ? this._current.operations.slice().reverse()
      : [];
    var query = (this._opsFilter || '').toLowerCase().trim();
    var ops   = [];
    if (query) {
      for (var i = 0; i < allOps.length; i++) {
        var op = allOps[i];
        if ((op.desc || '').toLowerCase().indexOf(query) >= 0 || String(op.amount).indexOf(query) >= 0) {
          ops.push(op);
        }
      }
    } else {
      ops = allOps;
    }

    var el = document.getElementById('sh_ops_list');
    if (!el) return;

    var statsEl = document.getElementById('sh_ops_stats');
    if (statsEl && allOps.length) {
      var totalIn = 0, totalOut = 0;
      for (var si = 0; si < allOps.length; si++) {
        if (allOps[si].type === 'income')  totalIn  += allOps[si].amount;
        else                                totalOut += allOps[si].amount;
      }
      var ms      = this._current ? Date.now() - new Date(this._current.openTime) : 0;
      var perHour = ms > 360000 ? (allOps.length / (ms / 3600000)).toFixed(1) : allOps.length;
      statsEl.innerHTML = ''
        + '<div style="text-align:center;padding:6px;background:rgba(76,175,130,.07);border-radius:8px;border:1px solid rgba(76,175,130,.15);">'
        +   '<div style="font-weight:900;color:var(--sh-green);font-size:.85rem;">+' + formatMoney(totalIn)  + '</div>'
        +   '<div style="font-size:.55rem;color:var(--sh-muted);">ПРИХОД</div>'
        + '</div>'
        + '<div style="text-align:center;padding:6px;background:rgba(244,112,103,.07);border-radius:8px;border:1px solid rgba(244,112,103,.15);">'
        +   '<div style="font-weight:900;color:var(--sh-red);font-size:.85rem;">-' + formatMoney(totalOut) + '</div>'
        +   '<div style="font-size:.55rem;color:var(--sh-muted);">РАСХОД</div>'
        + '</div>'
        + '<div style="text-align:center;padding:6px;background:rgba(200,150,234,.07);border-radius:8px;border:1px solid rgba(200,150,234,.15);">'
        +   '<div style="font-weight:900;color:var(--sh-purple);font-size:.85rem;">' + perHour + '/ч</div>'
        +   '<div style="font-size:.55rem;color:var(--sh-muted);">ТЕМП</div>'
        + '</div>';
    } else if (statsEl) {
      statsEl.innerHTML = '';
    }

    var sumEl = document.getElementById('sh_ops_summary');
    if (sumEl) {
      sumEl.textContent = allOps.length
        ? allOps.length + ' оп.' + (query ? ' / ' + ops.length : '')
        : '';
    }

    if (!ops.length) {
      el.innerHTML = '<div style="text-align:center;padding:26px;color:var(--sh-muted);">'
        + '<div style="margin-bottom:8px;opacity:.25;">' + shIcon('report',30,'var(--sh-muted)') + '</div>'
        + '<div style="font-size:.8rem;">' + (query ? 'Ничего не найдено' : 'Операций пока нет') + '</div></div>';
      return;
    }

    var self = this;
    var html = '';
    for (var oi = 0; oi < ops.length; oi++) {
      html += self._renderOpRow(ops[oi]);
    }
    el.innerHTML = html;
  },

  _renderOpRow: function(op) {
    var isIncome = op.type === 'income';
    var isQr     = !!op.qrPaymentId;
    var dj       = JSON.stringify(op).replace(/"/g, '&quot;');
    var mColor   = isQr ? 'var(--sh-accent)' : (isIncome ? 'var(--sh-green)' : 'var(--sh-red)');
    var editing  = (this._editingOpId === op.id) ? ' editing' : '';
    return '<div class="sh-op-row' + editing + '">'
      + '<div class="sh-op-icon" style="background:' + (isIncome ? 'rgba(76,175,130,.12)' : 'rgba(244,112,103,.12)') + ';">'
      +   shIcon(isIncome ? 'income' : 'expense', 14, isIncome ? 'var(--sh-green)' : 'var(--sh-red)')
      + '</div>'
      + '<div style="flex:1;min-width:0;">'
      +   '<div style="font-weight:600;font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
      +     (op.desc || (isIncome ? 'Поступление' : 'Изъятие'))
      +     ((op.qty || 1) > 1 ? ' <span style="color:var(--sh-muted);font-size:.67rem;">×' + op.qty + '</span>' : '')
      +   '</div>'
      +   '<div style="font-size:.63rem;color:var(--sh-muted);margin-top:1px;">'
      +     new Date(op.time).toLocaleTimeString('ru', {hour:'2-digit', minute:'2-digit'})
      +     ' · <span style="color:' + mColor + ';">' + (op.method || 'Наличные') + (isQr ? ' ✓' : '') + '</span>'
      +   '</div>'
      + '</div>'
      + '<span style="font-weight:900;font-size:.86rem;flex-shrink:0;color:' + (isIncome ? 'var(--sh-green)' : 'var(--sh-red)') + ';">'
      +   (isIncome ? '+' : '-') + formatMoney(op.amount)
      + '</span>'
      + '<div style="display:flex;gap:3px;flex-shrink:0;">'
      +   '<button class="sh-btn sh-btn-ghost sh-btn-icon sm" onclick="CRM.modules.shift._startEdit(' + dj + ')" title="Редактировать">' + shIcon('edit',12,'var(--sh-muted)') + '</button>'
      +   '<button class="sh-btn sh-btn-icon sm" style="background:rgba(244,112,103,.1);border:1px solid rgba(244,112,103,.2);" onclick="CRM.modules.shift._deleteOp(\'' + op.id + '\')" title="Удалить">' + shIcon('trash',12,'var(--sh-red)') + '</button>'
      + '</div>'
      + '</div>';
  },

  /* ================================================================
     QR / СБП
  ================================================================ */
  _qrPayment:   null,
  _qrPollTimer: null,
  _qrTickTimer: null,
  _qrAmount:    0,
  _qrDesc:      '',
  _qrQty:       1,
  _qrUnitPrice: 0,
  _qrExtras:    [],

  _openQRModal: async function(totalAmount, desc, unitPrice, qty, extras) {
    extras = extras || [];
    this._qrAmount    = totalAmount;
    this._qrDesc      = desc;
    this._qrQty       = qty;
    this._qrUnitPrice = unitPrice;
    this._qrExtras    = extras;
    this._qrPayment   = null;
    this._qrStopPoll();

    var show = function(id) { var e = document.getElementById(id); if (e) e.style.display = 'block'; };
    var hide = function(id) { var e = document.getElementById(id); if (e) e.style.display = 'none'; };
    show('sh_qr_loader'); hide('sh_qr_img'); hide('sh_qr_paid_overlay');

    var al = document.getElementById('sh_qr_amount_lbl'); if (al) al.textContent = formatMoney(totalAmount);
    var dl = document.getElementById('sh_qr_desc_lbl');   if (dl) dl.textContent = desc || 'Оплата услуг';
    this._qrSetStatus('creating', 'Создаём...');

    var ids = ['sh_qr_check_btn', 'sh_qr_print_btn'];
    for (var i = 0; i < ids.length; i++) { var b = document.getElementById(ids[i]); if (b) b.disabled = true; }
    var cb = document.getElementById('sh_qr_autopoll_cb'); if (cb) cb.checked = false;
    var ov = document.getElementById('sh_qr_overlay');     if (ov) ov.style.display = 'flex';

    await this._qrCreate(totalAmount, desc);
  },

  _qrCreate: async function(amount, desc) {
    try {
      var res = await CRM.api('bank_account', 'create_payment', { amount:amount, description:desc || 'Оплата услуг', clientName:'', orderId:'', payMode:'sbp' });
      if (!res || !res.ok) {
        this._qrSetStatus('error', 'Ошибка: ' + ((res && res.error) || 'нет ответа'));
        var ld = document.getElementById('sh_qr_loader');
        if (ld) ld.innerHTML = '<div style="color:var(--sh-red);font-size:.74rem;padding:10px;text-align:center;">Ошибка платежа.<br><small>Проверьте настройки эквайринга</small></div>';
        return;
      }
      this._qrPayment = res.payment;
      var url = (res.payment && res.payment.paymentUrl) ? res.payment.paymentUrl : '';
      var ld2 = document.getElementById('sh_qr_loader'); if (ld2) ld2.style.display = 'none';
      if (url) {
        var img = document.getElementById('sh_qr_img');
        if (img) {
          img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data=' + encodeURIComponent(url);
          img.style.display = 'block';
        }
      }
      var ids = ['sh_qr_check_btn', 'sh_qr_print_btn'];
      for (var i = 0; i < ids.length; i++) { var b = document.getElementById(ids[i]); if (b) b.disabled = false; }
      this._qrSetStatus('pending', 'Ожидает оплаты');
      var ap = document.getElementById('sh_qr_autopoll_cb');
      if (ap) { ap.checked = true; this._qrTogglePoll(true); }
    } catch(e) {
      this._qrSetStatus('error', 'Ошибка: ' + e.message);
    }
  },

  _qrCheckOnce: async function() {
    if (!this._qrPayment) return;
    var btn = document.getElementById('sh_qr_check_btn');
    if (btn) btn.disabled = true;
    try {
      var res = await CRM.api('bank_account', 'payment_status', { paymentId:this._qrPayment.paymentId, extId:this._qrPayment.extId, payMode:'sbp' });
      if (res && res.ok && res.isPaid) {
        await this._qrOnPaid();
      } else {
        this._qrSetStatus('pending', 'Ожидает: ' + ((res && res.status) || ''));
        notify('Ещё не оплачено', 'info');
      }
    } finally {
      if (btn) btn.disabled = false;
    }
  },

  _qrTogglePoll: function(enabled) {
    this._qrStopPoll();
    var statusEl = document.getElementById('sh_qr_poll_status');
    if (!enabled || !this._qrPayment) { if (statusEl) statusEl.textContent = ''; return; }
    var self      = this;
    var countdown = 10;
    var upd = function() {
      if (statusEl) statusEl.textContent = 'Проверка через ' + countdown + ' сек.';
      countdown--;
      if (countdown < 0) countdown = 9;
    };
    upd();
    this._qrTickTimer = setInterval(upd, 1000);
    this._qrPollTimer = setInterval(async function() {
      countdown = 10;
      if (!self._qrPayment) { self._qrStopPoll(); return; }
      var res = await CRM.api('bank_account', 'payment_status', { paymentId:self._qrPayment.paymentId, extId:self._qrPayment.extId, payMode:'sbp' });
      if (res && res.ok && res.isPaid) await self._qrOnPaid();
    }, 10000);
  },

  _qrStopPoll: function() {
    if (this._qrPollTimer) { clearInterval(this._qrPollTimer); this._qrPollTimer = null; }
    if (this._qrTickTimer) { clearInterval(this._qrTickTimer); this._qrTickTimer = null; }
    var s = document.getElementById('sh_qr_poll_status');
    if (s) s.textContent = '';
  },

  _qrOnPaid: async function() {
    this._qrStopPoll();
    this._qrSetStatus('paid', 'Оплачено!');
    var ov = document.getElementById('sh_qr_paid_overlay');
    if (ov) ov.style.display = 'flex';
    notify('Оплата QR / СБП получена!', 'success');

    var paymentId = (this._qrPayment && (this._qrPayment.paymentId || this._qrPayment.extId)) || '';
    var iKey      = 'qr_' + (paymentId || Date.now());
    var res       = await CRM.api('shift', 'operation', {
      type: 'income', amount: this._qrUnitPrice, qty: this._qrQty,
      desc: this._qrDesc + ' [QR/СБП]', method: 'QR / СБП', iKey: iKey, qrPaymentId: paymentId,
    });
    if (res && res.ok && !res._dup) {
      this._current = res.data;
      for (var i = 0; i < this._qrExtras.length; i++) {
        var svc  = this._qrExtras[i];
        var eKey = 'op_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
        await CRM.api('shift', 'operation', { type:'income', amount:svc.price, qty:1, desc:svc.desc || svc.label, method:'QR / СБП', iKey:eKey });
      }
      this._clearForm();
      this._updateStats();
      this._renderOps();
      this._updateSalaryPreview();
    }
    var self = this;
    setTimeout(function() { self._closeQR(); }, 2500);
  },

  _closeQR: function() {
    this._qrStopPoll();
    var ov = document.getElementById('sh_qr_overlay');
    if (ov) ov.style.display = 'none';
    this._qrPayment = null;
  },

  _qrSetStatus: function(state, text) {
    var badge = document.getElementById('sh_qr_status');
    if (!badge) return;
    var cls = { pending:'sh-badge sh-badge-amber', paid:'sh-badge sh-badge-green', error:'sh-badge sh-badge-red', creating:'sh-badge sh-badge-blue' };
    badge.className = cls[state] || cls.pending;
    badge.textContent = text;
  },

  _qrPrint: function() {
    var img = document.getElementById('sh_qr_img') ? document.getElementById('sh_qr_img').src : '';
    var w   = window.open('', '_blank');
    if (!w) return;
    w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>QR</title>'
      + '<style>body{font-family:Arial,sans-serif;text-align:center;padding:40px;max-width:380px;margin:0 auto;}'
      + '.amt{font-size:2.5rem;font-weight:900;color:#10b981;margin:10px 0;}'
      + 'img{width:230px;height:230px;border:3px solid #3b82f6;border-radius:12px;padding:8px;}'
      + '</style></head><body>'
      + '<div style="font-size:1rem;font-weight:700;">Оплата через СБП</div>'
      + '<div class="amt">' + formatMoney(this._qrAmount) + '</div>'
      + '<div style="font-size:.85rem;color:#666;margin-bottom:18px;">' + (this._qrDesc || 'Оплата услуг') + '</div>'
      + (img ? '<img src="' + img + '" alt="QR">' : '<div style="color:red;">QR не загружен</div>')
      + '<div style="font-size:.8rem;color:#666;margin-top:14px;">Отсканируйте QR-код приложением банка</div>'
      + '</body></html>');
    w.document.close();
    w.onload = function() { w.print(); };
  },

  /* ================================================================
     ИСТОРИЯ СМЕН
  ================================================================ */
  _loadHistory: async function() {
    var res  = await CRM.api('shift', 'history');
    var hist = (res && res.data) ? res.data : [];
    var cnt  = document.getElementById('sh_hist_count');
    if (cnt) cnt.textContent = hist.length;
    var tbody = document.getElementById('sh_history_body');
    if (!tbody) return;
    if (!hist.length) {
      tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:20px;color:var(--sh-muted);font-size:.78rem;">Смен пока не было</td></tr>';
      return;
    }
    var html = '';
    for (var i = 0; i < hist.length; i++) {
      var s    = hist[i];
      var ms   = s.closeTime ? new Date(s.closeTime) - new Date(s.openTime) : 0;
      var dur  = ms ? Math.floor(ms/3600000) + 'ч ' + String(Math.floor((ms%3600000)/60000)).padStart(2,'0') + 'м' : '—';
      var diff = ((s.endCash || 0) - (s.cash || 0)).toFixed(2);
      var prof = (s.totalIncome || 0) - (s.totalExpense || 0);
      var dj   = JSON.stringify(s).replace(/"/g, '&quot;');
      var diffColor = Math.abs(diff) < 0.01 ? 'var(--sh-green)' : (parseFloat(diff) < 0 ? 'var(--sh-red)' : 'var(--sh-amber)');
      var diffTxt   = Math.abs(diff) < 0.01 ? '✓ 0' : (parseFloat(diff) > 0 ? '+' : '') + diff;
      html += '<tr>'
        + '<td>' + new Date(s.openTime).toLocaleDateString('ru') + '</td>'
        + '<td style="font-weight:600;">' + s.manager + '</td>'
        + '<td>' + dur + '</td>'
        + '<td style="color:var(--sh-green);font-weight:700;">'   + formatMoney(s.totalIncome  || 0) + '</td>'
        + '<td style="color:var(--sh-red);font-weight:700;">'     + formatMoney(s.totalExpense || 0) + '</td>'
        + '<td style="font-weight:800;color:' + (prof >= 0 ? 'var(--sh-green)' : 'var(--sh-red)') + ';">' + formatMoney(prof) + '</td>'
        + '<td>' + formatMoney(s.endCash || s.cash || 0) + '</td>'
        + '<td style="font-weight:700;color:' + diffColor + ';">' + diffTxt + ' ₽</td>'
        + '<td style="color:var(--sh-purple);font-weight:700;">'  + formatMoney(s.totalSalary  || 0) + '</td>'
        + '<td><button class="sh-btn sh-btn-ghost sh-btn-xs" onclick="CRM.modules.shift._printZ(' + dj + ')">' + shIcon('report',11) + ' Отчёт</button></td>'
        + '</tr>';
    }
    tbody.innerHTML = html;
  },

  /* ================================================================
     ОТЧЁТЫ
  ================================================================ */
  _loadReports: async function() {
    var res     = await CRM.api('shift', 'reports');
    var reports = (res && res.data) ? res.data : [];
    var el      = document.getElementById('sh_reports_list');
    if (!el) return;

    if (!reports.length) {
      el.innerHTML = '<div style="text-align:center;padding:48px;color:var(--sh-muted);">'
        + '<div style="margin-bottom:10px;opacity:.2;">' + shIcon('report',44,'var(--sh-muted)') + '</div>'
        + '<div style="font-weight:700;font-size:.9rem;">Z-Отчётов пока нет</div>'
        + '<div style="font-size:.76rem;margin-top:4px;">Появятся после закрытия первой смены</div></div>';
      return;
    }

    var html = '';
    for (var ri = 0; ri < reports.length; ri++) {
      var r  = reports[ri];
      var ms = (r.closeTime && r.openTime) ? new Date(r.closeTime) - new Date(r.openTime) : 0;
      var dur = ms ? Math.floor(ms/3600000) + 'ч ' + String(Math.floor((ms%3600000)/60000)).padStart(2,'0') + 'м' : '—';
      var mt  = r.methodTotals || {};
      var dj  = JSON.stringify(r).replace(/"/g, '&quot;');

      /* методы */
      var methodRows = '';
      for (var m in mt) {
        methodRows += '<div style="display:flex;justify-content:space-between;font-size:.76rem;padding:3px 0;">'
          + '<span style="color:var(--sh-muted);">' + m + '</span>'
          + '<div style="display:flex;gap:8px;">'
          +   (mt[m].income  ? '<span style="color:var(--sh-green);font-weight:700;">+'  + formatMoney(mt[m].income)  + '</span>' : '')
          +   (mt[m].expense ? '<span style="color:var(--sh-red);">-' + formatMoney(mt[m].expense) + '</span>' : '')
          + '</div></div>';
      }

      /* операции */
      var ops     = r.operations || [];
      var opRows  = '';
      for (var oi = 0; oi < ops.length; oi++) {
        var op    = ops[oi];
        var isInc = op.type === 'income';
        opRows += '<div style="display:flex;align-items:center;gap:8px;padding:7px 11px;border-bottom:1px solid var(--sh-border);">'
          + '<span style="font-size:.65rem;color:' + (isInc ? 'var(--sh-green)' : 'var(--sh-red)') + ';">' + (isInc ? '▲' : '▼') + '</span>'
          + '<span style="flex:1;font-size:.76rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + (op.desc || '—') + '</span>'
          + '<span style="font-size:.65rem;color:var(--sh-muted);">' + (op.method || 'Нал.') + '</span>'
          + '<span style="font-weight:800;font-size:.78rem;color:' + (isInc ? 'var(--sh-green)' : 'var(--sh-red)') + ';">' + (isInc ? '+' : '-') + formatMoney(op.amount) + '</span>'
          + '</div>';
      }

      /* cashDiff */
      var cashDiffHtml = '';
      if (Math.abs(r.cashDiff || 0) >= 0.01) {
        cashDiffHtml = '<div class="' + (r.cashDiff < 0 ? 'sh-diff-neg' : 'sh-diff-pos') + '" style="margin-top:5px;">'
          + (r.cashDiff < 0 ? 'Недостача' : 'Излишек') + ': ' + Math.abs(r.cashDiff).toFixed(2) + ' ₽</div>';
      } else {
        cashDiffHtml = '<div class="sh-diff-ok" style="margin-top:5px;">✓ Расхождений нет</div>';
      }

      html += '<div class="sh-report-card">'
        + '<div class="sh-report-hdr">'
        +   '<div>'
        +     '<div style="font-weight:900;color:var(--sh-text);font-size:.92rem;">Z-Отчёт · ' + r.shiftDate + '</div>'
        +     '<div style="font-size:.72rem;color:var(--sh-muted);margin-top:2px;">' + r.manager + ' · ' + dur + ' · ' + r.operationsCount + ' операций</div>'
        +   '</div>'
        +   '<button class="sh-btn sh-btn-ghost sh-btn-sm" style="display:inline-flex;align-items:center;gap:5px;" onclick="CRM.modules.shift._printZ(' + dj + ')">' + shIcon('report',13) + ' Печать</button>'
        + '</div>'
        + '<div style="padding:14px 16px;">'

        /* 4 KPI */
        + '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px;">';

      var kpiData = [
        ['Доход',   r.totalIncome  || 0, 'var(--sh-green)'],
        ['Расход',  r.totalExpense || 0, 'var(--sh-red)'],
        [(r.profit || 0) >= 0 ? 'Прибыль' : 'Убыток', r.profit || 0, (r.profit || 0) >= 0 ? 'var(--sh-green)' : 'var(--sh-red)'],
        ['ЗП выплачено', r.totalSalary || 0, 'var(--sh-purple)'],
      ];
      for (var ki = 0; ki < kpiData.length; ki++) {
        html += '<div style="text-align:center;padding:9px;background:var(--sh-card2);border-radius:9px;border:1px solid var(--sh-border);">'
          + '<div style="font-weight:900;font-size:.92rem;color:' + kpiData[ki][2] + ';">' + formatMoney(kpiData[ki][1]) + '</div>'
          + '<div style="font-size:.6rem;color:var(--sh-muted);margin-top:2px;">' + kpiData[ki][0] + '</div>'
          + '</div>';
      }

      html += '</div>'  /* конец KPI grid */

        /* касса */
        + '<div style="background:var(--sh-card2);border-radius:9px;padding:9px 12px;border:1px solid var(--sh-border);margin-bottom:10px;">'
        +   '<div style="font-size:.63rem;font-weight:700;color:var(--sh-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;">Касса</div>'
        +   '<div style="display:flex;justify-content:space-between;font-size:.77rem;padding:2px 0;"><span style="color:var(--sh-muted);">Начало</span><span>'       + formatMoney(r.startCash || 0) + '</span></div>'
        +   '<div style="display:flex;justify-content:space-between;font-size:.77rem;padding:2px 0;"><span style="color:var(--sh-muted);">Расчётный</span><span>'  + formatMoney(r.calcCash  || 0) + '</span></div>'
        +   '<div style="display:flex;justify-content:space-between;font-size:.77rem;padding:2px 0;"><span style="color:var(--sh-muted);">Фактически</span><span>' + formatMoney(r.endCash   || 0) + '</span></div>'
        +   cashDiffHtml
        + '</div>'

        /* методы */
        + (Object.keys(mt).length
          ? '<div style="background:var(--sh-card2);border-radius:9px;padding:9px 12px;border:1px solid var(--sh-border);margin-bottom:10px;">'
          +   '<div style="font-size:.63rem;font-weight:700;color:var(--sh-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px;">По методам</div>'
          +   methodRows
          + '</div>'
          : '')

        /* ЗП */
        + '<details><summary style="cursor:pointer;font-size:.72rem;font-weight:700;color:var(--sh-muted);list-style:none;display:flex;align-items:center;gap:5px;padding:4px 0;">' + shIcon('chart',13) + ' Зарплата</summary>'
        +   '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px;">'
        +     '<div style="background:var(--sh-card2);border-radius:9px;padding:8px 11px;border:1px solid var(--sh-border);">'
        +       '<div style="font-size:.62rem;color:var(--sh-muted);">Оклад</div>'
        +       '<div style="font-weight:800;font-size:.9rem;">' + formatMoney(r.baseSalary || 0) + '</div>'
        +     '</div>'
        +     '<div style="background:rgba(200,150,234,.07);border-radius:9px;padding:8px 11px;border:1px solid rgba(200,150,234,.2);">'
        +       '<div style="font-size:.62rem;color:var(--sh-purple);">Бонус ' + (r.bonusPct || 0.5) + '%</div>'
        +       '<div style="font-weight:800;font-size:.9rem;color:var(--sh-purple);">+' + formatMoney(r.accruedBonus || 0) + '</div>'
        +     '</div>'
        +   '</div>'
        +   '<div style="margin-top:6px;padding:8px 11px;background:rgba(76,175,130,.07);border:1px solid rgba(76,175,130,.2);border-radius:9px;display:flex;justify-content:space-between;align-items:center;">'
        +     '<span style="font-size:.78rem;font-weight:700;">Итого к выплате:</span>'
        +     '<span style="font-size:1rem;font-weight:900;color:var(--sh-green);">' + formatMoney(r.totalSalary || 0) + '</span>'
        +   '</div>'
        + '</details>'

        /* операции */
        + '<details style="margin-top:8px;"><summary style="cursor:pointer;font-size:.72rem;font-weight:700;color:var(--sh-muted);list-style:none;display:flex;align-items:center;gap:5px;padding:4px 0;">' + shIcon('report',13) + ' Операции (' + r.operationsCount + ')</summary>'
        +   '<div style="margin-top:8px;border:1px solid var(--sh-border);border-radius:10px;overflow:hidden;max-height:280px;overflow-y:auto;">' + opRows + '</div>'
        + '</details>'

        + (r.note ? '<div style="margin-top:8px;padding:8px 11px;background:var(--sh-card2);border-radius:8px;font-size:.76rem;color:var(--sh-muted);">💬 ' + r.note + '</div>' : '')

        + '</div>' /* конец padding div */
        + '</div>'; /* конец sh-report-card */
    }
    el.innerHTML = html;
  },

  /* ================================================================
     НАСТРОЙКИ КНОПОК
  ================================================================ */
  _renderBtnsSettings: function() {
    var listEl    = document.getElementById('sh_btns_list');
    var previewEl = document.getElementById('sh_btns_preview');
    var emptyPrev = document.getElementById('sh_btns_preview_empty');
    if (!listEl) return;

    if (!this._buttons.length) {
      listEl.innerHTML = '<div style="text-align:center;padding:18px;color:var(--sh-muted);border:2px dashed var(--sh-border);border-radius:11px;font-size:.78rem;">Кнопок пока нет</div>';
    } else {
      var html = '';
      for (var i = 0; i < this._buttons.length; i++) {
        var btn = this._buttons[i];
        var clr = btn.type === 'income' ? 'var(--sh-green)' : 'var(--sh-red)';
        var pfx = btn.type === 'income' ? '+' : '-';
        html += '<div style="display:flex;align-items:center;gap:9px;background:var(--sh-card2);border-radius:10px;padding:8px 11px;margin-bottom:6px;border:1.5px solid ' + btn.color + '22;">'
          + '<div style="width:20px;height:20px;border-radius:5px;flex-shrink:0;background:' + btn.color + '18;border:1px solid ' + btn.color + '44;display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:900;color:' + btn.color + ';">' + (i < 9 ? i + 1 : '—') + '</div>'
          + shIcon(btn.icon || 'default', 18, btn.color)
          + '<div style="flex:1;min-width:0;">'
          +   '<div style="font-weight:700;font-size:.8rem;">' + btn.label + '</div>'
          +   '<div style="font-size:.66rem;color:var(--sh-muted);">' + (btn.type === 'income' ? '▲ Приход' : '▼ Расход') + ' · <b style="color:' + clr + ';">' + pfx + formatMoney(btn.amount) + '</b></div>'
          + '</div>'
          + '<button class="sh-btn sh-btn-icon sm" style="background:rgba(244,112,103,.1);border:1px solid rgba(244,112,103,.2);" onclick="CRM.modules.shift._deleteBtn(\'' + btn.id + '\')">' + shIcon('trash',12,'var(--sh-red)') + '</button>'
          + '</div>';
      }
      listEl.innerHTML = html;
    }

    if (previewEl) {
      if (!this._buttons.length) {
        previewEl.style.display = 'none';
        if (emptyPrev) emptyPrev.style.display = 'block';
      } else {
        previewEl.style.display = 'grid';
        if (emptyPrev) emptyPrev.style.display = 'none';
        var html2 = '';
        for (var j = 0; j < this._buttons.length; j++) {
          var b   = this._buttons[j];
          var clr2 = b.type === 'income' ? 'var(--sh-green)' : 'var(--sh-red)';
          var pfx2 = b.type === 'income' ? '+' : '-';
          html2 += '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;padding:9px 4px;border-radius:11px;background:var(--sh-card2);border:1.5px solid ' + b.color + '44;min-height:70px;position:relative;">'
            + '<span style="position:absolute;top:3px;left:5px;font-size:.48rem;font-weight:900;color:' + b.color + ';">' + (j < 9 ? j + 1 : '') + '</span>'
            + shIcon(b.icon || 'default', 20, b.color)
            + '<span style="font-size:.6rem;font-weight:700;text-align:center;max-width:68px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + b.label + '</span>'
            + '<span style="font-size:.7rem;font-weight:900;color:' + clr2 + ';">' + pfx2 + formatMoney(b.amount) + '</span>'
            + '</div>';
        }
        previewEl.innerHTML = html2;
      }
    }
  },

  _addButton: async function() {
    var label  = document.getElementById('sb_label')  ? document.getElementById('sb_label').value.trim()  : '';
    var amount = parseFloat(document.getElementById('sb_amount') ? document.getElementById('sb_amount').value : 0) || 0;
    var type   = document.getElementById('sb_type')   ? document.getElementById('sb_type').value   : 'income';
    var icon   = document.getElementById('sb_icon')   ? document.getElementById('sb_icon').value   : 'default';
    var color  = document.getElementById('sb_color')  ? document.getElementById('sb_color').value  : '#f59e0b';
    if (!label)  { notify('Введите название', 'error'); return; }
    if (!amount) { notify('Введите сумму',    'error'); return; }
    var res = await CRM.api('shift', 'addButton', { label:label, amount:amount, type:type, icon:icon, color:color });
    if (res && res.ok) {
      this._buttons.push(res.data);
      this._renderBtnsSettings();
      this._renderQuickBtns();
      document.getElementById('sb_label').value  = '';
      document.getElementById('sb_amount').value = '';
      notify('Кнопка добавлена: ' + label, 'success');
    } else {
      notify((res && res.error) || 'Ошибка', 'error');
    }
  },

  _deleteBtn: async function(id) {
    if (!confirm('Удалить кнопку?')) return;
    var res = await CRM.api('shift', 'deleteButton', { id:id });
    if (res && res.ok) {
      var filtered = [];
      for (var i = 0; i < this._buttons.length; i++) {
        if (this._buttons[i].id !== id) filtered.push(this._buttons[i]);
      }
      this._buttons = filtered;
      this._renderBtnsSettings();
      this._renderQuickBtns();
      notify('Кнопка удалена', 'info');
    }
  },

  /* ================================================================
     Z-ОТЧЁТ ПЕЧАТЬ
  ================================================================ */
  _printZ: function(r) {
    var ops     = r.operations || [];
    var totalIn = 0, totalOut = 0;
    for (var i = 0; i < ops.length; i++) {
      if (ops[i].type === 'income') totalIn  += ops[i].amount;
      else                          totalOut += ops[i].amount;
    }
    var ms  = (r.closeTime && r.openTime) ? new Date(r.closeTime) - new Date(r.openTime) : 0;
    var dur = ms ? Math.floor(ms/3600000) + 'ч ' + String(Math.floor((ms%3600000)/60000)).padStart(2,'0') + 'м' : '—';
    var mt  = r.methodTotals || {};

    var methodSection = '';
    for (var m in mt) {
      methodSection += '<div class="row"><span>' + m + '</span><span>'
        + '<span class="inc">+' + (mt[m].income || 0).toFixed(2) + ' ₽</span>'
        + (mt[m].expense ? '<span class="exp"> / -' + (mt[m].expense || 0).toFixed(2) + ' ₽</span>' : '')
        + '</span></div>';
    }
    if (methodSection) methodSection = '<h3>ПО МЕТОДАМ</h3>' + methodSection;

    var opRows = '';
    for (var oi = 0; oi < ops.length; oi++) {
      var op   = ops[oi];
      var isIn = op.type === 'income';
      opRows += '<tr>'
        + '<td>' + new Date(op.time).toLocaleTimeString('ru', {hour:'2-digit', minute:'2-digit'}) + '</td>'
        + '<td class="' + (isIn ? 'inc' : 'exp') + '">' + (isIn ? '▲' : '▼') + '</td>'
        + '<td>' + (op.desc || '—') + '</td>'
        + '<td style="text-align:center;">' + (op.qty || 1) + '</td>'
        + '<td>' + (op.method || 'Нал.') + '</td>'
        + '<td class="' + (isIn ? 'inc' : 'exp') + '"><b>' + (isIn ? '+' : '-') + op.amount.toFixed(2) + ' ₽</b></td>'
        + '</tr>';
    }

    var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Z-Отчёт ' + r.shiftDate + '</title>'
      + '<style>*{box-sizing:border-box;margin:0;padding:0;}'
      + 'body{font-family:"Courier New",monospace;font-size:12px;padding:20px;max-width:620px;margin:0 auto;}'
      + '.hdr{text-align:center;border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:14px;}'
      + 'h1{font-size:18px;font-weight:900;margin-bottom:4px;}'
      + 'h3{font-size:12px;font-weight:900;margin:14px 0 7px;border-bottom:1px dashed #000;padding-bottom:3px;}'
      + '.row{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dotted #ccc;}'
      + '.row.total{font-weight:900;font-size:13px;border-top:2px solid #000;border-bottom:2px solid #000;padding:6px 0;margin-top:3px;}'
      + '.inc{color:#15803d;}.exp{color:#dc2626;}'
      + 'table{width:100%;border-collapse:collapse;font-size:11px;margin-top:7px;}'
      + 'td,th{border:1px solid #ddd;padding:4px 7px;}th{background:#f5f5f5;font-weight:900;}'
      + '.sign{display:flex;justify-content:space-between;margin-top:40px;}'
      + '</style></head><body>'
      + '<div class="hdr"><h1>Z - О Т Ч Ё Т</h1><div>Кассовая смена · ' + r.shiftDate + '</div></div>'
      + '<h3>СМЕНА</h3>'
      + '<div class="row"><span>Менеджер</span><b>'    + r.manager + '</b></div>'
      + '<div class="row"><span>Открыта</span><span>'  + new Date(r.openTime).toLocaleString('ru')  + '</span></div>'
      + '<div class="row"><span>Закрыта</span><span>'  + new Date(r.closeTime).toLocaleString('ru') + '</span></div>'
      + '<div class="row"><span>Длительность</span><span>' + dur + '</span></div>'
      + '<div class="row"><span>Операций</span><span>' + r.operationsCount + '</span></div>'
      + '<h3>КАССА</h3>'
      + '<div class="row"><span>Начало</span><span>'        + (r.startCash || 0).toFixed(2) + ' ₽</span></div>'
      + '<div class="row"><span>Приход</span><span class="inc"><b>+'  + (r.totalIncome  || 0).toFixed(2) + ' ₽</b></span></div>'
      + '<div class="row"><span>Расход</span><span class="exp"><b>-'  + (r.totalExpense || 0).toFixed(2) + ' ₽</b></span></div>'
      + '<div class="row"><span>Расчётный остаток</span><b>' + (r.calcCash || 0).toFixed(2) + ' ₽</b></div>'
      + '<div class="row"><span>Фактически</span><b>'  + (r.endCash  || 0).toFixed(2) + ' ₽</b></div>'
      + '<div class="row total"><span>РАСХОЖДЕНИЕ</span><span class="' + (Math.abs(r.cashDiff || 0) < 0.01 ? 'inc' : 'exp') + '">'
      +   ((r.cashDiff || 0) >= 0 ? '+' : '') + (r.cashDiff || 0).toFixed(2) + ' ₽ '
      +   (Math.abs(r.cashDiff || 0) < 0.01 ? 'Норма' : (r.cashDiff || 0) < 0 ? 'Недостача' : 'Излишек')
      + '</span></div>'
      + methodSection
      + '<h3>ИТОГИ</h3>'
      + '<div class="row"><span>Доход</span><span class="inc"><b>+'   + (r.totalIncome  || 0).toFixed(2) + ' ₽</b></span></div>'
      + '<div class="row"><span>Расход</span><span class="exp"><b>-'  + (r.totalExpense || 0).toFixed(2) + ' ₽</b></span></div>'
      + '<div class="row total"><span>ПРИБЫЛЬ</span><span class="' + ((r.profit || 0) >= 0 ? 'inc' : 'exp') + '"><b>' + (r.profit || 0).toFixed(2) + ' ₽</b></span></div>'
      + '<h3>ЗАРПЛАТА</h3>'
      + '<div class="row"><span>Оклад</span><span>'                   + (r.baseSalary  || 0).toFixed(2) + ' ₽</span></div>'
      + '<div class="row"><span>Бонус ' + (r.bonusPct || 0.5) + '% с ' + (r.totalIncome || 0).toFixed(2) + ' ₽</span><span class="inc">+' + (r.accruedBonus || 0).toFixed(2) + ' ₽</span></div>'
      + '<div class="row total"><span>К ВЫПЛАТЕ</span><b>'            + (r.totalSalary || 0).toFixed(2) + ' ₽</b></div>'
      + '<h3>ОПЕРАЦИИ</h3>'
      + '<table><thead><tr><th>Время</th><th>Тип</th><th>Описание</th><th>Кол.</th><th>Метод</th><th>Сумма</th></tr></thead>'
      + '<tbody>' + opRows
      + '<tr><td colspan="5"><b>ИТОГО</b></td><td><span class="inc">+' + totalIn.toFixed(2) + '</span> / <span class="exp">-' + totalOut.toFixed(2) + '</span> ₽</td></tr>'
      + '</tbody></table>'
      + (r.note ? '<p style="margin-top:12px;"><b>Заметки:</b> ' + r.note + '</p>' : '')
      + '<div class="sign"><div>Менеджер: _____________________</div><div>Принял: _____________________</div></div>'
      + '</body></html>';

    var w = window.open('', '_blank');
    if (w) {
      w.document.write(html);
      w.document.close();
      w.onload = function() { w.print(); };
    }
  },

  /* ================================================================
     УТИЛИТЫ
  ================================================================ */
  _esc: function(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
      var map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'": '&#39;' };
      return map[m];
    });
  },

}); /* конец registerModule */

})(); /* конец IIFE */
</script>