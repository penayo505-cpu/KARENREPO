<?php
declare(strict_types=1);
function invalid(): never { throw new InvalidArgumentException('Datos clínicos inválidos o incompletos.'); }
function strings(array $r, array $keys): void {
    foreach ($keys as $key) if (!isset($r[$key]) || !is_string($r[$key]) || strlen($r[$key]) > 50000) invalid();
}
function day(mixed $v): bool {
    if (!is_string($v)) return false;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
}
function validate_clinic(mixed $d): void {
    if (!is_array($d)) invalid();
    $keys = ['patients','appointments','sessions','treatments','payments'];
    $patientIds = [];
    foreach ($keys as $key) {
        if (!isset($d[$key]) || !is_array($d[$key]) || !array_is_list($d[$key])) invalid();
        $seen = [];
        foreach ($d[$key] as $r) {
            if (!is_array($r) || !isset($r['id']) || !is_string($r['id']) || !preg_match('/^[a-f0-9-]{36}$/D', $r['id']) || isset($seen[$r['id']])) invalid();
            $seen[$r['id']] = true;
            if ($key === 'patients') {
                strings($r, ['name','document','phone','email','address','emergency','notes','alert','birth']);
                if (!trim($r['name']) || !is_bool($r['active'] ?? null) || ($r['birth'] !== '' && !day($r['birth']))) invalid();
                $patientIds[$r['id']] = true;
            } else {
                if (!isset($patientIds[$r['patientId'] ?? ''])) invalid();
                if ($key !== 'treatments' && !day($r['date'] ?? null)) invalid();
            }
            if ($key === 'appointments') {
                strings($r,['time','reason','status']);
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D',$r['time']) || !is_int($r['duration'] ?? null) || $r['duration']<5 || $r['duration']>480 || !in_array($r['status'],['Programada','Confirmada','Atendida','Cancelada'],true)) invalid();
            }
            if ($key === 'treatments' || $key === 'payments') {
                $number = $r[$key === 'treatments' ? 'cost' : 'amount'] ?? null;
                if ((!is_int($number) && !is_float($number)) || !is_finite((float)$number) || $number<($key==='payments'?1:0) || $number>1000000000 || floor($number)!=(float)$number) invalid();
                strings($r,['description']);
                if ($key === 'treatments') {
                    strings($r,['phase','status','tooth']);
                    if (!in_array($r['phase'],['Fase I','Fase II','Fase III'],true) || !in_array($r['status'],['Pendiente','En Curso','Completado'],true)) invalid();
                } elseif (!in_array($r['method']??null,['Efectivo','Transferencia','Tarjeta'],true)) invalid();
            }
            if ($key === 'sessions') {
                strings($r,['procedure','notes','anesthesia','materials','url']);
                if ($r['url']!=='' && (!filter_var($r['url'],FILTER_VALIDATE_URL) || parse_url($r['url'],PHP_URL_SCHEME)!=='https')) invalid();
                if (!isset($r['files']) || !is_array($r['files']) || !array_is_list($r['files'])) invalid();
                $bytes=0;
                foreach ($r['files'] as $f) {
                    if (!is_array($f)) invalid();
                    strings($f,['name','type']);
                    if (!in_array($f['type'],['image/png','image/jpeg','image/webp','application/pdf'],true)) invalid();
                    if (isset($f['id']) && preg_match('/^[a-f0-9]{64}$/D',$f['id']) && !isset($f['data'])) continue;
                    if (!is_string($f['data']??null)) invalid();
                    $prefix='data:'.$f['type'].';base64,';
                    if (!str_starts_with($f['data'],$prefix)) invalid();
                    $raw=base64_decode(substr($f['data'],strlen($prefix)),true);
                    if ($raw===false) invalid();
                    $bytes+=strlen($raw);
                    if ($bytes>10*1024*1024) invalid();
                }
            }
        }
    }
    if (!isset($d['teeth']) || !is_array($d['teeth'])) invalid();
    foreach ($d['teeth'] as $pid=>$teeth) {
        if (!isset($patientIds[$pid]) || !is_array($teeth)) invalid();
        foreach ($teeth as $n=>$tooth) {
            if (!preg_match('/^(?:[1-4][1-8]|[5-8][1-5])$/D',(string)$n) || !is_array($tooth)) invalid();
            foreach ($tooth as $face=>$status) if (!in_array($face,['Vestibular','Lingual','Mesial','Distal','Oclusal'],true) || !in_array($status,['Sano','Caries','Obturación (Resina)','Endodoncia','Corona','Ausente'],true)) invalid();
        }
    }
}
