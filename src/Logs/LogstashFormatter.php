<?php
/**
 * @copyright
 */

namespace App\Logs;

use Monolog\Formatter\NormalizerFormatter;

/**
 * @author Walter Carvallo Alandia <wcarvallo@elconfidencial.com>
 */
class LogstashFormatter extends NormalizerFormatter
{
    private const DEFAULT_VALUE = 'unknown';

    protected string $applicationName;
    private string $environment;
    private ?string $applicationImage;
    private ?string $nodeName;
    private ?string $podId;

    public function __construct(
        string $applicationName,
        string $environment,
        ?string $applicationImage = null,
        ?string $nodeName = null,
        ?string $podId = null
    ) {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->applicationName = $this->normalizeApplicationName($applicationName);
        $this->environment = $environment;

        $this->applicationImage = $applicationImage;
        $this->nodeName = $nodeName;
        $this->podId = $podId;
    }

    public function format(array $record): string
    {
        $recordNormalized = parent::format($record);

        if (empty($recordNormalized['datetime'])) {
            $recordNormalized['datetime'] = gmdate('c');
        }

        $message = [
            '@timestamp' => $recordNormalized['datetime'],
            '@version' => 1,
            'app_name' => $this->applicationName,
            'app_image' => $this->applicationImage ?? self::DEFAULT_VALUE,
            'environment' => strtoupper($this->environment),
            'hostname' => (string) gethostname(),
            'node_name' => $this->nodeName ?? self::DEFAULT_VALUE,
            'pod_ip' => $this->podId ?? self::DEFAULT_VALUE,
        ];

        if (isset($recordNormalized['message'])) {
            $message['message'] = $recordNormalized['message'];
        }

        if (isset($recordNormalized['channel'])) {
            $message['channel'] = $recordNormalized['channel'];
        }

        if (isset($recordNormalized['level_name'])) {
            $message['level'] = $recordNormalized['level_name'];
        }

        if (isset($recordNormalized['level'])) {
            $message['monolog_level'] = $recordNormalized['level'];
        }

        if (!empty($recordNormalized['extra'])) {
            $message['extra'] = $recordNormalized['extra'];
        }

        if (!empty($recordNormalized['context'])) {
            $message['context'] = $recordNormalized['context'];
        }

        return $this->toJson($message)."\n";
    }

    private function normalizeApplicationName(string $applicationName): string
    {
        return ucfirst(strtolower($applicationName));
    }
}
