<?php
/**
 * =====================================================================
 * GOOGLE SHEETS SERVICE
 * =====================================================================
 * Helper class untuk membuat Google Spreadsheet baru via Google Sheets API v4.
 * Menggunakan Service Account untuk autentikasi server-to-server (tanpa login user).
 * =====================================================================
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/google_sheets.php';

class GoogleSheetsService
{
    private Google_Client $client;
    private Google_Service_Sheets $sheetsService;
    private Google_Service_Drive $driveService;

    public function __construct()
    {
        if (!file_exists(GOOGLE_SERVICE_ACCOUNT_JSON)) {
            throw new RuntimeException(
                'File service_account.json tidak ditemukan di config/. ' .
                'Silakan ikuti panduan setup di config/google_sheets.php'
            );
        }

        $this->client = new Google_Client();
        $this->client->setApplicationName(GOOGLE_APP_NAME);
        $this->client->setAuthConfig(GOOGLE_SERVICE_ACCOUNT_JSON);
        $this->client->addScope(Google_Service_Sheets::SPREADSHEETS);
        $this->client->addScope(Google_Service_Drive::DRIVE);

        $this->sheetsService = new Google_Service_Sheets($this->client);
        $this->driveService  = new Google_Service_Drive($this->client);
    }

    /**
     * Buat spreadsheet baru dengan judul dan data yang diberikan.
     *
     * @param string   $title   Judul spreadsheet
     * @param string[] $headers Array nama kolom header
     * @param array[]  $rows    Array of array berisi data baris
     * @param string   $sheetName Nama sheet tab (default: Sheet1)
     * @return string URL spreadsheet yang baru dibuat
     */
    public function createSpreadsheet(
        string $title,
        array $headers,
        array $rows,
        string $sheetName = 'Sheet1'
    ): string {
        // 1. Dapatkan / Buat file spreadsheet
        $spreadsheetId = null;
        $folderId = defined('GOOGLE_DRIVE_FOLDER_ID') ? trim(GOOGLE_DRIVE_FOLDER_ID) : '';

        // Coba cari file Google Spreadsheet yang ada di dalam folder yang dibagikan
        if (!empty($folderId)) {
            try {
                $res = $this->driveService->files->listFiles([
                    'q' => "'{$folderId}' in parents and mimeType = 'application/vnd.google-apps.spreadsheet' and trashed = false",
                    'fields' => 'files(id, name)'
                ]);
                $files = $res->getFiles();
                if (!empty($files)) {
                    $spreadsheetId = $files[0]->id;
                    // Update nama file spreadsheet sesuai judul rekap yang baru
                    $this->driveService->files->update($spreadsheetId, new Google_Service_Drive_DriveFile(['name' => $title]));
                    
                    // Bersihkan isi sheet lama sebelum diisi data baru
                    try {
                        $this->sheetsService->spreadsheets_values->clear($spreadsheetId, 'Sheet1!A1:Z500', new Google_Service_Sheets_ClearValuesRequest());
                    } catch (Exception $ce) {}
                }
            } catch (Exception $e) {
                // Abaikan error pencarian
            }
        }

        // Jika belum ada file spreadsheet di folder, coba buat file baru
        if (!$spreadsheetId) {
            try {
                $fileMetadata = [
                    'name' => $title,
                    'mimeType' => 'application/vnd.google-apps.spreadsheet',
                ];
                if (!empty($folderId)) {
                    $fileMetadata['parents'] = [$folderId];
                }

                $driveFile = $this->driveService->files->create(
                    new Google_Service_Drive_DriveFile($fileMetadata),
                    ['fields' => 'id']
                );
                $spreadsheetId = $driveFile->id;
            } catch (Google_Service_Exception $e) {
                if (strpos($e->getMessage(), 'storageQuotaExceeded') !== false || $e->getCode() == 403) {
                    throw new RuntimeException(
                        "Folder 'Rekap nilai' sudah berhasil terhubung! Langkah terakhir: Buka folder 'Rekap nilai' di Google Drive Anda, buat 1 file 'Google Spreadsheet' baru di dalam folder tersebut. Setelah itu, klik tombol 'Buka di Google Sheets' lagi."
                    );
                }
                throw $e;
            }
        }

        // 2. Susun semua data: baris judul, header, dan data nilai
        $allValues = [];

        // Baris 1: Judul dokumen (merge nanti via formatting)
        $allValues[] = [$title];

        // Baris 2: Header kolom
        $allValues[] = $headers;

        // Baris 3+: Data siswa
        foreach ($rows as $row) {
            $allValues[] = array_values($row);
        }

        // Baris terakhir: Rata-rata kelas (jika ada data)
        if (!empty($rows)) {
            $dataCount = count($rows);
            $startRow  = 3; // Baris data mulai dari baris ke-3 (1-indexed)
            $endRow    = $startRow + $dataCount - 1;
            $naCol     = self::columnLetter(count($headers) - 1); // Kolom Nilai Akhir (sebelum Catatan)
            $allValues[] = [
                '', '', 'Rata-rata Kelas',
                '', '', '', '', '',
                "=ROUND(AVERAGE({$naCol}{$startRow}:{$naCol}{$endRow}),1)",
                '',
            ];
        }

        // 3. Tulis semua data ke sheet
        $range = "{$sheetName}!A1";
        $body  = new Google_Service_Sheets_ValueRange(['values' => $allValues]);
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );

        // 4. Format spreadsheet (styling header, merge judul, warna)
        $this->applyFormatting($spreadsheetId, count($headers), count($rows));

        // 5. Jadikan spreadsheet dapat diakses oleh siapa saja yang punya link
        $this->makePublicReadable($spreadsheetId);

        return "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit";
    }

    /**
     * Terapkan formatting: merge judul, bold header, warna header hijau Excel
     */
    private function applyFormatting(string $spreadsheetId, int $colCount, int $dataCount): void
    {
        $sheetId = 0; // Sheet pertama selalu ID 0

        $requests = [];

        // --- Merge sel A1 sampai kolom terakhir (baris judul) ---
        $requests[] = new Google_Service_Sheets_Request([
            'mergeCells' => new Google_Service_Sheets_MergeCellsRequest([
                'range' => new Google_Service_Sheets_GridRange([
                    'sheetId'          => $sheetId,
                    'startRowIndex'    => 0,
                    'endRowIndex'      => 1,
                    'startColumnIndex' => 0,
                    'endColumnIndex'   => $colCount,
                ]),
                'mergeType' => 'MERGE_ALL',
            ]),
        ]);

        // --- Format baris judul (row 1): font besar, bold, hijau tua, center ---
        $requests[] = new Google_Service_Sheets_Request([
            'repeatCell' => new Google_Service_Sheets_RepeatCellRequest([
                'range' => new Google_Service_Sheets_GridRange([
                    'sheetId'          => $sheetId,
                    'startRowIndex'    => 0,
                    'endRowIndex'      => 1,
                    'startColumnIndex' => 0,
                    'endColumnIndex'   => $colCount,
                ]),
                'cell'   => new Google_Service_Sheets_CellData([
                    'userEnteredFormat' => new Google_Service_Sheets_CellFormat([
                        'backgroundColor' => $this->hexColor('137333'),
                        'textFormat'      => new Google_Service_Sheets_TextFormat([
                            'bold'            => true,
                            'fontSize'        => 13,
                            'foregroundColor' => $this->hexColor('FFFFFF'),
                        ]),
                        'horizontalAlignment' => 'CENTER',
                        'verticalAlignment'   => 'MIDDLE',
                    ]),
                ]),
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)',
            ]),
        ]);

        // --- Format baris header (row 2): bold, hijau, teks putih, center ---
        $requests[] = new Google_Service_Sheets_Request([
            'repeatCell' => new Google_Service_Sheets_RepeatCellRequest([
                'range' => new Google_Service_Sheets_GridRange([
                    'sheetId'          => $sheetId,
                    'startRowIndex'    => 1,
                    'endRowIndex'      => 2,
                    'startColumnIndex' => 0,
                    'endColumnIndex'   => $colCount,
                ]),
                'cell'   => new Google_Service_Sheets_CellData([
                    'userEnteredFormat' => new Google_Service_Sheets_CellFormat([
                        'backgroundColor' => $this->hexColor('107C41'),
                        'textFormat'      => new Google_Service_Sheets_TextFormat([
                            'bold'            => true,
                            'foregroundColor' => $this->hexColor('FFFFFF'),
                        ]),
                        'horizontalAlignment' => 'CENTER',
                        'verticalAlignment'   => 'MIDDLE',
                    ]),
                ]),
                'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)',
            ]),
        ]);

        // --- Border seluruh data (baris 2 sampai baris data + rata-rata) ---
        $totalRows = 2 + $dataCount + ($dataCount > 0 ? 1 : 0); // header + data + rata2
        $requests[] = new Google_Service_Sheets_Request([
            'updateBorders' => new Google_Service_Sheets_UpdateBordersRequest([
                'range' => new Google_Service_Sheets_GridRange([
                    'sheetId'          => $sheetId,
                    'startRowIndex'    => 1,
                    'endRowIndex'      => $totalRows,
                    'startColumnIndex' => 0,
                    'endColumnIndex'   => $colCount,
                ]),
                'top'    => $this->solidBorder(),
                'bottom' => $this->solidBorder(),
                'left'   => $this->solidBorder(),
                'right'  => $this->solidBorder(),
                'innerHorizontal' => $this->solidBorder('CCCCCC'),
                'innerVertical'   => $this->solidBorder('CCCCCC'),
            ]),
        ]);

        // --- Auto resize semua kolom ---
        $requests[] = new Google_Service_Sheets_Request([
            'autoResizeDimensions' => new Google_Service_Sheets_AutoResizeDimensionsRequest([
                'dimensions' => new Google_Service_Sheets_DimensionRange([
                    'sheetId'    => $sheetId,
                    'dimension'  => 'COLUMNS',
                    'startIndex' => 0,
                    'endIndex'   => $colCount,
                ]),
            ]),
        ]);

        // --- Tinggi baris judul = 40px ---
        $requests[] = new Google_Service_Sheets_Request([
            'updateDimensionProperties' => new Google_Service_Sheets_UpdateDimensionPropertiesRequest([
                'range' => new Google_Service_Sheets_DimensionRange([
                    'sheetId'    => $sheetId,
                    'dimension'  => 'ROWS',
                    'startIndex' => 0,
                    'endIndex'   => 1,
                ]),
                'properties' => new Google_Service_Sheets_DimensionProperties([
                    'pixelSize' => 40,
                ]),
                'fields' => 'pixelSize',
            ]),
        ]);

        // Kirim semua request formatting sekaligus
        $batchRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
            'requests' => $requests,
        ]);
        $this->sheetsService->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);
    }

    /**
     * Jadikan spreadsheet bisa dibaca oleh siapa saja yang punya link (link sharing)
     */
    private function makePublicReadable(string $spreadsheetId): void
    {
        $permission = new Google_Service_Drive_Permission([
            'type' => 'anyone',
            'role' => 'writer', // writer agar guru bisa edit langsung
        ]);
        $this->driveService->permissions->create($spreadsheetId, $permission);
    }

    /**
     * Konversi hex color ke format Google Sheets RGB object
     */
    private function hexColor(string $hex): Google_Service_Sheets_Color
    {
        $hex = ltrim($hex, '#');
        return new Google_Service_Sheets_Color([
            'red'   => hexdec(substr($hex, 0, 2)) / 255,
            'green' => hexdec(substr($hex, 2, 2)) / 255,
            'blue'  => hexdec(substr($hex, 4, 2)) / 255,
        ]);
    }

    /**
     * Buat objek border solid
     */
    private function solidBorder(string $hex = '000000'): Google_Service_Sheets_Border
    {
        return new Google_Service_Sheets_Border([
            'style' => 'SOLID',
            'color' => $this->hexColor($hex),
        ]);
    }

    /**
     * Konversi index kolom (0-based) ke huruf Excel (A, B, ..., Z, AA, ...)
     */
    private static function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index  = (int)($index / 26);
        }
        return $letter;
    }
}
