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
        'resources/read',
        'prompts/list',
        'prompts/get',
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
        $bodyVersion = $meta['io.modelcontextprotocol/protocolVersion'] ?? null;
        $headerVersion = trim((string)($server['HTTP_MCP_PROTOCOL_VERSION'] ?? ''));

        if ($headerVersion === '') {
            // Old clients without MCP-Protocol-Version may still initialize.
            // A modern client must not silently fall back when its header is missing.
            if ($bodyVersion !== null || $method === 'server/discover') {
                throw new McpProtocolViolation('Missing MCP-Protocol-Version header', -32020);
            }

            $requestedVersion = $method === 'initialize' ? ($params['protocolVersion'] ?? null) : null;
            return is_string($requestedVersion) && in_array($requestedVersion, self::SUPPORTED_VERSIONS, true)
                && $requestedVersion !== self::MODERN_VERSION
                ? $requestedVersion
                : '2025-11-25';
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
