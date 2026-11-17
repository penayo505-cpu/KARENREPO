"""Build a deterministic, credential-free incremental update for extraction in VDNT."""
from pathlib import Path
import hashlib
import stat
import zipfile

root = Path(__file__).resolve().parents[1]
files = [
    'public/.htaccess', 'public/routing.js', 'public/app.js', 'public/styles.css', 'public/sw.js', 'public/api/index.php',
    'server/remember.php', 'server/origin.php', 'server/validation.php', 'database/upgrade-2.1.sql', 'ACTUALIZAR.txt',
]
target = root / 'updates/vital-dent-actualizacion-2.1.3.zip'
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in ['public/', 'public/api/', 'server/', 'database/']:
        item = zipfile.ZipInfo(path, date_time=(2026, 11, 17, 12, 0, 0))
        item.create_system = 3
        item.external_attr = (stat.S_IFDIR | 0o755) << 16 | 0x10
        archive.writestr(item, b'')
    for path in files:
        assert 'config.php' not in path and (root / path).is_file()
        item = zipfile.ZipInfo(path, date_time=(2026, 11, 17, 12, 0, 0))
        item.create_system = 3
        item.external_attr = (stat.S_IFREG | 0o644) << 16
        archive.writestr(item, (root / path).read_bytes(), compress_type=zipfile.ZIP_DEFLATED)
checksum = hashlib.sha256(target.read_bytes()).hexdigest()
target.with_suffix('.sha256').write_text(f'{checksum}  {target.name}\n')
print(f'{target.name}: {target.stat().st_size} bytes; SHA-256 {checksum}')
