<?php

namespace App\Core\Platform;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use TCPDF;

class PlatformReportExportService
{
    /**
     * @param  array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     */
    public function export(array $report, string $format): HttpResponse
    {
        $normalizedFormat = $this->normalizeFormat($format);
        $timestamp = now()->format('Ymd-His');
        $baseName = Str::slug($report['title']).'-'.$timestamp;

        if ($normalizedFormat === 'xlsx') {
            return $this->excelResponse($report, $baseName.'.xlsx');
        }

        return $this->pdfResponse($report, $baseName.'.pdf');
    }

    public function normalizeFormat(string $format): string
    {
        $normalized = strtolower(trim($format));

        if (! in_array($normalized, ['pdf', 'xlsx'], true)) {
            return 'pdf';
        }

        return $normalized;
    }

    /**
     * @param  array{
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     */
    private function pdfResponse(array $report, string $filename): HttpResponse
    {
        $html = view('reports.platform.table', [
            'report' => $report,
            'generatedAt' => now()->toDateTimeString(),
        ])->render();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Port-101');
        $pdf->SetAuthor('Port-101');
        $pdf->SetTitle($report['title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        $content = $pdf->Output($filename, 'S');

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * @param  array{
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     */
    private function excelResponse(array $report, string $filename): HttpResponse
    {
        $tmpDirectory = storage_path('app/tmp');
        if (! is_dir($tmpDirectory)) {
            mkdir($tmpDirectory, 0777, true);
        }

        $tmpPath = $tmpDirectory.'/'.Str::uuid().'.xlsx';

        $writer = new Writer();
        $writer->openToFile($tmpPath);
        $writer->addRow(Row::fromValues([$report['title']]));
        $writer->addRow(Row::fromValues([$report['subtitle']]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($report['columns']));

        foreach ($report['rows'] as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $writer->close();

        return response()
            ->download(
                $tmpPath,
                $filename,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }
}
