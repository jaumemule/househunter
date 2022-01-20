<?php

declare(strict_types=1);

namespace Pararius\Application\Bootstrap;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

class EscapingMysqlPasswordEnvVar implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv)
    {
        $env = $getEnv($name);

        return urlencode($env);
    }

    public static function getProvidedTypes()
    {
        return [
            'urlencode' => 'string',
        ];
    }
}
