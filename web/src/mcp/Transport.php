<?php
declare(strict_types=1);

/** Invalid per-request MCP metadata, with the JSON-RPC code and HTTP status. */
final class McpProtocolViolation extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $rpcCode,
        public readonly int $httpStatus = 400,
        public readonly ?array $data = null
    ) {
        parent::__construct($message);
    }
}

/**
 * MCP Streamable HTTP protocol-version negotiation and 2026 request validation.
 * This class has no database dependencies so it can be regression-tested in CLI.
 */
final class McpTransport
{
    public const MODERN_VERSION = '2026-07-28';

    public const SUPPORTED_VERSIONS = [
        self::MODERN_VERSION,
        '2025-11-25',
        '2025-06-18',
        '2025-03-26',
    ];

    private const MODERN_METHODS = [
        'server/discover',
        'tools/list',
        'tools/call',
        'resources/list',
        'resources/templates/list',
        'resources/read',
        'prompts/list',
        'prompts/get',
        'completion/complete',
    ];

    public static function supportsModernMethod(string $method): bool
    {
        return in_array($method, self::MODERN_METHODS, true);
    }

    public static function negotiate(array $request, array $server): string
    {
        $params = $request['params'] ?? [];
        $method = (string)$request['method'];
        $meta = is_array($params['_meta'] ?? null) ? $params['_meta'] : [];
        $hasBodyVersion = array_key_exists('io.modelcontextprotocol/protocolVersion', $meta);
        $bodyVersion = $meta['io.modelcontextprotocol/protocolVersion'] ?? null;
        $hasModernCapabilities = array_key_exists('io.modelcontextprotocol/clientCapabilities', $meta);
        $headerVersion = trim((string)($server['HTTP_MCP_PROTOCOL_VERSION'] ?? ''));

        // Compare every explicitly supplied per-request protocol version, including
        // mixed-era requests. Never let a legacy header bypass modern metadata.
        if ($hasBodyVersion && (!is_string($bodyVersion) || $headerVersion !== $bodyVersion)) {
            throw new McpProtocolViolation('MCP protocol version header/body mismatch', -32020);
        }

        if ($hasModernCapabilities && $headerVersion !== self::MODERN_VERSION
            && ($headerVersion === '' || in_array($headerVersion, self::SUPPORTED_VERSIONS, true))) {
            throw new McpProtocolViolation('Modern client metadata requires modern protocol version', -32020);
        }

        if ($headerVersion === '') {
            // MCP 2025-03-26 predates the mandatory HTTP version header.
            // Do not silently downgrade a modern discover request.
            if ($method === 'server/discover' || $hasBodyVersion) {
                throw new McpProtocolViolation('Missing MCP-Protocol-Version header', -32020);
            }
            if ($method === 'initialize' && ($params['protocolVersion'] ?? null) === self::MODERN_VERSION) {
                throw new McpProtocolViolation(
                    'Unsupported protocol version', -32022, 400,
                    ['supported' => self::SUPPORTED_VERSIONS, 'requested' => self::MODERN_VERSION]
                );
            }
            return '2025-03-26';
        }

        if (!in_array($headerVersion, self::SUPPORTED_VERSIONS, true)) {
            throw new McpProtocolViolation(
                'Unsupported protocol version',
                -32022,
                400,
                ['supported' => self::SUPPORTED_VERSIONS, 'requested' => $headerVersion]
            );
        }

        if ($headerVersion === self::MODERN_VERSION) {
            self::validateModern($request, $server, $meta, $bodyVersion);
        }

        return $headerVersion;
    }

    private static function validateModern(
        array $request,
        array $server,
        array $meta,
        mixed $bodyVersion
    ): void {
        if ($bodyVersion !== self::MODERN_VERSION) {
            throw new McpProtocolViolation('MCP protocol version header/body mismatch', -32020);
        }

        if (!isset($meta['io.modelcontextprotocol/clientCapabilities'])
            || !is_array($meta['io.modelcontextprotocol/clientCapabilities'])
        ) {
            throw new McpProtocolViolation('Missing client capabilities in _meta', -32602);
        }

        $method = (string)$request['method'];

        if (($server['HTTP_MCP_METHOD'] ?? null) !== $method) {
            throw new McpProtocolViolation('Missing or mismatched Mcp-Method header', -32020);
        }

        $params = $request['params'] ?? [];
        $nameField = match ($method) {
            'tools/call', 'prompts/get' => 'name',
            'resources/read' => 'uri',
            default => null,
        };

        if ($nameField === null) {
            return;
        }

        $expected = $params[$nameField] ?? null;
        $rawName = $server['HTTP_MCP_NAME'] ?? null;

        if (!is_string($expected) || !is_string($rawName)) {
            throw new McpProtocolViolation('Mcp-Name header or body value missing', -32020);
        }

        $actual = self::decodeHeaderValue($rawName);

        if ($actual !== $expected) {
            throw new McpProtocolViolation('Mcp-Name header/body mismatch', -32020);
        }
    }

    /**
     * Validate browser Origin against the public MCP origin (not just hostname).
     * OAuth deployments already configure a canonical public issuer. For manual
     * Bearer installs, the HTTP Host must be restricted by the web server.
     */
    public static function originAllowed(
        string $origin,
        array $server,
        bool $trustForwardedProto = false,
        string $publicIssuer = ''
    ): bool {
        if ($origin === '') {
            // Server-to-server MCP requests usually have no Origin header.
            return true;
        }

        $actual = self::parseOrigin($origin);
        if ($actual === null) {
            return false;
        }

        if ($publicIssuer !== '') {
            $expected = self::parseOrigin(rtrim($publicIssuer, '/'));
            return $expected !== null && $actual === $expected;
        }

        $rawHost = (string)($server['HTTP_HOST'] ?? '');
        if ($rawHost === '' || strpbrk($rawHost, "/@?#\\ \t\r\n") !== false) {
            return false;
        }
        $https = (!empty($server['HTTPS']) && $server['HTTPS'] !== 'off')
            || ($trustForwardedProto && ($server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $expected = self::parseOrigin(($https ? 'https://' : 'http://') . $rawHost);
        return $expected !== null && $actual === $expected;
    }

    /** Strictly parse an HTTP Origin; default ports are equivalent. */
    private static function parseOrigin(string $value): ?array
    {
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
        ) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }
        // Reject authorities parse_url accepts but browsers never emit as Origins.
        if (!preg_match('/^https?:\/\/(?:\[[0-9a-fA-F:.]+\]|[a-zA-Z0-9.-]+)(?::[0-9]{1,5})?$/iD', $value)) {
            return null;
        }
        if ($host[0] === '[' && !filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return null;
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return null;
        }
        return [$scheme, $host, $port];
    }

    /** Decode the official =?base64?…?= sentinel for non-ASCII/unsafe values. */
    private static function decodeHeaderValue(string $value): string
    {
        if (str_starts_with($value, '=?base64?') && str_ends_with($value, '?=')) {
            $encoded = substr($value, strlen('=?base64?'), -strlen('?='));
            $decoded = base64_decode($encoded, true);

            if ($decoded === false || preg_match('//u', $decoded) !== 1) {
                throw new McpProtocolViolation('Invalid Mcp-Name header encoding', -32020);
            }

            return $decoded;
        }

        if (preg_match('/[^\x20-\x7e]/', $value) || trim($value) !== $value) {
            throw new McpProtocolViolation('Unsafe Mcp-Name header', -32020);
        }

        return $value;
    }
}
