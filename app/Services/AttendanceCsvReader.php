<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AttendanceCsvReader
{
    public function read(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        try {
            $header = fgetcsv($handle);
            if ($header) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            }
            if ($header !== ['empid', 'timestamp', 'type']) {
                throw ValidationException::withMessages(['file' => 'CSV header must be: empid,timestamp,type']);
            }
            $logs = [];
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 3 || ! ctype_digit($row[0]) || ! in_array($row[2], ['0', '1', '4', '5'], true)) {
                    throw ValidationException::withMessages(['file' => 'Invalid CSV row '.(count($logs) + 2).'. Expected numeric employee ID and punch type 0, 1, 4 or 5.']);
                }
                $logs[] = ['id' => $row[0], 'timestamp' => $row[1], 'type' => $row[2]];
                if (count($logs) > 20000) {
                    throw ValidationException::withMessages(['file' => 'Upload at most 20,000 punches per file.']);
                }
            }
            if (! $logs) {
                throw ValidationException::withMessages(['file' => 'The CSV contains no attendance punches.']);
            }
        } finally {
            fclose($handle);
        }

        return $logs;
    }
}
