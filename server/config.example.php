<?php
// Copiar a config.php y completar SOLO en el hosting. Nunca subir config.php a GitHub.
return [
    'app_url' => 'https://vdnt.bmimportexport.com.py',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'USUARIO_vitaldent',
    'db_user' => 'USUARIO_vitaldent',
    'db_password' => 'COMPLETAR_EN_EL_HOSTING',
    // Crear una cadena aleatoria privada de 64 caracteres, por ejemplo con:
    // php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
    // Se solicita SOLO al crear el primer acceso. Vaciarla después de instalar.
    'setup_token' => '',
];
