<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class TestRaceConditionCurl extends Command
{
    protected $signature = 'race:test-curl {--stock=5} {--requests=20}';
    protected $description = 'Simulate a flash sale burst using curl_multi to test race condition handling';

    public function handle()
    {
        $initialStock = (int) $this->option('stock');
        $totalRequests = (int) $this->option('requests');
        $baseUrl = config('app.url', 'http://127.0.0.1:8000');

        // 1. Setup: buat/reset produk flash sale dengan stok terbatas
        $product = Product::updateOrCreate(
            ['name' => 'Race Condition Test Product'],
            [
                'price' => 100000,
                'flash_sale_price' => 50000,
                'is_flash_sale' => true,
                'stock' => $initialStock,
            ]
        );

        $this->info("Product ID: {$product->id} | Initial stock: {$initialStock}");
        $this->info("Firing {$totalRequests} concurrent orders (1 qty each)...");

        // 2. Siapkan curl_multi untuk N request bersamaan
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        for ($i = 0; $i < $totalRequests; $i++) {
            $ch = curl_init("{$baseUrl}/api/orders");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[] = $ch;
        }

        // 3. Eksekusi semua request secara paralel
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        // 4. Kumpulkan hasil tiap request
        $successCount = 0;
        $failCount = 0;

        foreach ($curlHandles as $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpCode === 201) {
                $successCount++;
            } else {
                $failCount++;
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        // 5. Verifikasi hasil akhir
        $product->refresh();
        $finalStock = $product->stock;

        $this->newLine();
        $this->info("=== RESULT ===");
        $this->info("Successful orders : {$successCount}");
        $this->info("Failed orders     : {$failCount}");
        $this->info("Final stock in DB : {$finalStock}");

        $isValid = $finalStock >= 0 && $successCount === $initialStock;

        if ($isValid) {
            $this->info("✅ PASS: No overselling occurred. Stock never went negative.");
            return self::SUCCESS;
        }

        $this->error("❌ FAIL: Race condition detected! Stock went negative or oversold.");
        return self::FAILURE;
    }
}