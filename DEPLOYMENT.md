# Deployment

Use this guide to deploy the example apps on your own systemd-managed Whisp SSH server. Replace `your-server` with your host and adjust the admin username, ports, user/group, and checkout paths for your installation.

Repo evidence:

- `systemd/whisp.service` runs `/usr/bin/php8.4 whisp-server.php 22`.
- The service listens for app SSH traffic on port 22.
- Keep your admin SSH access on a separate port; the commands below use port 2222.

Example installation paths:

- The service runs as user/group `elec`.
- The service working directory is `/home/elec/whisp.fyi`.
- Updating the service unit and restarting the service require sudo access.

## Update

Commit and push the desired changes to `main`, then SSH into your server's admin port:

```bash
ssh admin@your-server -p2222
```

Update the checkout and dependencies:

```bash
cd /home/elec/whisp.fyi
git fetch origin
git status --short
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader

cd /home/elec/whisp.fyi/apps
composer install --no-dev --prefer-dist --optimize-autoloader
```

For post-quantum key exchange, Whisp must be able to load a libcrypto that exposes `ML-KEM-768`. OpenSSL 3.5+ provides this. Ubuntu 22.04 currently ships OpenSSL 3.0, so production uses an app-local OpenSSL build at `runtime/openssl`. `whisp-server.php` automatically sets `WHISP_LIBCRYPTO` to `runtime/openssl/lib64/libcrypto.so.3` when that file exists.

Check the runtime capability before restarting:

```bash
cd /home/elec/whisp.fyi
php8.4 -r 'require "vendor/autoload.php"; var_export(Whisp\Crypto\MlKem768OpenSsl::isAvailable()); echo PHP_EOL;'
```

This must print `true`. If it prints `false`, Whisp will fall back to `curve25519-sha256` and will not advertise `mlkem768x25519-sha256`.

Restart and inspect the service:

```bash
sudo systemctl daemon-reload
sudo systemctl restart whisp
sudo systemctl status whisp --no-pager
sudo journalctl -u whisp -n 100 --no-pager
```

Verify from a workstation with a client that supports ML-KEM:

```bash
ssh -vv -o KexAlgorithms=mlkem768x25519-sha256 howdy-dood@your-server
```

The debug output should include:

```text
kex: algorithm: mlkem768x25519-sha256
```
