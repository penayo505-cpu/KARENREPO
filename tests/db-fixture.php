<?php
// Test fixture only. Refuses to run on production configuration.
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('VITAL_DENT_TESTS')!=='1') exit(1);
$c=require dirname(__DIR__).'/server/config.php';
if ($c['app_url']!=='http://localhost:8090' || !str_ends_with($c['db_name'],'_test')) exit(2);
$db=new PDO('mysql:host='.$c['db_host'].';port='.$c['db_port'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
switch ($argv[1]??'') {
    case 'reset':
        $db->exec('DROP TABLE IF EXISTS vd_remember_tokens');
        $db->exec('DELETE FROM vd_users');$db->exec('DELETE FROM vd_auth_limits');$db->exec('DELETE FROM vd_attachments');
        $q=$db->prepare('UPDATE vd_clinic SET revision=0,payload=? WHERE id=1');
        $q->execute(['{"patients":[],"appointments":[],"sessions":[],"treatments":[],"payments":[],"teeth":{}}']);
        break;
    case 'tokens': echo json_encode($db->query('SELECT token_hash,expires_at FROM vd_remember_tokens')->fetchAll(PDO::FETCH_ASSOC));break;
    case 'expire': $db->exec('UPDATE vd_remember_tokens SET expires_at=UNIX_TIMESTAMP()-1');break;
    default: exit(3);
}
