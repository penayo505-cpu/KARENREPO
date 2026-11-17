<?php
declare(strict_types=1);
const REMEMBER_SECONDS = 30 * 24 * 60 * 60;
const REMEMBER_COOKIE = 'vd_remember';

function remember_cookie(string $value, int $expires, bool $local): void {
    setcookie(REMEMBER_COOKIE, $value, ['expires'=>$expires,'path'=>'/','secure'=>!$local,'httponly'=>true,'samesite'=>'Strict']);
}
function remember_hash(): ?string {
    $token=$_COOKIE[REMEMBER_COOKIE]??'';
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/D',$token) ? hash('sha256',$token) : null;
}
function remember_table(PDO $pdo): void {
    try { $pdo->query('SELECT token_hash FROM vd_remember_tokens LIMIT 0'); }
    catch (PDOException $e) {
        if ($e->getCode()!=='42S02') throw $e;
        // Additive upgrade: existing clinical tables and credentials are untouched.
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS vd_remember_tokens (
                token_hash CHAR(64) NOT NULL PRIMARY KEY,
                user_id TINYINT UNSIGNED NOT NULL,
                auth_version INT UNSIGNED NOT NULL,
                expires_at BIGINT UNSIGNED NOT NULL,
                INDEX (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (PDOException $migrationError) {
            reply(['error'=>'Para habilitar los 30 días, importá database/upgrade-2.1.sql en phpMyAdmin. El usuario MySQL no tiene permiso CREATE.'],503);
        }
    }
}
function remember_revoke(PDO $pdo, bool $local): void {
    $hashes=array_unique(array_filter([remember_hash(),$_SESSION['remember_hash']??null]));
    if ($hashes) {
        remember_table($pdo);
        $q=$pdo->prepare('DELETE FROM vd_remember_tokens WHERE token_hash=?');
        foreach ($hashes as $hash) $q->execute([$hash]);
    }
    unset($_SESSION['remember_hash'],$_SESSION['remember_expires']);
    remember_cookie('',time()-3600,$local);
}
function remember_issue(PDO $pdo, int $version, bool $local): void {
    remember_table($pdo);
    $token=bin2hex(random_bytes(32));
    $hash=hash('sha256',$token);
    $expires=time()+REMEMBER_SECONDS;
    $q=$pdo->prepare('INSERT INTO vd_remember_tokens(token_hash,user_id,auth_version,expires_at) VALUES(?,1,?,?)');
    $q->execute([$hash,$version,$expires]);
    $q=$pdo->prepare('DELETE FROM vd_remember_tokens WHERE expires_at<=?');$q->execute([time()]);
    $_SESSION['remember_hash']=$hash;
    $_SESSION['remember_expires']=$expires;
    remember_cookie($token,$expires,$local);
}
function remember_valid(PDO $pdo, string $hash, array $user): ?int {
    remember_table($pdo);
    $q=$pdo->prepare('SELECT expires_at FROM vd_remember_tokens WHERE token_hash=? AND user_id=1 AND auth_version=? AND expires_at>?');
    $q->execute([$hash,$user['auth_version'],time()]);
    $expires=$q->fetchColumn();
    return $expires===false ? null : (int)$expires;
}
function authenticate(PDO $pdo, array|false $user, bool $local): bool {
    if (!$user) return false;
    $base=($_SESSION['uid']??null)===1 && ($_SESSION['version']??null)===(int)$user['auth_version'];
    if ($base) {
        if (isset($_SESSION['remember_hash'])) {
            if (remember_valid($pdo,$_SESSION['remember_hash'],$user)!==null) {$_SESSION['last']=time();return true;}
        } elseif (time()-($_SESSION['last']??0)<28800) {$_SESSION['last']=time();return true;}
    }
    $hash=remember_hash();
    if ($hash && ($expires=remember_valid($pdo,$hash,$user))!==null) {
        // This also restores access after PHP has garbage-collected its session file.
        session_regenerate_id(true);
        $_SESSION=['uid'=>1,'version'=>(int)$user['auth_version'],'last'=>time(),'csrf'=>bin2hex(random_bytes(32)), 'remember_hash'=>$hash,'remember_expires'=>$expires];
        return true;
    }
    if ($hash || isset($_SESSION['remember_hash'])) remember_cookie('',time()-3600,$local);
    unset($_SESSION['uid'],$_SESSION['version'],$_SESSION['last'],$_SESSION['remember_hash'],$_SESSION['remember_expires']);
    return false;
}
