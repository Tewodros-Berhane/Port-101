<?php

namespace App\Modules\Reports;

use App\Core\Company\Models\Company;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use TCPDF;

class CompanyReportExportService
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
    public function export(
        Company $company,
        array $report,
        string $format,
    ): HttpResponse {
        $generated = $this->generate($company, $report, $format);

        return response($generated['content'], 200, [
            'Content-Type' => $generated['mime_type'],
            'Content-Disposition' => "attachment; filename=\"{$generated['file_name']}\"",
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
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     * @return array{disk: string, path: string, file_name: string, mime_type: string, file_size: int}
     */
    public function storeExport(
        Company $company,
        array $report,
        string $format,
        string $directory = 'report-exports',
        string $disk = 'local',
    ): array {
        $generated = $this->generate($company, $report, $format);
        $path = trim($directory, '/').'/'.$generated['file_name'];

        Storage::disk($disk)->put($path, $generated['content']);

        return [
            'disk' => $disk,
            'path' => $path,
            'file_name' => $generated['file_name'],
            'mime_type' => $generated['mime_type'],
            'file_size' => strlen($generated['content']),
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
    private function pdfContent(
        Company $company,
        array $report,
    ): string {
        $html = view('reports.company.table', [
            'company' => $company,
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

        return $pdf->Output('report.pdf', 'S');
    }

    /**
     * @param  array{
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     */
    private function excelContent(array $report): string
    {
        $tmpDirectory = storage_path('app/tmp');

        if (! is_dir($tmpDirectory)) {
            mkdir($tmpDirectory, 0777, true);
        }

        $tmpPath = $tmpDirectory.'/'.Str::uuid().'.xlsx';

        $writer = new Writer;
        $writer->openToFile($tmpPath);
        $writer->addRow(Row::fromValues([$report['title']]));
        $writer->addRow(Row::fromValues([$report['subtitle']]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($report['columns']));

        foreach ($report['rows'] as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $writer->close();

        $content = file_get_contents($tmpPath) ?: '';

        @unlink($tmpPath);

        return $content;
    }

    /**
     * @param  array{
     *  key: string,
     *  title: string,
     *  subtitle: string,
     *  columns: array<int, string>,
     *  rows: array<int, array<int, string|int|float>>
     * }  $report
     * @return array{file_name: string, mime_type: string, content: string}
     */
    private function generate(
        Company $company,
        array $report,
        string $format,
    ): array {
        $normalizedFormat = $this->normalizeFormat($format);
        $timestamp = now()->format('Ymd-His');
        $baseName = Str::slug($company->name.'-'.$report['title']).'-'.$timestamp;

        if ($normalizedFormat === 'xlsx') {
            return [
                'file_name' => $baseName.'.xlsx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'content' => $this->excelContent($report),
            ];
        }

        return [
            'file_name' => $baseName.'.pdf',
            'mime_type' => 'application/pdf',
            'content' => $this->pdfContent($company, $report),
        ];
    }
}
