# Vital Dent · Gestión odontológica

Aplicación web/PWA para **https://vdnt.bmimportexport.com.py/**, con base centralizada **MySQL/MariaDB y PHP 8.2+**. Interfaz clínica clara en cyan y tonos pastel, adaptable a PC y iPhone.

**Instalación del subdominio, SQL, conexión privada y primer acceso:** [INSTALACION.md](INSTALACION.md).

## Estructura

- `public/`: única carpeta expuesta por el hosting; interfaz, PWA y API.
- `server/`: configuración privada (usar la plantilla) y validación.
- `database/schema.sql`: base de datos importable desde phpMyAdmin.
- `tests/`: pruebas de integración con navegador, exclusivamente contra una base de prueba vacía.

Incluye pacientes e historia clínica, odontograma permanente/temporal por caras FDI, agenda con control de superposición, evoluciones con imágenes/PDF, planes por fases, presupuestos/abonos/saldos, caja y respaldo/restauración. Usuario único: `karen.frutos`; contraseña creada durante la instalación protegida por una clave privada. No hay contraseña predeterminada.

La API valida las sesiones, CSRF y datos, usa PDO preparado y evita sobreescrituras mediante revisión del documento clínico. Los adjuntos se guardan como BLOB privados independientes. Actualizar datos trae cambios desde otros dispositivos. No es una aplicación multiusuario ni tiene sincronización en tiempo real.

Esta versión sustituye el almacenamiento en el navegador por guardado en el servidor. No permite escrituras offline ni cachea historias clínicas. La PWA conserva la interfaz pública; para acceder a los datos necesita conexión. Tailwind se carga por CDN y el diseño completo tiene CSS propio.

## Desarrollo y pruebas

Crear una base MySQL **de prueba vacía**, importar `database/schema.sql`, copiar `server/config.example.php` a `server/config.php` y usar `app_url=http://localhost:8090`. Sólo para las pruebas, usar `setup_token=development-test-token-only-1234567890`. No usar esta clave en producción.

```sh
php -S localhost:8090 -t public
```

En otra terminal:

```sh
npm ci
npx playwright install chromium
npm test
```

Las pruebas usan una contraseña ficticia, crean registros, exportan/restauran y necesitan que no exista todavía una cuenta en la base de prueba. Nunca apuntarlas a producción. `CHROMIUM_PATH` permite usar Chromium ya instalado. No se requiere Node en el hosting de producción.

Validación realizada con PHP 8.4, MariaDB 11.8 y Chromium: instalación/acceso, pacientes, citas superpuestas, odontograma 32/20 piezas, evolución/adjuntos, abonos y saldos, persistencia, respaldo/restauración, dos contextos de navegador compartiendo MySQL, pantalla táctil de 390 px, rechazo de escrituras offline, conflictos, respuestas 401/403/422 y cambio de contraseña. No sustituye la comprobación final en Safari de un iPhone físico ni valida el hosting remoto.
