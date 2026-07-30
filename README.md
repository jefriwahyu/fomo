# Fullstack Developer

Repositori ini berisi solusi untuk 2 task: **Online Store API** dan **Hidden Item Game**.

**API sudah publicly accessible di:**
`https://fomo-three-bice.vercel.app/api/api`

> Catatan: prefix `/api` muncul dua kali karena konfigurasi rewrite di Vercel. Contoh: `GET /api/api/products`.

---

## Requirement

- PHP 8.3, Laravel 12
- Composer
- PostgreSQL

## Instalasi (Lokal)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Isi kredensial database PostgreSQL di `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fomo_store
DB_USERNAME=postgres
DB_PASSWORD=
```

```bash
php artisan migrate
php artisan serve
```

---

## Task 1: Online Store API

### Endpoints

| Method | Endpoint            | Deskripsi                    |
|--------|----------------------|-------------------------------|
| GET    | `/api/products`      | List semua produk             |
| GET    | `/api/products/{id}` | Detail produk                 |
| POST   | `/api/products`      | Buat produk baru               |
| POST   | `/api/orders`        | Buat order baru                |
| GET    | `/api/orders/{id}`   | Detail order                   |

Contoh body `POST /api/orders`:
```json
{
    "items": [
        { "product_id": 1, "quantity": 2 }
    ]
}
```

### Race Condition Handling

Implementasi ada di `app/Http/Controllers/Api/OrderController.php`. Setiap pembuatan
order dibungkus dalam `DB::transaction()`, dan row produk dikunci dengan
`lockForUpdate()` sebelum stok dibaca dan dikurangi. Pengecekan stok dilakukan
**setelah** lock didapat, sehingga tidak ada dua request yang bisa membaca nilai
stok yang sama secara bersamaan (mencegah stale read dan overselling).

### Menjalankan Functional Test (Race Condition)

Pastikan server berjalan (`php artisan serve`), lalu:

```bash
php artisan race:test-curl --stock=5 --requests=20
```

Command ini men-generate satu produk uji dengan stok terbatas, lalu mengirim
N request pembelian secara bersamaan (menggunakan `curl_multi`) ke endpoint
yang sama. Di akhir, ia memverifikasi bahwa:
- Jumlah order yang berhasil **tidak melebihi** stok awal
- Stok akhir di database **tidak pernah negatif**

Contoh output:
```
Successful orders : 5
Failed orders     : 15
Final stock in DB : 0
PASS: No overselling occurred.
```

---

## Task 2: Hidden Item Game

Program CLI yang mensimulasikan pencarian item tersembunyi dalam grid dengan
obstacle. Pemain bergerak sesuai urutan tetap: Utara → Timur → Selatan.

### Menjalankan

```bash
php artisan game:hidden-item {utara} {timur} {selatan}
```

Contoh:
```bash
php artisan game:hidden-item 2 3 1
```

### Cara Kerja & Asumsi

Grid didefinisikan di dalam `app/Console/Commands/HiddenItemGame.php`. Karena
soal menyebut "item tersembunyi di salah satu titik jalur bebas" (bentuk jamak
dari kemungkinan lokasi), program ini menganggap **setiap titik jalur bebas
yang dilalui pemain** selama pergerakan (bukan cuma titik akhir) sebagai
kandidat lokasi item. Jika pemain terhalang obstacle sebelum menyelesaikan
semua langkah yang diminta pada satu arah, pergerakan di fase itu berhenti
lebih awal dan program mencatatnya, lalu tetap melanjutkan ke fase berikutnya
dari posisi terakhir yang berhasil dicapai.

Output program menampilkan:
1. Ringkasan langkah yang diminta vs. yang berhasil dicapai per arah
2. Daftar koordinat kandidat lokasi item
3. Visualisasi grid dengan simbol `$` pada titik-titik tersebut (bonus)

---

## Struktur Proyek Singkat

```
app/Console/Commands/
  ├── HiddenItemGame.php          # Task 2
  ├── TestRaceConditionCurl.php   # Functional test race condition (Task 1)
app/Http/Controllers/Api/
  ├── ProductController.php
  ├── OrderController.php         # Berisi logic locking
app/Models/
  ├── Product.php
  ├── Order.php
  ├── OrderItem.php
```
