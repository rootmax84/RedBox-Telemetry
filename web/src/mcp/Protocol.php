<?php
declare(strict_types=1);

require_once __DIR__ . '/../version.php';

/** Format MCP responses without coupling the protocol layer to the database. */
final class McpProtocol
{
    /** Cache lifetimes, in milliseconds, advertised by the modern MCP protocol. */
    private const CACHEABLE_TTL = [
        'tools/list' => 300000,
        'prompts/list' => 300000,
        'resources/list' => 300000,
        'resources/read' => 0,
    ];

    /** Use the application's canonical version for every MCP handshake. */
    public static function serverInfo(): array
    {
        return [
            'name' => 'redbox-telemetry',
            'version' => RATEL_VERSION . '-mcp',
        ];
    }

    public static function decorateResult(string $method, array|stdClass $result, bool $modern): array|stdClass
    {
        if (!$modern || $method === 'server/discover') {
            // Legacy ping must remain an object ({}) instead of an array ([]).
            return $result;
        }

        if ($result instanceof stdClass) {
            $result = (array)$result;
        }

        if (in_array($method, [
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'prompts/list',
            'prompts/get',
        ], true)) {
            $result = ['resultType' => 'complete'] + $result;
        }

        if (array_key_exists($method, self::CACHEABLE_TTL)) {
            $result['ttlMs'] = self::CACHEABLE_TTL[$method];
            $result['cacheScope'] = 'private';
        }

        $result['_meta']['io.modelcontextprotocol/serverInfo'] = self::serverInfo();

        return $result;
    }
}
