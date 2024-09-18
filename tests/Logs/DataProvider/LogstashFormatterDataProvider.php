<?php
/**
 * @copyright
 */

namespace App\Tests\Logs\DataProvider;

/**
 * @author Razvan Alin Munteanu <arazvan@elconfidencial.com>
 */
class LogstashFormatterDataProvider
{
    public static function formatData(): \Generator
    {
        $datetime = '2022-06-24T08:52:18+00:00';
        $applicationName = 'Weather-service';
        $environment = 'dev';

        yield [
            $applicationName,
            'dev',
            [
                'datetime' => $datetime,
            ],
            [
                '@timestamp' => $datetime,
                '@version' => 1,
                'app_name' => $applicationName,
                'app_image' => 'unknown',
                'environment' => strtoupper($environment),
                'hostname' => (string) gethostname(),
                'node_name' => 'unknown',
                'pod_ip' => 'unknown',
                'pod_environment' => 'unknown',
            ],
        ];

        $applicationImage = 'docker_weather-service';
        $nodeName = 'node_name_weather-service';
        $podIp = '127.0.0.1';
        $podEnvironment = 'pod-dev';
        $message = 'message';
        $channel = 'channel';
        $levelName = 'DEBUG';
        $level = 100;
        $extra = 'extra-data';
        $context = 'context-data';

        yield [
            $applicationName,
            'dev',
            [
                'datetime' => $datetime,
                'message' => $message,
                'channel' => $channel,
                'level_name' => $levelName,
                'level' => $level,
                'extra' => $extra,
                'context' => $context,
            ],
            [
                '@timestamp' => $datetime,
                '@version' => 1,
                'app_name' => $applicationName,
                'app_image' => $applicationImage,
                'environment' => strtoupper($environment),
                'hostname' => (string) gethostname(),
                'node_name' => $nodeName,
                'pod_ip' => $podIp,
                'pod_environment' => $podEnvironment,
                'message' => $message,
                'channel' => $channel,
                'level' => $levelName,
                'monolog_level' => $level,
                'extra' => $extra,
                'context' => $context,
            ],
            $applicationImage,
            $nodeName,
            $podIp,
            $podEnvironment,
        ];

        $datetime = gmdate('c');

        yield [
            $applicationName,
            'dev',
            [
                'message' => $message,
                'channel' => $channel,
                'level_name' => $levelName,
                'level' => $level,
                'extra' => $extra,
                'context' => $context,
            ],
            [
                '@timestamp' => $datetime,
                '@version' => 1,
                'app_name' => $applicationName,
                'app_image' => $applicationImage,
                'environment' => strtoupper($environment),
                'hostname' => (string) gethostname(),
                'node_name' => $nodeName,
                'pod_ip' => $podIp,
                'pod_environment' => $podEnvironment,
                'message' => $message,
                'channel' => $channel,
                'level' => $levelName,
                'monolog_level' => $level,
                'extra' => $extra,
                'context' => $context,
            ],
            $applicationImage,
            $nodeName,
            $podIp,
            $podEnvironment,
        ];
    }
}
