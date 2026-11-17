<?php
declare(strict_types=1);
require dirname(__DIR__).'/server/origin.php';
$count=0;
function expect(mixed $actual, mixed $expected): void {
    global $count;$count++;
    if ($actual!==$expected) {fwrite(STDERR,'Failed case '.$count.PHP_EOL);exit(1);}
}
$expected='https://vdnt.bmimportexport.com.py';
foreach ([$expected, $expected.'/', strtoupper($expected), ' '.$expected.' ', $expected.':443', $expected.'/public/', $expected.'/vdnt/public/'] as $config) expect(web_origin($config,true),$expected);
expect(web_origin('http://LOCALHOST:80/'), 'http://localhost');
expect(web_origin('http://localhost:8090/'), 'http://localhost:8090');
expect(web_origin('https://[::1]:443/'), 'https://[::1]');
foreach (['null','',null,'javascript:alert(1)','https://user:pass@vdnt.bmimportexport.com.py','https://host:99999',"https://host\n.invalid",'https://host\\.invalid'] as $invalid) expect(web_origin($invalid,true),null);
expect(web_origin($expected.'/public/'), null);
expect(web_origin($expected.'/?x=1'),null);
expect(web_origin($expected.'/#x'),null);
expect(request_origin_allowed(['HTTP_ORIGIN'=>'null','HTTP_REFERER'=>$expected.'/?from=home'],$expected),true);
foreach ([$expected,strtoupper($expected),$expected.':443',$expected.'/'] as $origin) expect(request_origin_allowed(['HTTP_ORIGIN'=>$origin],$expected),true);
foreach (['https://evil.example','https://vdnt.bmimportexport.com.py.evil.example','https://evil-vdnt.bmimportexport.com.py','http://vdnt.bmimportexport.com.py',$expected.':444',$expected.'/public/','https://user@vdnt.bmimportexport.com.py','null',''] as $origin) expect(request_origin_allowed(['HTTP_ORIGIN'=>$origin],$expected),false);
expect(request_origin_allowed(['HTTP_ORIGIN'=>'null','HTTP_REFERER'=>$expected.'/index.html','HTTP_SEC_FETCH_SITE'=>'same-origin'],$expected),true);
expect(request_origin_allowed(['HTTP_ORIGIN'=>'null','HTTP_REFERER'=>$expected.'/','HTTP_SEC_FETCH_SITE'=>'cross-site'],$expected),false);
expect(request_origin_allowed(['HTTP_ORIGIN'=>'null','HTTP_REFERER'=>'https://evil.example/'],$expected),false);
expect(request_origin_allowed(['HTTP_ORIGIN'=>'https://evil.example','HTTP_REFERER'=>$expected.'/'],$expected),false);
expect(request_origin_allowed(['HTTP_REFERER'=>$expected.'/index.html'],$expected),true);
expect(request_origin_allowed(['HTTP_REFERER'=>'https://evil.example/'],$expected),false);
expect(request_origin_allowed([],$expected),true);
expect(request_origin_allowed(['HTTP_SEC_FETCH_SITE'=>'cross-site'],$expected),false);
echo 'PASS: '.$count.' origin cases: canonical configuration, default ports, WebKit fallback, missing headers and cross-origin rejection.'.PHP_EOL;
