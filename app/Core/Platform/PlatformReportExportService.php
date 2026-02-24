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

        $attachment = $this->buildAttachmentPayload(
            report: $report,
            format: $normalizedFormat,
            filename: $baseName.'.'.$normalizedFormat
        );

        return response($attachment['content'], 200, [
            'Content-Type' => $attachment['mime'],
            'Content-Disposition' => "attachment; filename=\"{$attachment['filename']}\"",
        ]);
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
     * @return array{filename: string, mime: string, content: string}
     */
    public function buildAttachmentPayload(
        array $report,
        string $format,
        ?string $filename = null
    ): array {
        $normalizedFormat = $this->normalizeFormat($format);
        $resolvedFilename = $filename ?? (Str::slug($report['title']).'.'.$normalizedFormat);

        if ($normalizedFormat === 'xlsx') {
            return [
                'filename' => $resolvedFilename,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'content' => $this->excelBinary($report),
            ];
        }

        return [
            'filename' => $resolvedFilename,
            'mime' => 'application/pdf',
            'content' => $this->pdfBinary($report),
        ];
    }

    /**
     * @param  array{
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     */
    private function pdfBinary(array $report): string
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

        return (string) $pdf->Output('', 'S');
    }

    /**
     * @param  array{
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     */
    private function excelBinary(array $report): string
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

        $content = (string) file_get_contents($tmpPath);
        @unlink($tmpPath);

        return $content;
    }
}
