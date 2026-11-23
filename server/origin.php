<?php
declare(strict_types=1);

/** Compare web origins, not full URLs. Never infer trust from Host or forwarded headers. */
function web_origin(mixed $url, bool $allowPath=false): ?string {
    if (!is_string($url)) return null;
    $url=trim($url);
    if ($url==='' || preg_match('/[\x00-\x20\x7f\\\\]/',$url)) return null;
    $parts=parse_url($url);
    if ($parts===false || !isset($parts['scheme'],$parts['host'])) return null;
    if (isset($parts['user']) || isset($parts['pass'])) return null;
    if (!$allowPath && (isset($parts['query']) || isset($parts['fragment']))) return null;
    if (!$allowPath && !in_array($parts['path']??'',['','/'],true)) return null;
    $scheme=strtolower($parts['scheme']);
    $host=strtolower($parts['host']);
    if (!in_array($scheme,['http','https'],true) || !preg_match('/^(?:[a-z0-9.-]+|\[[a-f0-9:]+\])$/D',$host)) return null;
    $port=$parts['port']??($scheme==='https'?443:80);
    if ($port<1 || $port>65535) return null;
    return $scheme.'://'.$host.(($scheme==='https'&&$port===443)||($scheme==='http'&&$port===80)?'':':'.$port);
}

function request_origin_allowed(array $server, string $expected): bool {
    if (($server['HTTP_SEC_FETCH_SITE']??'')==='cross-site') return false;
    if (array_key_exists('HTTP_ORIGIN',$server)) {
        $source=$server['HTTP_ORIGIN'];
        if (web_origin($source)===$expected) return true;
        // WebKit can send an opaque Origin after redirects. Require a matching
        // Referer in that case. POST requests still require the session's CSRF token.
        return $source==='null' && web_origin($server['HTTP_REFERER']??null,true)===$expected;
    }
    // Older Safari may omit Origin. A supplied Referer must still be trusted;
    // without either header, CSRF + SameSite cookies continue to protect writes.
    return !array_key_exists('HTTP_REFERER',$server) || web_origin($server['HTTP_REFERER'],true)===$expected;
}

/** Public routing evidence only: no cookies, tokens, paths, queries or credentials. */
function origin_diagnostic(array $server, string $expected): array {
    $source=$server['HTTP_ORIGIN']??null;
    $referer=$server['HTTP_REFERER']??null;
    $site=$server['HTTP_SEC_FETCH_SITE']??'missing';
    return [
        'server_version'=>'3.1.0',
        'expected_origin'=>$expected,
        'received_origin'=>$source===null?'missing':($source==='null'?'null':(web_origin($source)??'invalid')),
        'referer_origin'=>$referer===null?'missing':(web_origin($referer,true)??'invalid'),
        'fetch_site'=>in_array($site,['same-origin','same-site','cross-site','none','missing'],true)?$site:'invalid',
    ];
}
