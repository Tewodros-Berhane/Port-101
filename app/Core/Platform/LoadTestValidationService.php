<?php

namespace App\Core\Platform;

use Illuminate\Support\Facades\File;

class LoadTestValidationService
{
    /**
     * @return array<string, mixed>
     */
    public function configSummary(): array
    {
        return [
            'summary_output_dir' => (string) config('core.performance.load_test_output_dir'),
            'signoff_output_dir' => (string) config('core.performance.load_signoff_output_dir'),
            'max_failed_rate' => (float) config('core.performance.load_thresholds.max_failed_rate', 0.02),
            'max_p95_ms' => (float) config('core.performance.load_thresholds.max_p95_ms', 1500),
            'endpoint_success_rates' => (array) config('core.performance.load_thresholds.endpoint_success_rates', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function run(string $summaryFile, bool $writeArtifact = false): array
    {
        $summaryState = $this->readSummary($summaryFile);
        $checks = [
            $this->summaryFileCheck($summaryState),
        ];

        if ($summaryState['ok']) {
            $summary = $summaryState['summary'];
            $failedRate = $this->metricValue($summary, 'http_req_failed', 'rate');
            $p95Duration = $this->metricValue($summary, 'http_req_duration', 'p(95)');

            $checks[] = $this->thresholdCheck(
                'http_req_failed',
                'HTTP request failure rate',
                $failedRate,
                '<=',
                (float) config('core.performance.load_thresholds.max_failed_rate', 0.02),
                fn (float $actual, float $threshold) => $actual <= $threshold,
            );

            $checks[] = $this->thresholdCheck(
                'http_req_duration_p95',
                'HTTP request duration p95',
                $p95Duration,
                '<=',
                (float) config('core.performance.load_thresholds.max_p95_ms', 1500),
                fn (float $actual, float $threshold) => $actual <= $threshold,
                'ms',
            );

            foreach ((array) config('core.performance.load_thresholds.endpoint_success_rates', []) as $metric => $threshold) {
                $checks[] = $this->thresholdCheck(
                    $metric,
                    ucfirst(str_replace('_', ' ', $metric)),
                    $this->metricValue($summary, $metric, 'rate'),
                    '>=',
                    (float) $threshold,
                    fn (float $actual, float $expected) => $actual >= $expected,
                );
            }
        }

        $result = [
            'ok' => collect($checks)->every(fn (array $check) => (bool) ($check['ok'] ?? false)),
            'generated_at' => now()->toIso8601String(),
            'summary_file' => $summaryFile,
            'checks' => $checks,
            'summary' => $summaryState['summary'] ?? null,
            'config' => $this->configSummary(),
            'artifact_path' => null,
        ];

        if ($writeArtifact && $result['ok']) {
            $result['artifact_path'] = $this->writeArtifact($result);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSummary(string $summaryFile): array
    {
        if (! File::exists($summaryFile)) {
            return [
                'ok' => false,
                'detail' => "Summary file [{$summaryFile}] does not exist.",
                'summary' => null,
            ];
        }

        $decoded = json_decode((string) File::get($summaryFile), true);

        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'detail' => "Summary file [{$summaryFile}] is not valid JSON.",
                'summary' => null,
            ];
        }

        if (! is_array($decoded['metrics'] ?? null)) {
            return [
                'ok' => false,
                'detail' => "Summary file [{$summaryFile}] does not contain k6 metrics.",
                'summary' => null,
            ];
        }

        return [
            'ok' => true,
            'detail' => "Summary file [{$summaryFile}] parsed successfully.",
            'summary' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $summaryState
     * @return array<string, mixed>
     */
    private function summaryFileCheck(array $summaryState): array
    {
        return [
            'key' => 'summary_file',
            'label' => 'Load summary file',
            'ok' => (bool) ($summaryState['ok'] ?? false),
            'detail' => (string) ($summaryState['detail'] ?? 'Summary file validation failed.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function metricValue(array $summary, string $metric, string $valueKey): ?float
    {
        $values = data_get($summary, "metrics.{$metric}.values");

        if (! is_array($values)) {
            return null;
        }

        $candidates = [
            $valueKey,
            str_replace(['(', ')'], '', $valueKey),
            strtolower($valueKey),
            strtolower(str_replace(['(', ')'], '', $valueKey)),
        ];

        foreach ($candidates as $candidate) {
            $raw = $values[$candidate] ?? null;

            if (is_numeric($raw)) {
                return (float) $raw;
            }
        }

        return null;
    }

    /**
     * @param  callable(float, float): bool  $comparator
     * @return array<string, mixed>
     */
    private function thresholdCheck(
        string $key,
        string $label,
        ?float $actual,
        string $operator,
        float $threshold,
        callable $comparator,
        string $unit = '',
    ): array {
        if ($actual === null) {
            return [
                'key' => $key,
                'label' => $label,
                'ok' => false,
                'detail' => 'Metric is missing from the k6 summary.',
            ];
        }

        $ok = $comparator($actual, $threshold);
        $suffix = $unit !== '' ? " {$unit}" : '';

        return [
            'key' => $key,
            'label' => $label,
            'ok' => $ok,
            'detail' => sprintf(
                'Actual %.4f%s %s threshold %.4f%s',
                $actual,
                $suffix,
                $operator,
                $threshold,
                $suffix,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function writeArtifact(array $result): string
    {
        $outputDir = (string) config('core.performance.load_signoff_output_dir');
        File::ensureDirectoryExists($outputDir);

        $path = $outputDir.'/load-signoff-'.now()->format('Ymd-His').'.json';

        File::put(
            $path,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $path;
    }
}
