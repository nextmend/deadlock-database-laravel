# Anatomi deadlock database: contoh Laravel & MySQL

Contoh yang bisa dijalankan untuk artikel [Anatomi deadlock database dan optimasi locking](https://nextmend.dev/artikel/anatomi-deadlock-database-dan-optimasi-locking/?utm_source=github&utm_medium=readme&utm_campaign=artikel-deadlock).

Sebuah backend e-commerce melayani checkout pesanan multi-item. Saat dua pembeli memesan produk yang sama pada detik yang bersamaan tetapi dalam urutan item yang bersilangan, transaksi mereka saling menunggu dan memicu deadlock di InnoDB MySQL (`1213 Deadlock found when trying to get lock; try restarting transaction`).

Repo ini mereproduksi deadlock tersebut di Laravel 13 dan MySQL 8.4, membedah anatominya dari `SHOW ENGINE INNODB STATUS`, lalu memperbaikinya dengan *deterministic lock ordering* dan batch locking.

## Isi

| File | Isi |
| --- | --- |
| `app/Http/Controllers/CheckoutController.php` | Controller checkout pesanan dan pengurangan stok dengan row-level lock |
| `database/migrations/` | Tabel `products`, `orders`, dan `order_items` |
| `routes/console.php` | Command `demo:deadlock`: mengirim request silang secara konkuren via `Http::pool()` |
| `tests/Feature/CheckoutTest.php` | Test checkout pesanan sekuensial dan verifikasi konsistensi urutan locking |

## Menjalankan

Butuh PHP 8.4+, Composer, dan MySQL 8.x (atau database kompatibel InnoDB).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

Test unit/feature:

```bash
php artisan test
```

### Demo reproduksi deadlock dengan request konkuren

Jalankan built-in server PHP dengan multiple worker (hanya di Linux/macOS):

```bash
PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload
```

Di terminal lain, jalankan simulasi request silang paralel:

```bash
php artisan demo:deadlock --pairs=10
```

Command ini mengirimkan 10 pasang request simultan (Request A memesan Produk 1 lalu 2; Request B memesan Produk 2 lalu 1). Pada kondisi bug, MySQL langsung mendeteksi siklus deadlock dan menggagalkan salah satu transaksi dengan error 1213.

Untuk melihat rincian siklus lock yang terjadi di MySQL:

```sql
SHOW ENGINE INNODB STATUS\G
```

Periksa bagian `LATEST DETECTED DEADLOCK`.

## Langkah demi langkah

Setiap tahap memiliki git tag:

| Tag | Isi | `php artisan test` | `demo:deadlock` |
| --- | --- | --- | --- |
| `1-bug` | Lock baris satu per satu mengikuti urutan input request | hijau (test sekuensial) | mayoritas request silang deadlock (1213) |
| `2-test-merah` | Test disiplin urutan lock (assert lock order deterministik) | merah (gagal karena lock acak) | mayoritas request silang deadlock (1213) |
| `3-perbaikan` | Urutkan ID produk secara deterministik, batch lock, dan retry | hijau | 0 deadlock (semua transaksi 200 OK) |

Lisensi MIT. Dibuat oleh [Nextmend](https://nextmend.dev/?utm_source=github&utm_medium=readme&utm_campaign=artikel-deadlock).
