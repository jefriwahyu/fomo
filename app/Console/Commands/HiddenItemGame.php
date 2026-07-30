<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HiddenItemGame extends Command
{
    protected $signature = 'game:hidden-item {north} {east} {south}';
    protected $description = 'Simulasi pencarian item tersembunyi: bergerak Utara A langkah, Timur B langkah, Selatan C langkah';

    /**
     * Layout grid: # = obstacle (penghalang), . = jalur bebas, X = posisi awal pemain.
     */
    protected array $grid = [
        "########",
        "#......#",
        "#.###..#",
        "#...#.##",
        "#X#....#",
        "########",
    ];

    public function handle()
    {
        $north = (int) $this->argument('north');
        $east = (int) $this->argument('east');
        $south = (int) $this->argument('south');

        $grid = array_map('str_split', $this->grid);
        $start = $this->findStart($grid);

        if (!$start) {
            $this->error('Posisi awal (X) tidak ditemukan di grid.');
            return self::FAILURE;
        }

        [$row, $col] = $start;
        $path = []; // semua titik jalur bebas yang dilalui pemain

        [$row, $col, $achievedNorth] = $this->move($grid, $row, $col, -1, 0, $north, $path);
        [$row, $col, $achievedEast]  = $this->move($grid, $row, $col, 0, 1, $east, $path);
        [$row, $col, $achievedSouth] = $this->move($grid, $row, $col, 1, 0, $south, $path);

        $this->info('Ringkasan pergerakan:');
        $this->line("  Utara   diminta: {$north}, tercapai: {$achievedNorth}");
        $this->line("  Timur   diminta: {$east}, tercapai: {$achievedEast}");
        $this->line("  Selatan diminta: {$south}, tercapai: {$achievedSouth}");
        $this->line("  Posisi akhir    : (baris {$row}, kolom {$col})");

        if ($achievedNorth < $north || $achievedEast < $east || $achievedSouth < $south) {
            $this->warn('Catatan: pergerakan terhalang obstacle sebelum semua langkah yang diminta selesai di salah satu arah.');
        }

        $this->newLine();
        $this->info('Kemungkinan lokasi item (semua titik jalur bebas yang dilalui):');
        foreach ($path as $point) {
            $this->line("  - (baris {$point[0]}, kolom {$point[1]})");
        }

        $this->newLine();
        $this->displayGridWithMarkers($grid, $path);

        return self::SUCCESS;
    }

    protected function findStart(array $grid): ?array
    {
        foreach ($grid as $r => $rowChars) {
            foreach ($rowChars as $c => $char) {
                if ($char === 'X') {
                    return [$r, $c];
                }
            }
        }

        return null;
    }

    /**
     * Bergerak selangkah demi selangkah ke satu arah. Berhenti lebih awal kalau
     * sel berikutnya adalah obstacle atau di luar batas grid. Setiap sel valid
     * yang dilalui ditambahkan ke $path (by reference) sebagai kandidat lokasi item.
     */
    protected function move(array $grid, int $row, int $col, int $dRow, int $dCol, int $steps, array &$path): array
    {
        $achieved = 0;

        for ($i = 0; $i < $steps; $i++) {
            $nextRow = $row + $dRow;
            $nextCol = $col + $dCol;

            if (!$this->isWalkable($grid, $nextRow, $nextCol)) {
                break; // terhalang — hentikan fase ini, sisa langkah tidak dilanjutkan
            }

            $row = $nextRow;
            $col = $nextCol;
            $achieved++;
            $path[] = [$row, $col];
        }

        return [$row, $col, $achieved];
    }

    protected function isWalkable(array $grid, int $row, int $col): bool
    {
        return isset($grid[$row][$col]) && $grid[$row][$col] !== '#';
    }

    protected function displayGridWithMarkers(array $grid, array $path): void
    {
        $markers = [];
        foreach ($path as [$r, $c]) {
            $markers["{$r},{$c}"] = true;
        }

        $this->info('Grid dengan kemungkinan lokasi item ($):');
        foreach ($grid as $r => $rowChars) {
            $line = '';
            foreach ($rowChars as $c => $char) {
                $line .= isset($markers["{$r},{$c}"]) ? '$' : $char;
            }
            $this->line($line);
        }
    }
}