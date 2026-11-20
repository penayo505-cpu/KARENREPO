<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-Vital-Dent-Version: 3.0.0');
function reply(array $data, int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
set_exception_handler(function(Throwable $e): void {
    if ($e instanceof InvalidArgumentException || $e instanceof JsonException) reply(['error'=>'Solicitud inválida. Revisá los datos enviados.'],422);
    error_log('Vital Dent API error: '.get_class($e));
    reply(['error'=>'No se pudo completar la operación. Revisá la configuración del servidor y la base de datos.'],503);
});
$configFile=dirname(__DIR__,2).'/server/config.php';
if (!is_file($configFile)) reply(['error'=>'Falta server/config.php. Seguí INSTALACION.md.'],503);
$config=require $configFile;
require dirname(__DIR__,2).'/server/origin.php';
$origin=web_origin($config['app_url']??null,true);
if ($origin===null) reply(['error'=>'Revisá app_url en server/config.php: debe ser la URL HTTPS del consultorio.'],503);
$local=in_array(parse_url($origin,PHP_URL_HOST),['localhost','127.0.0.1'],true);
if (!$local && (parse_url($origin,PHP_URL_SCHEME)!=='https' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')) reply(['error'=>'Esta aplicación requiere HTTPS. Revisá SSL y la configuración del hosting.'],400);
if (!request_origin_allowed($_SERVER,$origin)) reply(['error'=>'Origen no autorizado. Abrí '.$origin.' directamente en Safari o Chrome.','code'=>'origin_not_allowed','diagnostic'=>origin_diagnostic($_SERVER,$origin)],403);
ini_set('session.use_strict_mode','1');
session_name('vd_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!$local,'httponly'=>true,'samesite'=>'Strict']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$pdo=new PDO('mysql:host='.$config['db_host'].';port='.$config['db_port'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
require dirname(__DIR__,2).'/server/validation.php';
$action=$_GET['action']??'status';
$method=$_SERVER['REQUEST_METHOD'];
if (!in_array($method,['GET','POST'],true)) reply(['error'=>'Método no permitido.'],405);
require dirname(__DIR__,2).'/server/remember.php';
$user=$pdo->query('SELECT id,username,password_hash,auth_version FROM vd_users WHERE id=1')->fetch(PDO::FETCH_ASSOC);
$authenticated=authenticate($pdo,$user,$local);
if ($method==='POST') {
    if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??'')) reply(['error'=>'Sesión vencida. Recargá la página.'],403);
    if (($_SERVER['CONTENT_LENGTH']??0)>32*1024*1024) reply(['error'=>'La base supera el límite de 32 MB por operación.'],413);
    $raw=file_get_contents('php://input',false,null,0,32*1024*1024+1);
    if (strlen($raw)>32*1024*1024) reply(['error'=>'Solicitud demasiado grande.'],413);
    $input=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($input)) reply(['error'=>'JSON inválido.'],422);
}
if ($action==='status' && $method==='GET') reply(['authenticated'=>(bool)$authenticated,'setup'=>!$user,'csrf'=>$_SESSION['csrf']]);
if (in_array($action,['login','setup'],true) && $method==='POST') {
    // Rate limit is stored in MySQL, so clearing cookies does not bypass it.
    $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
    $q=$pdo->prepare('INSERT INTO vd_auth_limits(ip_hash,attempts,window_start) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start < ?,1,attempts+1),window_start=IF(window_start < ?,VALUES(window_start),window_start)');
    $q->execute([$ip,time(),time()-900,time()-900]);
    $q=$pdo->prepare('SELECT attempts FROM vd_auth_limits WHERE ip_hash=?');$q->execute([$ip]);
    if ((int)$q->fetchColumn()>10) reply(['error'=>'Demasiados intentos. Esperá 15 minutos.'],429);
    if (isset($input['remember']) && !is_bool($input['remember'])) reply(['error'=>'Opción de sesión inválida.'],422);
    $pass=$input['password']??'';
    if (!is_string($pass) || strlen($pass)>1024 || ($input['username']??'')!=='karen.frutos') reply(['error'=>'Usuario o contraseña incorrectos.'],401);
    if ($action==='setup') {
        if ($user) reply(['error'=>'El acceso ya está configurado.'],409);
        $token=$config['setup_token']??'';
        if (strlen($token)<32 || !is_string($input['token']??null) || !hash_equals($token,$input['token'])) reply(['error'=>'Clave de instalación incorrecta o no configurada.'],403);
        if (strlen($pass)<12) reply(['error'=>'Usá una contraseña de al menos 12 caracteres.'],422);
        if (($input['remember']??false)===true) remember_table($pdo);
        $pdo->beginTransaction();
        $pdo->query('SELECT id FROM vd_clinic WHERE id=1 FOR UPDATE')->fetch();
        if ($pdo->query('SELECT id FROM vd_users WHERE id=1')->fetch()) {$pdo->rollBack();reply(['error'=>'El acceso ya está configurado.'],409);}
        $q=$pdo->prepare('INSERT INTO vd_users(id,username,password_hash) VALUES(1,?,?)');$q->execute(['karen.frutos',password_hash($pass,PASSWORD_DEFAULT)]);$pdo->commit();
        $version=1;
    } else {
        if (!$user || !password_verify($pass,$user['password_hash'])) reply(['error'=>'Usuario o contraseña incorrectos.'],401);
        if (($input['remember']??false)===true) remember_table($pdo);
        $version=(int)$user['auth_version'];
    }
    remember_revoke($pdo,$local);
    session_regenerate_id(true);
    $_SESSION=['uid'=>1,'version'=>$version,'last'=>time(),'csrf'=>bin2hex(random_bytes(32))];
    if (($input['remember']??false)===true) remember_issue($pdo,$version,$local);
    $q=$pdo->prepare('DELETE FROM vd_auth_limits WHERE ip_hash=? OR window_start < ?');$q->execute([$ip,time()-900]);
    reply(['authenticated'=>true,'csrf'=>$_SESSION['csrf']]);
}
if (!$authenticated) reply(['error'=>'Ingresá nuevamente al consultorio.'],401);
if ($action==='logout' && $method==='POST') {remember_revoke($pdo,$local);$_SESSION=[];session_destroy();setcookie('vd_session','',['expires'=>time()-3600,'path'=>'/','secure'=>!$local,'httponly'=>true,'samesite'=>'Strict']);reply(['ok'=>true]);}
if ($action==='password' && $method==='POST') {
    $pass=$input['password']??null;$current=$input['current']??null;
    if (!is_string($pass) || !is_string($current) || strlen($pass)<12 || strlen($pass)>1024) reply(['error'=>'Usá una contraseña de al menos 12 caracteres.'],422);
    if (!password_verify($current,$user['password_hash'])) reply(['error'=>'La contraseña actual no es correcta.'],403);
    remember_table($pdo);
    $q=$pdo->prepare('UPDATE vd_users SET password_hash=?,auth_version=auth_version+1 WHERE id=1 AND auth_version=?');$q->execute([password_hash($pass,PASSWORD_DEFAULT),$user['auth_version']]);
    if (!$q->rowCount()) reply(['error'=>'La sesión cambió. Ingresá nuevamente.'],409);
    $pdo->exec('DELETE FROM vd_remember_tokens WHERE user_id=1');
    remember_revoke($pdo,$local);
    $_SESSION['version']++;session_regenerate_id(true);reply(['ok'=>true]);
}
if ($action==='clinic' && $method==='GET') {
    $row=$pdo->query('SELECT revision,payload FROM vd_clinic WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    reply(['revision'=>(int)$row['revision'],'data'=>json_decode($row['payload'],false,512,JSON_THROW_ON_ERROR)]);
}
if ($action==='attachment' && $method==='GET') {
    $id=$_GET['id']??'';
    if (!preg_match('/^[a-f0-9]{64}$/D',$id)) reply(['error'=>'Adjunto inválido.'],422);
    $q=$pdo->prepare('SELECT mime,content FROM vd_attachments WHERE id=?');$q->execute([$id]);$file=$q->fetch(PDO::FETCH_ASSOC);
    if (!$file) reply(['error'=>'Adjunto no disponible.'],404);
    reply(['data'=>'data:'.$file['mime'].';base64,'.base64_encode($file['content'])]);
}
if ($action==='clinic' && $method==='POST') {
    validate_clinic($input['data']??null);
    if (!is_int($input['revision']??null)) reply(['error'=>'Falta la revisión.'],422);
    $pdo->beginTransaction();
    $version=(int)$pdo->query('SELECT revision FROM vd_clinic WHERE id=1 FOR UPDATE')->fetchColumn();
    if ($version!==$input['revision']) {$pdo->rollBack();reply(['error'=>'Hay cambios guardados desde otra pestaña o dispositivo. Exportá tu copia si necesitás conservarla y pulsá Actualizar datos antes de repetir la operación.'],409);}
    foreach ($input['data']['sessions'] as &$session) {
        $bytes=0;
        foreach ($session['files'] as &$file) {
            if (isset($file['data'])) {
                $raw=base64_decode(substr($file['data'],strpos($file['data'],',')+1),true);
                $id=hash('sha256',$file['type'].';'.$raw);
                $q=$pdo->prepare('INSERT IGNORE INTO vd_attachments(id,mime,content) VALUES(?,?,?)');$q->execute([$id,$file['type'],$raw]);
                $file['id']=$id;unset($file['data']);
            }
            $q=$pdo->prepare('SELECT mime,OCTET_LENGTH(content) AS size FROM vd_attachments WHERE id=?');$q->execute([$file['id']]);$stored=$q->fetch(PDO::FETCH_ASSOC);
            if (!$stored || $stored['mime']!==$file['type']) {$pdo->rollBack();reply(['error'=>'Falta un adjunto. Restaurá un respaldo completo.'],422);}
            $bytes+=(int)$stored['size'];
        }
        unset($file);
        if ($bytes>10*1024*1024) {$pdo->rollBack();reply(['error'=>'Los adjuntos de una sesión superan 10 MB.'],422);}
    }
    unset($session);
    $object=(object)$input['data'];$object->teeth=(object)$object->teeth;
    $q=$pdo->prepare('UPDATE vd_clinic SET payload=?,revision=revision+1 WHERE id=1');
    $q->execute([json_encode($object,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    reply(['revision'=>$version+1,'data'=>$object]);
}
reply(['error'=>'Operación no disponible.'],404);
