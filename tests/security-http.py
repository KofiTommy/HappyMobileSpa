"""Run local HTTP security checks against an isolated, temporary SQLite database.

Usage: python tests/security-http.py [path/to/php]
Does not contact payment/SMS services or use the installation's database.
"""
from contextlib import closing
import base64
import http.cookiejar
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parent.parent
PHP = sys.argv[1] if len(sys.argv) > 1 else 'C:/xampp/php/php.exe'
if (ROOT / 'config/config.php').exists():
    raise SystemExit('Use an isolated checkout without config/config.php so the test cannot use a real database.')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def client():
    return urllib.request.build_opener(NoRedirect, urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(opener, path, data=None, headers=None):
    if isinstance(data, dict):
        data = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(base + path, data=data, headers=headers or {})
    try:
        response = opener.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.headers, response.read().decode('utf-8', errors='replace')


def csrf(body):
    return re.search(r'name="csrf" value="([a-f0-9]{64})"', body)[1]


passed = 0


def check(condition, label):
    global passed
    if not condition:
        raise AssertionError(label)
    passed += 1
    print('PASS', label)


with tempfile.TemporaryDirectory(prefix='spa-security-') as temporary:
    db = Path(temporary) / 'test.sqlite'
    date = subprocess.check_output([PHP, 'tests/security-http-fixture.php', str(db)], cwd=ROOT, text=True).strip()
    env = os.environ.copy()
    env.update(SPA_DB_DSN='sqlite:' + str(db), SPA_SMS_DISABLED='1', SPA_PAYSTACK_DISABLED='1', SPA_ENV='development')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    with open(Path(temporary) / 'server.log', 'w') as log:
        server = subprocess.Popen([PHP, '-d', 'display_errors=0', '-d', 'display_startup_errors=0', '-d', 'session.upload_progress.enabled=0', '-d', 'session.save_path="' + temporary + '"', '-d', 'upload_tmp_dir="' + temporary + '"', '-S', f'127.0.0.1:{port}', 'router.php'], cwd=ROOT, env=env, stdout=log, stderr=log)
        upload_path = None
        try:
            guest = client()
            for _ in range(100):
                try:
                    request(guest, '/admin/login.php')
                    break
                except OSError:
                    time.sleep(0.05)
            for path in ['/.git/HEAD','/config/config.example.php','/database/seed.php','/tests/run.php','/src/Auth.php','/scripts/sms-worker.php','/storage/receipts/test.pdf','/templates/admin/dashboard.php','/%2egit/HEAD','/assets/../database/schema.sql','/assets/images/gallery/exploit.php','/server.mjs']:
                check(request(guest, path)[0] == 404, 'private path denied: ' + path)
            for path in ['/admin/index.php','/admin/bookings.php','/admin/finance.php','/admin/gallery.php','/admin/expense-receipt.php?id=1','/admin/finance-export.php']:
                check(request(guest, path)[0] == 302, 'anonymous access denied: ' + path)
            status, headers, body = request(guest, '/book.php', headers={'Cookie':'PHPSESSID=' + 'a' * 32})
            check(status == 200, 'booking page loads')
            cookie = headers.get('Set-Cookie', '')
            check('HttpOnly' in cookie and 'SameSite=Lax' in cookie, 'protected session cookie')
            check('PHPSESSID=' + 'a' * 32 not in cookie, 'unissued session ID rejected')
            check("script-src 'self'" in headers.get('Content-Security-Policy', ''), 'script execution restricted by CSP')
            check('no-store' in headers.get('Cache-Control',''), 'personal pages not cached')
            for path in ['/book.php?service[]=x','/manage.php?reference[]=x','/api/availability.php?date[]=x']:
                check(request(guest, path)[0] == 400, 'malformed query rejected: ' + path)
            check(request(guest, '/contact.php', {'csrf[]':'x'})[0] == 400, 'array CSRF token rejected')
            check(request(guest, '/book.php?confirmation=SPA-MANAGE01')[0] == 403, 'confirmation requires owning session')
            check(request(guest, '/payment.php?booking=SPA-EXISTING')[0] == 302, 'checkout requires owning session')
            check(request(guest, '/api/paystack-webhook.php', b'{}', {'Content-Type':'application/json'})[0] == 401, 'unsigned webhook denied')
            status, _, body = request(guest, '/api/paystack-webhook.php', b'x' * 1048577, {'Content-Type':'application/json'})
            check(status == 413, f'oversized webhook denied (HTTP {status}: {body[:100]})')
            admin = client()
            _, headers, body = request(admin, '/admin/login.php')
            initial_token = csrf(body)
            check(request(admin, '/admin/login.php', {'username':'audit-owner','password':'isolated test password','csrf':'wrong'})[0] == 200, 'invalid CSRF cannot sign in')
            check(request(admin, '/admin/index.php')[0] == 302, 'invalid CSRF leaves user anonymous')
            status, headers, login_body = request(admin, '/admin/login.php', {'username':'audit-owner','password':'isolated test password','csrf':initial_token})
            check(status == 302 and 'Set-Cookie' in headers, f'login rotates session ID (HTTP {status}; expired={"Your session expired" in login_body}; incorrect={"incorrect" in login_body})')
            status, _, body = request(admin, '/admin/gallery.php')
            token = csrf(body)
            check(status == 200 and token != initial_token, 'login rotates CSRF token')
            boundary = 'spa-security-upload-boundary'
            parts = []
            for key, value in {'csrf':token,'action':'save','caption':'Security test','alt_text':'Isolated upload','published':'1','show_in_hero':'1','display_order':'0'}.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            image = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=')
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="test.php.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + image + b'\r\n')
            parts.append(f'--{boundary}--\r\n'.encode())
            check(request(admin, '/admin/gallery.php', b''.join(parts), {'Content-Type':'multipart/form-data; boundary=' + boundary})[0] == 302, 'validated multipart image upload succeeds')
            with closing(sqlite3.connect(db)) as connection, connection:
                image_id, upload_path = connection.execute('SELECT id,path FROM gallery_images').fetchone()
            check(request(guest, '/' + upload_path)[0] == 200, 'validated image is publicly readable')
            (ROOT / upload_path).unlink()
            check(request(guest, '/gallery.php')[0] == 200 and (ROOT / upload_path).read_bytes() == image, 'deleted upload restored from database')
            check(request(admin, '/admin/gallery.php', {'csrf':'invalid','action':'delete','id':image_id})[0] == 200, 'invalid CSRF cannot delete image')
            with closing(sqlite3.connect(db)) as connection, connection:
                check(connection.execute('SELECT COUNT(*) FROM gallery_images').fetchone()[0] == 1, 'CSRF rejection preserves image record')
            check(request(admin, '/admin/gallery.php', {'csrf':token,'action':'delete','id':image_id})[0] == 302, 'authorized deletion succeeds')
            check(not (ROOT / upload_path).exists(), 'authorized deletion removes image and backup')
            reception = client()
            token_r = csrf(request(reception, '/admin/login.php')[2])
            request(reception, '/admin/login.php', {'csrf':token_r,'username':'audit-reception','password':'isolated test password'})
            for path in ['/admin/finance.php','/admin/gallery.php','/admin/staff.php','/admin/settings.php','/admin/expense-receipt.php?id=1']:
                status, _, body = request(reception, path)
                check(status == 403 and 'Stack trace' not in body, 'receptionist access denied: ' + path)
            with closing(sqlite3.connect(db)) as connection, connection:
                connection.execute("UPDATE admin_users SET password_hash='revoked' WHERE username='audit-owner'")
            check(request(admin, '/admin/index.php')[0] == 302, 'password reset revokes existing browser session')
        finally:
            server.terminate()
            server.wait(timeout=10)
            if sys.exc_info()[0] is not None:
                print((Path(temporary) / 'server.log').read_text(errors='replace')[-3000:], file=sys.stderr)
            if upload_path and (ROOT / upload_path).exists():
                (ROOT / upload_path).unlink()
print(f'{passed} HTTP security checks passed; isolated test database removed.')
