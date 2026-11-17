# Vital Dent · Gestión odontológica

Aplicación web/PWA para **https://vdnt.bmimportexport.com.py/**, con base centralizada **MySQL/MariaDB y PHP 8.2+**. Interfaz clínica clara en cyan y tonos pastel, adaptable a PC y iPhone.

**Instalación del subdominio, SQL, conexión privada y primer acceso:** [INSTALACION.md](INSTALACION.md).

## Estructura

- `public/`: única carpeta expuesta por el hosting; interfaz, PWA y API.
- `server/`: configuración privada (usar la plantilla) y validación.
- `database/schema.sql`: base de datos importable desde phpMyAdmin.
- `tests/`: pruebas de integración con navegador, exclusivamente contra una base de prueba vacía.

Incluye pacientes e historia clínica, odontograma permanente/temporal por caras FDI, agenda con control de superposición, evoluciones con imágenes/PDF, planes por fases, presupuestos/abonos/saldos, caja y respaldo/restauración. Usuario único: `karen.frutos`; contraseña creada durante la instalación protegida por una clave privada. No hay contraseña predeterminada.

El login incluye una opción voluntaria de sesión por 30 días mediante un token aleatorio HttpOnly almacenado como hash en MySQL. El acceso se revoca al salir y al cambiar la contraseña. La API valida las sesiones, CSRF y datos, usa PDO preparado y evita sobreescrituras mediante revisión del documento clínico. Los adjuntos se guardan como BLOB privados independientes. Actualizar datos trae cambios desde otros dispositivos. No es una aplicación multiusuario ni tiene sincronización en tiempo real.

Esta versión sustituye el almacenamiento en el navegador por guardado en el servidor. No permite escrituras offline ni cachea historias clínicas. La PWA conserva la interfaz pública; para acceder a los datos necesita conexión. Tailwind se carga por CDN y el diseño completo tiene CSS propio.

## Desarrollo y pruebas

Crear una base MySQL **de prueba vacía con nombre terminado en `_test`**, importar `database/schema.sql`, copiar `server/config.example.php` a `server/config.php` y usar `app_url=http://localhost:8090`. Sólo para las pruebas, usar `setup_token=development-test-token-only-1234567890`. No usar esta clave en producción.

```sh
php -S localhost:8090 -t public
```

En otra terminal:

```sh
npm ci
npx playwright install chromium
VITAL_DENT_TESTS=1 php tests/db-fixture.php reset
npm test
```

El reset es destructivo y se limita a un entorno localhost con base `_test`. Las pruebas usan una contraseña ficticia, crean registros, exportan/restauran y necesitan que no exista todavía una cuenta en la base de prueba. Nunca apuntarlas a producción. `CHROMIUM_PATH` permite usar Chromium ya instalado y `PHP_BIN` seleccionar el ejecutable PHP para las pruebas de expiración/revocación. No se requiere Node en el hosting de producción.

Validación realizada con PHP 8.4, MariaDB 11.8 y Chromium: instalación/acceso, pacientes, citas superpuestas, odontograma 32/20 piezas, evolución/adjuntos, abonos y saldos, persistencia, respaldo/restauración, dos contextos de navegador compartiendo MySQL, pantalla táctil de 390 px, rechazo de escrituras offline, conflictos, respuestas 401/403/422 y cambio de contraseña. No sustituye la comprobación final en Safari de un iPhone físico ni valida el hosting remoto.

## Actualización 2.1.3

[Descargar ZIP de actualización](updates/vital-dent-actualizacion-2.1.3.zip) y extraer directamente en **VDNT**, no en `public/`. Conserva `server/config.php` y los datos. [Pasos](ACTUALIZAR.txt). Incluye login limpio, logo de regreso a Inicio, botones con espera y protección contra envíos duplicados, menú táctil completo y acceso recordado opcional por 30 días.

Las pruebas adicionales verifican cookies persistentes/HttpOnly, almacenamiento del hash, restauración sin cookie de sesión PHP, plazo fijo, rechazo de tokens alterados o vencidos, revocación al salir/cambiar contraseña, navegación por el logo, mostrar/ocultar contraseña y envíos duplicados.

Corrección 2.1.1: normaliza el origen de `app_url` y las cabeceras del navegador (mayúsculas, puertos predeterminados y ruta configurada). Un Origin opaco de WebKit sólo se acepta con Referer del dominio autorizado, además del CSRF obligatorio en escrituras. No se infiere el dominio permitido desde Host ni se habilitan comodines/CORS. Los errores de origen indican la URL autorizada.

Versión 2.1.2: el error de origen tiene un título específico y permite copiar un diagnóstico con versiones, dominio abierto, dominio permitido y orígenes normalizados de las cabeceras. No contiene credenciales, cookies, CSRF, rutas, parámetros ni información clínica. El diagnóstico permite investigar el rechazo en el hosting; no deshabilita la validación de origen ni supone una causa todavía no observada.

Versión 2.1.3: el diagnóstico confirmó que los dispositivos que fallaban abrían el alias `www.vdnt.bmimportexport.com.py`. Apache redirige ese alias con 308 a `https://vdnt.bmimportexport.com.py/`; la aplicación también lo hace antes de consultar la API como respaldo. No se amplía la lista de dominios autorizados. Probado con Apache real y navegador, incluidos rutas, consultas y ausencia de bucles. Prueba opcional de Apache con un fixture en 127.0.0.1:8092: `python3 tests/apache-redirect.py`.
