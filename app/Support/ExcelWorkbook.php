<?php

namespace App\Support;

use Illuminate\Http\Response;

class ExcelWorkbook
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows, string $sheet = 'Sheet1'): Response
    {
        return response(self::document($headers, $rows, $sheet), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public static function document(array $headers, iterable $rows, string $sheet = 'Sheet1'): string
    {
        $sheetName = self::escape(mb_substr(preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', $sheet) ?: 'Sheet1', 0, 31));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header"><Font ss:Bold="1"/><Interior ss:Color="#003366" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF" ss:Bold="1"/></Style>';
        $xml .= '</Styles>';
        $xml .= '<Worksheet ss:Name="'.$sheetName.'"><Table>';

        $xml .= '<Row>';
        foreach ($headers as $header) {
            $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">'.self::escape((string) $header).'</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $row) {
            $xml .= '<Row>';
            foreach ($row as $value) {
                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && ! str_starts_with($value, '0'))) {
                    $xml .= '<Cell><Data ss:Type="Number">'.self::escape((string) $value).'</Data></Cell>';
                } else {
                    $xml .= '<Cell><Data ss:Type="String">'.self::escape((string) ($value ?? '')).'</Data></Cell>';
                }
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        return $xml;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
