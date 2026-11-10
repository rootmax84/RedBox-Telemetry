<?php
declare(strict_types=1);

/**
 * Built-in, read-only OAuth 2.1 authorization server using allowlisted CIMD clients.
 * Opaque credentials are SHA-256 hashed in MariaDB; legacy rbxmcp_* tokens remain separate.
 * No arbitrary client metadata URLs: only explicitly allowlisted CIMD documents are trusted.
 */
final class McpOAuth
{
    private const ACCESS_SECONDS = 1800;

    /** Reject repeated/array form or query values instead of casting them into strings. */
    private static function parameter(array $input, string $key, string $fallback = ''): string
    {
        $value = $input[$key] ?? $fallback;
        if (!is_string($value)) {
            throw new InvalidArgumentException('invalid_request');
        }
        return $value;
    }

    public static function issuer(): string
    {
        $issuer = rtrim((string)($GLOBALS['mcp_oauth_issuer'] ?? ''), '/');
        $parts = parse_url($issuer);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || (isset($parts['user']) || isset($parts['pass']))
            || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('~[^a-zA-Z0-9.\-:]~', (string)$parts['host'])) {
            throw new RuntimeException('Configure mcp_oauth_issuer as your public HTTPS origin');
        }
        return $issuer;
    }

    public static function resource(): string
    {
        return self::issuer() . '/mcp';
    }

    public static function resourceMetadata(): array
    {
        return [
            'resource' => self::resource(),
            'authorization_servers' => [self::issuer()],
            'scopes_supported' => McpSecurity::SCOPES,
            'bearer_methods_supported' => ['header'],
        ];
    }

    public static function serverMetadata(): array
    {
        return [
            'issuer' => self::issuer(),
            'authorization_endpoint' => self::issuer() . '/oauth/authorize',
            'token_endpoint' => self::issuer() . '/oauth/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'client_id_metadata_document_supported' => true,
            'authorization_response_iss_parameter_supported' => true,
            'scopes_supported' => McpSecurity::SCOPES,
        ];
    }

    public static function challenge(string $error = 'invalid_token'): string
    {
        return 'Bearer realm="RedBox MCP", resource_metadata="' . self::issuer()
            . '/.well-known/oauth-protected-resource", error="' . $error . '"';
    }

    /**
     * Allowed client metadata URLs are configured in creds.php. Entries must be
     * exact HTTPS URLs; an optional `*` matches one alphanumeric URL path segment.
     * Defaults belong exclusively to creds.php.example, not this class.
     * A missing/empty/invalid setting fails closed (no clients allowed).
     */
    public static function isClientId(string $clientId): bool
    {
        $clients = $GLOBALS['mcp_oauth_allowed_clients'] ?? [];

        if (!is_array($clients) || !array_is_list($clients)) {
            return false;
        }

        foreach ($clients as $pattern) {
            if (!is_string($pattern) || !self::validClientUrl($pattern, true)) {
                continue;
            }

            $regex = preg_quote($pattern, '~');
            $regex = str_replace('\*', '[A-Za-z0-9_-]{1,128}', $regex);

            if (self::validClientUrl($clientId) && preg_match('~^' . $regex . '$~D', $clientId) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Limit outbound metadata fetches to explicitly approved HTTPS host/path pairs. */
    private static function validClientUrl(string $url, bool $allowWildcard = false): bool
    {
        if (strlen($url) > 2048 || !preg_match('~^https://([A-Za-z0-9.-]+)(/[A-Za-z0-9_./*-]+)$~D', $url, $matches)) {
            return false;
        }

        $host = strtolower($matches[1]);
        $labels = explode('.', $host);
        if (count($labels) < 2 || filter_var($host, FILTER_VALIDATE_IP) !== false
            || in_array(end($labels), ['local', 'localhost', 'internal', 'test', 'invalid'], true)) {
            return false;
        }

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63
                || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/D', $label) !== 1) {
                return false;
            }
        }

        $segments = explode('/', ltrim($matches[2], '/'));
        if ($segments === []) {
            return false;
        }

        $wildcards = 0;
        foreach ($segments as $segment) {
            if ($segment === '*' && $allowWildcard) {
                $wildcards++;
                continue;
            }
            if ($segment === '' || strlen($segment) > 128 || in_array($segment, ['.', '..'], true)
                || preg_match('/^[A-Za-z0-9_.-]+$/D', $segment) !== 1) {
                return false;
            }
        }

        return $wildcards <= 1;
    }

    /**
     * Redirects must be HTTPS, have no credentials/fragment and match the exact
     * redirect_uris entry from the allowlisted, fetched CIMD metadata document.
     * Redirect hosts need not be the metadata host (some clients use a separate
     * callback host), so there is no hard-coded provider-specific prefix.
     */
    private static function validRedirectUri(string $uri): bool
    {
        if (strlen($uri) > 2048 || preg_match('/[\x00-\x20\x7F]/', $uri) === 1
            || str_contains($uri, '\\')) {
            return false;
        }

        $parts = parse_url($uri);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || isset($parts['port'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || !preg_match('~^[A-Za-z0-9.-]+$~D', $host)
            || in_array(substr(strrchr('.' . $host, '.'), 1), ['local', 'localhost', 'internal', 'test', 'invalid'], true)) {
            return false;
        }

        return true;
    }

    /** Fetch one strictly allowlisted HTTPS endpoint, with TLS verification and redirects disabled. */
    public static function clientMetadata(string $clientId): array
    {
        if (!self::isClientId($clientId)) {
            throw new InvalidArgumentException('Unsupported OAuth client metadata URL');
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET', 'timeout' => 4, 'follow_location' => 0,
                'max_redirects' => 0, 'header' => "Accept: application/json\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $fp = @fopen($clientId, 'rb', false, $context);
        if ($fp === false) {
            throw new RuntimeException('OAuth client metadata unavailable');
        }
        try {
            $meta = stream_get_meta_data($fp);
            $headers = $meta['wrapper_data'] ?? [];
            if (!preg_match('~^HTTP/\S+ 200(?: |$)~', (string)($headers[0] ?? ''))) {
                throw new RuntimeException('OAuth client metadata request failed');
            }
            $json = stream_get_contents($fp, 16385);
        } finally {
            fclose($fp);
        }
        if (!is_string($json) || strlen($json) > 16384) {
            throw new InvalidArgumentException('Oversized client metadata');
        }
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['client_id'] ?? null) !== $clientId
            || !is_array($data['redirect_uris'] ?? null)
            || !in_array('code', $data['response_types'] ?? ['code'], true)) {
            throw new InvalidArgumentException('Invalid client metadata');
        }
        $methods = $data['token_endpoint_auth_methods_supported'] ?? [$data['token_endpoint_auth_method'] ?? ''];
        if (!is_array($methods) || !in_array('none', $methods, true)) {
            throw new InvalidArgumentException('Client does not support public PKCE exchange');
        }
        return $data;
    }

    public static function validateAuthorization(array $query, array $metadata): array
    {
        $client = self::parameter($query, 'client_id');
        $redirect = self::parameter($query, 'redirect_uri');
        $resource = self::parameter($query, 'resource');
        $challenge = self::parameter($query, 'code_challenge');
        $scopeString = self::parameter($query, 'scope', 'telemetry:read');
        $state = self::parameter($query, 'state');

        if (!self::isClientId($client) || ($metadata['client_id'] ?? null) !== $client
            || !in_array($redirect, $metadata['redirect_uris'], true)
            || !self::validRedirectUri($redirect)
            || $resource !== self::resource()
            || ($query['response_type'] ?? '') !== 'code'
            || ($query['code_challenge_method'] ?? '') !== 'S256'
            || !preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $challenge)
            || strlen($state) > 512 || strlen($scopeString) > 256) {
            throw new InvalidArgumentException('Invalid OAuth authorization request');
        }
        $scopes = preg_split('/\s+/', trim($scopeString));
        $scopes = McpSecurity::validScopes($scopes);
        return compact('client', 'redirect', 'resource', 'challenge', 'state', 'scopes');
    }

    public static function redirect(array $request, array $fields): never
    {
        // redirect_uri was verified against the trusted allowlisted CIMD document.
        $redirect = $request['redirect'];
        $fields['iss'] = self::issuer();
        if ($request['state'] !== '') {
            $fields['state'] = $request['state'];
        }
        header('Location: ' . $redirect . (str_contains($redirect, '?') ? '&' : '?')
            . http_build_query($fields, '', '&', PHP_QUERY_RFC3986), true, 302);
        exit;
    }

    public static function authorize(mysqli $db, int $uid, array $request, array $scopes): string
    {
        $scopes = McpSecurity::validScopes($scopes);
        if (array_diff($scopes, $request['scopes']) !== []) {
            throw new InvalidArgumentException('Requested scope exceeds client authorization');
        }
        $code = 'rbxc_' . bin2hex(random_bytes(32));
        $db->execute_query(
            'INSERT INTO mcp_oauth_codes (user_id,code_hash,client_id,redirect_uri,resource,scopes,code_challenge,expires_at) '
            . 'VALUES (?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))',
            [$uid, hash('sha256', $code), $request['client'], $request['redirect'], $request['resource'],
                implode(' ', $scopes), $request['challenge']]
        );
        return $code;
    }

    public static function validateVerifier(string $verifier): bool
    {
        return preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier) === 1;
    }

    public static function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Atomic one-time code consumption and OAuth token issuance. */
    public static function exchangeCode(mysqli $db, array $input): array
    {
        $code = self::parameter($input, 'code');
        $verifier = self::parameter($input, 'code_verifier');
        $client = self::parameter($input, 'client_id');
        $redirect = self::parameter($input, 'redirect_uri');
        $resource = self::parameter($input, 'resource');
        if (!preg_match('/^rbxc_[a-f0-9]{64}$/D', $code)
            || !self::validateVerifier($verifier) || !self::isClientId($client)
            || $resource !== self::resource()) {
            throw new InvalidArgumentException('invalid_grant');
        }
        $db->begin_transaction();
        try {
            $row = $db->execute_query(
                'SELECT c.*,u.s,u.mcp_enabled FROM mcp_oauth_codes c JOIN users u ON u.id=c.user_id '
                . 'WHERE c.code_hash=? AND c.expires_at>NOW() FOR UPDATE',
                [hash('sha256', $code)]
            )->fetch_assoc();
            if (!$row || (int)$row['s'] <= 0 || (int)$row['mcp_enabled'] !== 1
                || !hash_equals((string)$row['client_id'], $client)
                || !hash_equals((string)$row['redirect_uri'], $redirect)
                || !hash_equals((string)$row['resource'], $resource)
                || !hash_equals((string)$row['code_challenge'], self::pkceChallenge($verifier))) {
                throw new InvalidArgumentException('invalid_grant');
            }
            $db->execute_query('DELETE FROM mcp_oauth_codes WHERE id=?', [(int)$row['id']]);
            $tokens = self::newPair();
            $db->execute_query(
                'INSERT INTO mcp_oauth_tokens (user_id,client_id,resource,scopes,access_hash,refresh_hash,expires_at,refresh_expires_at) '
                . 'VALUES (?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),DATE_ADD(NOW(),INTERVAL 30 DAY))',
                [(int)$row['user_id'], $client, $resource, $row['scopes'],
                    hash('sha256', $tokens['access_token']), hash('sha256', $tokens['refresh_token'])]
            );
            $db->commit();
            return $tokens + ['token_type' => 'Bearer', 'expires_in' => self::ACCESS_SECONDS,
                'scope' => (string)$row['scopes']];
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public static function refresh(mysqli $db, array $input): array
    {
        $refresh = self::parameter($input, 'refresh_token');
        $client = self::parameter($input, 'client_id');
        $resource = self::parameter($input, 'resource');
        if (!preg_match('/^rbxr_[a-f0-9]{64}$/D', $refresh)
            || !self::isClientId($client) || $resource !== self::resource()) {
            throw new InvalidArgumentException('invalid_grant');
        }
        $db->begin_transaction();
        try {
            $row = $db->execute_query(
                'SELECT t.*,u.s,u.mcp_enabled FROM mcp_oauth_tokens t JOIN users u ON u.id=t.user_id '
                . 'WHERE t.refresh_hash=? AND t.refresh_expires_at>NOW() AND t.revoked_at IS NULL FOR UPDATE',
                [hash('sha256', $refresh)]
            )->fetch_assoc();
            if (!$row || (int)$row['s'] <= 0 || (int)$row['mcp_enabled'] !== 1
                || !hash_equals((string)$row['client_id'], $client)
                || !hash_equals((string)$row['resource'], $resource)) {
                throw new InvalidArgumentException('invalid_grant');
            }
            $tokens = self::newPair();
            $db->execute_query(
                'UPDATE mcp_oauth_tokens SET access_hash=?,refresh_hash=?,expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),'
                . 'refresh_expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY) WHERE id=?',
                [hash('sha256', $tokens['access_token']), hash('sha256', $tokens['refresh_token']), (int)$row['id']]
            );
            $db->commit();
            return $tokens + ['token_type' => 'Bearer', 'expires_in' => self::ACCESS_SECONDS,
                'scope' => (string)$row['scopes']];
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    private static function newPair(): array
    {
        return [
            'access_token' => 'rbxo_' . bin2hex(random_bytes(32)),
            'refresh_token' => 'rbxr_' . bin2hex(random_bytes(32)),
        ];
    }

    public static function authenticate(mysqli $db, string $token): array
    {
        $row = $db->execute_query(
            'SELECT t.id AS oauth_id,t.user_id,t.scopes,t.resource,u.user,u.s,u.mcp_enabled '
            . 'FROM mcp_oauth_tokens t JOIN users u ON u.id=t.user_id '
            . 'WHERE t.access_hash=? AND t.expires_at>NOW() AND t.revoked_at IS NULL LIMIT 1',
            [hash('sha256', $token)]
        )->fetch_assoc();
        try {
            $resource = self::resource();
        } catch (RuntimeException $e) {
            throw new McpAccessException('OAuth issuer is not configured', 401);
        }
        if (!$row || (int)$row['s'] <= 0 || (int)$row['mcp_enabled'] !== 1
            || !hash_equals((string)$row['resource'], $resource)) {
            throw new McpAccessException('OAuth access token expired or revoked', 401);
        }
        $scopes = explode(' ', (string)$row['scopes']);
        $scopes = array_values(array_intersect($scopes, McpSecurity::SCOPES));
        if (!$scopes) {
            throw new McpAccessException('OAuth token has no supported scopes', 401);
        }
        return [
            'user_id' => (int)$row['user_id'], 'user' => $row['user'],
            'token_id' => (int)$row['oauth_id'], 'token_kind' => 'oauth',
            'cache_identity' => 'oauth:' . (int)$row['oauth_id'], 'scopes' => $scopes,
        ];
    }

    /**
     * Permanently delete an owned OAuth connection and its pending authorization codes.
     * A consumed authorization code is already removed during exchangeCode().
     */
    public static function revoke(mysqli $db, int $uid, int $tokenId): void
    {
        if ($uid < 1 || $tokenId < 1) {
            throw new InvalidArgumentException('OAuth connection was not found');
        }

        $db->begin_transaction();
        try {
            $connection = $db->execute_query(
                'SELECT id,client_id,resource FROM mcp_oauth_tokens '
                . 'WHERE id=? AND user_id=? FOR UPDATE',
                [$tokenId, $uid]
            )->fetch_assoc();

            if (!$connection) {
                throw new InvalidArgumentException('OAuth connection was not found');
            }

            // Pending codes have no token_id: match the owner, client and MCP resource.
            // This prevents an unconsumed code for the disconnected client from issuing
            // another access/refresh pair after the user revokes the connection.
            $db->execute_query(
                'DELETE FROM mcp_oauth_codes WHERE user_id=? AND client_id=? AND resource=?',
                [$uid, (string)$connection['client_id'], (string)$connection['resource']]
            );

            $db->execute_query(
                'DELETE FROM mcp_oauth_tokens WHERE id=? AND user_id=?',
                [$tokenId, $uid]
            );

            if ($db->affected_rows !== 1) {
                throw new RuntimeException('OAuth token deletion failed');
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}
