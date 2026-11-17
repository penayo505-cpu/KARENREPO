# Vital Dent

PWA de gestión odontológica para la Dra. Karen Frutos. HTML semántico, JavaScript modular sin compilación y Tailwind vía CDN. `styles.css` incluye el diseño completo para funcionar incluso cuando el CDN no está disponible.

## Ejecutar

Desde `/workspace/KARENREPO` (o el directorio donde se descargó el proyecto):

```sh
python3 -m http.server 8080 --bind 0.0.0.0
```

Abrir el servidor en el navegador. Usar `http://localhost:8080` para desarrollo local; para otros dispositivos, desplegar con **HTTPS**. No abrir `index.html` mediante `file://`: los módulos, Web Crypto y Service Worker requieren un origen seguro.

Primera ejecución: usuario **karen.frutos**, crear contraseña o PIN de al menos 6 caracteres. No existe una contraseña predeterminada. La sesión persiste en localStorage hasta pulsar Salir. La contraseña se verifica con PBKDF2 SHA-256 (210 000 iteraciones y sal aleatoria) y se guarda como hash en IndexedDB.

## Funciones

- Dashboard con citas del día, pacientes activos, ingresos de lunes a hoy y procedimientos pendientes.
- Alta y edición de pacientes, búsqueda por nombre/cédula/teléfono, archivo lógico, alertas y antecedentes.
- Agenda con duración, prevención de superposición y estados de atención.
- Odontograma FDI permanente (32 piezas) y temporal (20), cinco caras y seis estados. Registra el estado actual; las evoluciones se documentan por separado.
- Sesiones con procedimientos, anestesia, materiales, notas, imágenes/PDF locales (10 MB por sesión) y enlaces HTTPS. Los adjuntos se descargan para abrirlos con el visor del dispositivo.
- Tratamientos en fases I, II y III con presupuesto y estados. Los abonos son por paciente, con saldo y crédito calculados automáticamente, en guaraníes (PYG).
- Caja, filtro diario y cuentas por cobrar.
- Exportación/restauración JSON con adjuntos y validación de estructura. Restaurar reemplaza la base clínica y conserva el acceso del dispositivo.
- Instalación PWA y uso offline tras la primera carga y activación del Service Worker. El caché sólo contiene archivos públicos de la aplicación.

## Desplegar

Subir `index.html`, `styles.css`, `app.js`, `manifest.json`, `sw.js` y `icons/` a cualquier hosting estático con HTTPS (GitHub Pages, Netlify, Cloudflare Pages, etc.). Sin build, backend ni variables secretas. Las rutas relativas permiten publicar en subdirectorios. Configurar `sw.js` con `Cache-Control: no-cache` si el proveedor lo permite y cambiar la versión de `CACHE` al actualizar los recursos. Las versiones nuevas esperan a que se cierren las ventanas anteriores.

El botón Instalar usa la invitación del navegador si está disponible; iOS requiere Compartir → Agregar a inicio. El CDN de Tailwind requiere conexión, pero el CSS propio mantiene la aplicación operativa offline.

## Datos y límites

La base IndexedDB pertenece al navegador y origen; no hay sincronización entre dispositivos. No borrar los datos del sitio sin un respaldo. Exportar copias periódicas y conservarlas en almacenamiento privado: incluyen información clínica **sin cifrar**. El acceso local es un bloqueo de interfaz, no autenticación de servidor ni cifrado de historias clínicas. Quien controle el dispositivo o las herramientas del navegador puede acceder a los datos. Usar un equipo con cifrado de disco, bloqueo de pantalla y acceso restringido. El almacenamiento disponible depende del navegador; errores de cuota se muestran sin reemplazar el último estado guardado. Para recuperar un PIN olvidado se necesita un respaldo y un perfil/origen nuevo; no hay recuperación remota.

No se incluyen pacientes ficticios ni datos clínicos precargados. No constituye un sistema de facturación fiscal ni una plataforma multiusuario.

## Comprobación manual

1. Crear acceso, ingresar un paciente y buscarlo por cédula.
2. Agendar una cita; intentar superponer otra y verificar el rechazo.
3. Abrir ficha → Odontograma y guardar una cara con caries en ambas denticiones.
4. Registrar evolución con un adjunto, tratamiento y un abono. Comprobar saldo, caja y dashboard.
5. Recargar y verificar persistencia; exportar/restaurar un respaldo.
6. Tras la primera activación del SW, desconectar Internet y recargar. Verificar navegación y escritura local.
