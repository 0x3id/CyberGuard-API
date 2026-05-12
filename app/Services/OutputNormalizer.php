<?php

namespace App\Services;

use App\Models\Finding;
use Illuminate\Support\Str;

class OutputNormalizer
{
    public function normalize(string $rawOutput, string $driverId, string $targetId, string $scanJobId): array
    {
        $driverConfig = config("scanners.drivers.{$driverId}");
        $format = $driverConfig['output_format'] ?? 'json';
        
        $findings = [];
        
        // Parse raw output based on format
        $parsedData = [];
        if ($format === 'json') {
            // First try to parse the entire output as a single JSON object/array
            $decoded = json_decode($rawOutput, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // If it's a single associative array with a 'findings' key (like our custom python scanner)
                if (is_array($decoded) && isset($decoded['findings'])) {
                    $parsedData = $decoded['findings'];
                }
                // If it's a list of findings
                elseif (isset($decoded[0]) && is_array($decoded[0])) {
                    $parsedData = $decoded;
                }
                // Single object
                else {
                    $parsedData[] = $decoded;
                }
            } else {
                // Fallback to JSON Lines (e.g. native subfinder output)
                $lines = explode("\n", trim($rawOutput));
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $decodedLine = json_decode($line, true);
                    if ($decodedLine) {
                        $parsedData[] = $decodedLine;
                    }
                }
            }
        } elseif ($format === 'xml') {
            // Simplified XML parsing mock for nmap
            // In a real scenario, use simplexml_load_string or similar
            $parsedData[] = [
                'host' => [
                    'ports' => [
                        'port' => [
                            'portid' => '80',
                            'protocol' => 'tcp',
                            'service' => ['name' => 'http']
                        ]
                    ]
                ]
            ];
        }

        // Map parsed data to Finding schema
        $fieldMap = $driverConfig['field_map'] ?? [];

        foreach ($parsedData as $data) {
            // Skip top-level error JSON objects from tools
            if (isset($data['error']) && count($data) === 1) {
                continue;
            }

            $finding = new Finding();
            $finding->scan_job_id = $scanJobId;
            $finding->target_id = $targetId;
            $finding->driver_id = $driverId;
            $finding->raw_data = json_encode($data);
            $finding->status = 'open';

            // Apply field mapping
            $metadata = [];
            foreach ($fieldMap as $findingField => $mappingDef) {
                $value = $this->applyMapping($mappingDef, $data);
                
                if (str_starts_with($findingField, 'metadata.')) {
                    $metaKey = str_replace('metadata.', '', $findingField);
                    $metadata[$metaKey] = $value;
                } else {
                    $finding->{$findingField} = $value;
                }
            }
            
            // Safety fallback for database required fields
            if (empty($finding->title)) {
                $finding->title = 'Untitled Finding (' . $driverId . ')';
            }
            if (empty($finding->severity)) {
                $finding->severity = 'info';
            }
            if (empty($finding->description)) {
                $finding->description = 'No description provided by the scanner.';
            }

            $finding->metadata = $metadata;
            $finding->save();
            $findings[] = $finding;
        }

        return $findings;
    }

    private function applyMapping(string $mappingDef, array $data)
    {
        if (str_starts_with($mappingDef, "static: '")) {
            return trim(str_replace("static: '", '', $mappingDef), "'");
        }

        if (str_starts_with($mappingDef, "path: ")) {
            $path = trim(str_replace('path: ', '', $mappingDef));
            return data_get($data, $path);
        }

        if (str_starts_with($mappingDef, "template: '")) {
            $template = trim(str_replace("template: '", '', $mappingDef), "'");
            return preg_replace_callback('/\{\{([^\}]+)\}\}/', function ($matches) use ($data) {
                return data_get($data, $matches[1], '');
            }, $template);
        }

        return null;
    }
}
