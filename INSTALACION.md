# Instalar Vital Dent en vdnt.bmimportexport.com.py

Esta edición funciona con **PHP 8.2 o superior, PDO MySQL, sesiones PHP y MySQL 8+/MariaDB 10.6+**. No funciona en GitHub Pages: GitHub almacena el código; tu hosting ejecuta PHP y guarda la base de datos. La interfaz es responsive para PC y Safari en iPhone, con instalación PWA. No requiere Node.js ni compilación en producción.

## 1. Subdominio, carpeta y HTTPS

En cPanel → Dominios → Crear dominio:

- Dominio: `vdnt.bmimportexport.com.py`.
- Desactivar compartir la raíz del dominio principal.
- Raíz del documento: la carpeta **`vdnt/public`** del hosting. El panel puede anteponer `/home/TU_USUARIO/` o `public_html/`; usá la ruta real que corresponda.
- Subir el proyecto COMPLETO dentro de `vdnt`, conservando la estructura:

```text
vdnt/
  server/
    config.example.php
    config.php             ← crear sólo en el hosting
    validation.php
  database/
    schema.sql
  public/                  ← raíz pública del subdominio
    index.html
    app.js
    styles.css
    manifest.json
    sw.js
    .htaccess
    .user.ini
    api/index.php
    icons/...
```

La dirección final es **https://vdnt.bmimportexport.com.py/**, sin `/vdnt/public` en la URL. No mover `server/` ni `database/` dentro de `public/`. El `.htaccess` del proyecto bloquea acceso a la raíz equivocada; `public/.htaccess` permite el sitio correcto. En Nginx configurar explícitamente `root` a `vdnt/public`, el handler PHP habitual y bloquear archivos ocultos. No usar el servidor de desarrollo PHP en producción.

Si el DNS está administrado fuera del hosting, crear un registro **A**, nombre `vdnt`, apuntando a la IP que indique el proveedor. No usar las IP de GitHub Pages. Si se usa Cloudflare, empezar con DNS only para validar SSL. Activar AutoSSL/Let's Encrypt y redirección de HTTP a HTTPS en el panel. Si hay proxy inverso, el proveedor debe hacer que PHP reciba `HTTPS=on` para las conexiones HTTPS; la aplicación no confía en cabeceras reenviadas por cualquier cliente.

## 2. Crear e importar la base

1. cPanel → Bases de datos MySQL: crear una base y un usuario propios para Vital Dent.
2. Asociar ese usuario a esa base. Para importar, usar el usuario administrador del panel; para la aplicación bastan SELECT, INSERT, UPDATE y DELETE sobre esta base.
3. phpMyAdmin → seleccionar la base → Importar → elegir **`database/schema.sql`**. La importación es aditiva y puede repetirse; no borra registros existentes.
4. En el selector PHP, habilitar `pdo_mysql` y sesiones. Configurar `post_max_size=34M`, `memory_limit=256M` y `max_allowed_packet` de MySQL de al menos 64 MB (pedir al soporte si el panel no lo permite).

Las tablas son:

- `vd_users`: usuario de la doctora, hash de contraseña y versión de sesiones.
- `vd_clinic`: documento JSON clínico con pacientes, citas, evoluciones, odontograma, tratamientos y cobros; revisión para evitar sobrescrituras simultáneas.
- `vd_attachments`: imágenes y PDF como BLOB privados, descargados bajo autenticación sólo cuando se solicitan. No hay carpeta pública de radiografías.
- `vd_auth_limits`: límites de intentos de inicio de sesión.
- `vd_remember_tokens`: hashes de los accesos recordados por 30 días. Se crea automáticamente al usar la opción; si el usuario MySQL carece de CREATE, importar `database/upgrade-2.1.sql` desde phpMyAdmin.

Los cambios clínicos se guardan de forma atómica en el documento central. Es una implementación para un consultorio pequeño; no es un esquema relacional de reportes ni un sistema multiusuario. Los adjuntos no se incluyen en la carga inicial y se almacenan por separado. Hay un límite de 10 MB por sesión y 32 MB por solicitud; los adjuntos nuevos de cada operación cuentan para ese límite. No se eliminan automáticamente adjuntos antiguos al restaurar un respaldo, para evitar pérdidas.

## 3. Configurar conexión privada

Copiar `server/config.example.php` como **`server/config.php`**, fuera de la carpeta pública. Completar allí:

```php
'app_url' => 'https://vdnt.bmimportexport.com.py',
'db_host' => 'localhost', // usar lo indicado por el hosting
'db_port' => 3306,
'db_name' => 'PREFIJO_vitaldent',
'db_user' => 'PREFIJO_vitaldent',
'db_password' => 'TU_CONTRASEÑA_REAL_DE_MYSQL',
'setup_token' => 'TU_CLAVE_ALEATORIA_PRIVADA_DE_AL_MENOS_32_CARACTERES',
```

No usar esas frases como contraseñas. Generar la clave de instalación con un gestor de contraseñas o con `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` en una terminal privada. No enviarla por chat ni subir `config.php` a GitHub; está excluido por `.gitignore`. Restringir sus permisos a la cuenta del hosting y al proceso PHP según las instrucciones del proveedor.

## 4. Crear el acceso

Abrir https://vdnt.bmimportexport.com.py/:

- Usuario: **karen.frutos**.
- Crear una contraseña de al menos **12 caracteres** y repetirla.
- Introducir la clave privada de instalación de `config.php`.

