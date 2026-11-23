"""Prepare a code-only hosting update. Does not connect to or change hosting."""
import hashlib
from pathlib import Path
import shutil
import subprocess
import zipfile

root = Path(__file__).resolve().parent.parent
output = (root / '.tmp' / 'deployment-ready').resolve()
if not output.is_relative_to(root):
    raise SystemExit('Release directory must stay inside the project.')
output.mkdir(parents=True, exist_ok=True)

public = {'index.php','about.php','services.php','gallery.php','book.php','manage.php','contact.php','payment.php','payment-return.php'}
special = {'.htaccess','.user.ini','config/.htaccess','config/config.example.php','storage/.htaccess','storage/receipts/.htaccess','storage/private/.htaccess','database/.htaccess','scripts/.htaccess','scripts/sms-worker.php','database/migrate_security.php','database/migrate_uploaded_photos.php','database/migrate_therapist_videos.php'}
files = subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard'], cwd=root, text=True).splitlines()
selected = []
for name in sorted(set(files)):
    path = root / name
    runtime_php = name.startswith(('src/','templates/','admin/','api/')) and path.suffix == '.php'
    browser_assets = name.startswith(('assets/css/','assets/js/')) and path.suffix in {'.css','.js'}
    bundled_image = name.startswith('assets/images/') and not name.startswith(('assets/images/team/','assets/images/gallery/')) and path.suffix.lower() in {'.jpg','.jpeg','.png','.webp','.svg','.ico'}
    upload_rule = name in {'assets/images/team/.htaccess','assets/images/gallery/.htaccess','assets/videos/gallery/.htaccess','src/.htaccess','templates/.htaccess'}
    if name in public or name in special or runtime_php or browser_assets or bundled_image or upload_rule:
        if not path.is_file() or not path.resolve().is_relative_to(root):
            raise SystemExit('Unsafe or missing release file: ' + name)
        selected.append(name)

archive = output / 'website-update.zip'
if not archive.resolve().is_relative_to(output):
    raise SystemExit('Unsafe archive destination.')
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED) as bundle:
    for name in selected:
        bundle.write(root / name, name)

for source, target in [('migrate_security_hostinger.sql','01-security-update.sql'),('migrate_therapist_videos_hostinger.sql','02-therapist-videos.sql')]:
    destination = output / target
    if not destination.resolve().is_relative_to(output):
        raise SystemExit('Unsafe migration destination.')
    shutil.copyfile(root / 'database' / source, destination)

instructions = '''Deployment order

1. Export the live database and back up the live uploads, storage and configuration through your hosting account.
2. In phpMyAdmin, select the LIVE website database. Import 01-security-update.sql, then 02-therapist-videos.sql. These are additive, repeatable migrations; they preserve existing accounts, passwords, pictures and bookings.
3. Upload website-update.zip to the EXISTING website folder and extract it there. Overwrite matching code files, preserving every other file. Do not delete or replace the website folder first. Do not import a local database dump.
4. This archive contains no config/config.php, provider keys, uploaded gallery/team photos, receipts, private storage, tests or database dump. Keep all existing live files in those categories. The archive includes bundled stock images and upload protection rules.
5. Check the PHP version in hosting and select a current patched supported release (preferably 8.4 or newer). Keep a copy of the prior code for rollback. Enable HTTPS and confirm backups can be restored. Run the site's ordinary booking/admin checks before reopening changes to customers.
6. In Admin > SMS notifications, enter or retain the live provider API key, approved sender ID and enable sending. Check provider credit. Do not share the key in chat.
7. Add/verify a hosting cron job every minute that runs PHP with the ABSOLUTE live path to scripts/sms-worker.php. Use the PHP executable path shown by hosting. The Windows XAMPP path does not apply to Linux hosting. The worker is command-line only; an HTTP cron URL will not work.
8. Check that the worker last-run time refreshes. Provider acceptance and handset delivery still need an agreed test phone and test appointment. Bookings less than 24 hours ahead get only the confirmation, so they cannot prove the 24-hour reminder flow.

Preparing this package does not deploy it, configure cron, upgrade hosting PHP, enable SMS or send messages.
'''
(output / 'DEPLOYMENT.txt').write_text(instructions, encoding='utf-8')
with zipfile.ZipFile(archive) as bundle:
    names = bundle.namelist()
    forbidden = ['config/config.php','config/sms.local.php','config/paystack.local.php','database/schema.sql']
    if any(name in names for name in forbidden) or any(name.startswith(('tests/','storage/private/')) and not name.endswith('.htaccess') for name in names):
        raise SystemExit('Private data unexpectedly included in release.')
manifest = '\n'.join(hashlib.sha256((output / name).read_bytes()).hexdigest() + '  ' + name for name in ['website-update.zip','01-security-update.sql','02-therapist-videos.sql']) + '\n'
(output / 'SHA256SUMS.txt').write_text(manifest, encoding='ascii')
print('Prepared:', archive)
print('Runtime files:', len(selected))
print('Excluded: local credentials, customer data, uploaded pictures, receipts and database dumps.')
print('Deployment instructions:', output / 'DEPLOYMENT.txt')
