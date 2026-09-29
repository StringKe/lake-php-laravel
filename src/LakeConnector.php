<?php

namespace StringKe\LakePhp\Laravel;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use LogicException;
use Pdo\Lake;

/**
 * pdo_lake passes every DSN key it does not know to the server as a session setting, so `settings` travel in the DSN and survive reconnects.
 */
final class LakeConnector extends Connector implements ConnectorInterface
{
    private const array DSN_KEYS = ['host', 'port', 'protocol', 'sslmode', 'warehouse', 'tenant', 'connect_timeout'];

    /**
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): Lake
    {
        $pdo = $this->createConnection($this->dsn($config), $config, $this->getOptions($config));

        if (! $pdo instanceof Lake) {
            throw new LogicException('The lake connection did not return Pdo\Lake; make sure the pdo_lake extension is loaded.');
        }

        if (is_string($config['role'] ?? null) && $config['role'] !== '') {
            $pdo->useRole($config['role']);
        }

        return $pdo;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function dsn(array $config): string
    {
        $pairs = ['dbname' => $config['database'] ?? null];

        foreach (self::DSN_KEYS as $key) {
            $pairs[$key] = $config[$key] ?? null;
        }

        foreach ((array) ($config['settings'] ?? []) as $name => $value) {
            $pairs[(string) $name] = is_bool($value) ? (int) $value : $value;
        }

        $parts = [];

        foreach ($pairs as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (! is_scalar($value) || preg_match('/^[A-Za-z0-9_]+$/', $key) !== 1 || str_contains((string) $value, ';')) {
                throw new LogicException("The lake connection option [{$key}] cannot be written into the DSN.");
            }

            $parts[] = $key.'='.$value;
        }

        return 'lake:'.implode(';', $parts);
    }
}