La creación inicial se bloquea una vez que existe la cuenta. Después, dejar `setup_token` vacío en `config.php`. Las contraseñas se guardan con `password_hash`, no en texto plano. Cambiar la contraseña desde Configuración invalida otras sesiones. Sin marcar la opción de recordar, las sesiones expiran tras 8 horas de inactividad. Con “Mantener sesión iniciada por 30 días”, un token aleatorio privado permite restaurar el acceso durante 30 días desde el ingreso, sin extender ese plazo con el uso. Los tokens se guardan como hashes en MySQL y se revocan al salir o cambiar la contraseña. Ambas opciones usan cookies HttpOnly/SameSite/Secure. El servidor verifica autorización y CSRF en cada escritura. Diez intentos de acceso por IP en 15 minutos; redes compartidas cuentan como una IP.

## 5. Usar en PC y iPhone

**PC:** abrir la URL en Chrome, Edge, Firefox o Safari. Chrome/Edge permiten instalar desde la barra de direcciones o Configuración → Instalar.

**iPhone:** abrir la URL en **Safari** → Compartir → **Agregar a pantalla de inicio** → Agregar. El icono y la interfaz están adaptados a pantallas táctiles. Ingresar con la misma cuenta. La sesión puede ser independiente de Safari al usar la PWA instalada.

Ambos acceden a la misma base centralizada. Pulsar **Actualizar datos** para traer cambios hechos desde otro dispositivo. Si hay una edición concurrente, se rechaza el guardado antiguo; conservar los datos del formulario, actualizar y repetir la operación. No hay sincronización automática en tiempo real.

La PWA puede mostrar su interfaz sin conexión, pero esta edición **requiere Internet para acceder a las historias y guardar cambios**. No guarda historias ni contraseñas en IndexedDB/localStorage ni cachea respuestas de la API. Una pérdida de conexión deja el formulario abierto con un aviso; no confirma un guardado que no se realizó. Los datos ya cargados pueden permanecer en memoria mientras la pestaña siga abierta; cerrar sesión en equipos compartidos.

## 6. Respaldo, migración y verificación

- Configuración → Exportar genera JSON con los adjuntos completos. Para migrar desde la versión local, exportar allí e importar aquí después de instalar. No se traslada automáticamente nada entre dominios.
- Restaurar reemplaza los registros de toda la base, conserva el usuario del servidor y exige confirmación. El importador admite archivos de hasta 30 MB; para bases mayores usar respaldo/restauración SQL del hosting.
- Programar un backup automático de **toda la base MySQL**, además del archivo privado de configuración. Guardar copias cifradas fuera del hosting. La exportación JSON no cifra datos clínicos.
- Probar con un paciente ficticio: crear desde PC, abrir desde iPhone, registrar una sesión y un abono, actualizar en PC y verificar saldo.
- Verificar que `/api/index.php?action=clinic` sin sesión devuelve 401 y que `https://vdnt.bmimportexport.com.py/server/config.php` no entrega archivos.
- Si se usó la versión anterior en este mismo dominio, cerrar todas las ventanas/PWA y abrir nuevamente para activar el Service Worker nuevo. El nuevo SW elimina cachés anteriores de Vital Dent; no borra datos de la versión antigua de IndexedDB. Exportarlos antes si existen y limpiar ese almacenamiento manualmente después de comprobar la migración.

Esta entrega incluye código y pruebas en un servidor de desarrollo con PHP/MariaDB; no configura tu DNS, SSL ni base remota sin acceso al hosting. La prueba en un iPhone físico/Safari debe realizarse tras instalar. El diseño clínico usa blanco, cyan y pastel con alertas diferenciadas.

## Actualizar una instalación existente a 2.1.2

Descargar `updates/vital-dent-actualizacion-2.1.2.zip`, subirlo y extraerlo dentro de **VDNT**, la carpeta que contiene `public/` y `server/`, aceptando reemplazar sólo los archivos incluidos. No extraer dentro de `public/`, no borrar carpetas y no reimportar el esquema completo. El ZIP excluye `server/config.php`, contraseñas y datos. Incluye permisos 755 para sus carpetas y 644 para sus archivos, evitando el 403 por archivos sin lectura pública.

La tabla de accesos recordados se crea automáticamente. Si aparece un error de permiso CREATE, seleccionar **bmimport_vdnt** e importar sólo `database/upgrade-2.1.sql`. Cerrar las ventanas/PWA y abrir nuevamente para activar el Service Worker actualizado; no hace falta borrar los datos del sitio. Ver también `ACTUALIZAR.txt`. El ZIP puede regenerarse con `python3 scripts/build-update.py` y tiene un checksum SHA-256 junto al archivo.

Si un dispositivo muestra “Origen no autorizado”, comprobar que abre `https://vdnt.bmimportexport.com.py/` directamente en Safari/Chrome y que `app_url` tiene ese dominio. La versión 2.1.1 normaliza la comparación y contempla Origin opaco de WebKit sólo con Referer autorizado y protección CSRF. No se aceptan otros dominios, HTTP en producción, puertos diferentes ni origen null sin evidencia de la dirección autorizada. Esta corrección no requiere cambios en la base clínica ni en credenciales.

Desde 2.1.2, ante un bloqueo de origen pulsar **Copiar diagnóstico** y enviar ese texto para identificar qué comparación falla. Los datos copiados están limitados a versiones, estado HTTP y dominios/categorías de las cabeceras. Nunca incluyen configuración privada, contraseñas, cookies o el CSRF. `server/validation.php` valida los datos clínicos y debe permanecer en su ubicación.
