# Deployment

This app is deployed as a systemd-managed Whisp SSH server.

Repo evidence:

- `systemd/whisp.service` runs `/usr/bin/php8.4 whisp-server.php 22`.
- The service runs as user/group `whisp`.
- The service working directory is `/home/whisp`.
- Public app SSH traffic is served on `whisp.fyi:22`.
- The local SSH alias `elec` points at `elec@46.101.72.14:2222`, which appears to be the droplet admin SSH port.

## Update

Commit and push the desired changes to `main`, then SSH into the droplet admin port:

```bash
ssh elec
```

Update the checkout and dependencies:

```bash
cd /home/whisp
git fetch origin
git status --short
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader

cd /home/whisp/apps
composer install --no-dev --prefer-dist --optimize-autoloader
```

For post-quantum key exchange, Whisp must be able to load a libcrypto that exposes `ML-KEM-768`. OpenSSL 3.5+ provides this. If the system OpenSSL is older, install a compatible libcrypto and set `WHISP_LIBCRYPTO` to its path in the systemd environment.

Check the runtime capability before restarting:

```bash
cd /home/whisp
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
ssh -vv -o KexAlgorithms=mlkem768x25519-sha256 howdy-dood@whisp.fyi
```

The debug output should include:

```text
kex: algorithm: mlkem768x25519-sha256
```
