<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Streams rows as a CSV download (UTF-8 with BOM so Excel opens it correctly). */
    protected function csv(string $filename, iterable $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($out, array_map(function ($cell) {
                    $cell = (string) $cell;

                    // Stop spreadsheet formula injection from user-entered text.
                    return ($cell !== '' && !is_numeric($cell) && in_array($cell[0], ['=', '+', '-', '@'], true)) ? "'" . $cell : $cell;
                }, $row));
            }

            fclose($out);
        }, $filename . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
