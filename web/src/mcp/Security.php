<?php
declare(strict_types=1);

/** MCP credentials are intentionally independent of users.token (firmware/API). */
final class McpAccessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 403)
    {
        parent::__construct($message);
    }
}

final class McpSecurity
{
    /** Prevent previously issued, ignored remote:read grants from becoming active unexpectedly. */
    private const REMOTE_GRANT = 'remote:read:granted-26.5.0';
    public const SCOPES = [
        'telemetry:read', 'telemetry:live', 'location:read', 'remote:read',
    ];

    public static function validScopes(mixed $requested): array
    {
        if (!is_array($requested) || count($requested) < 1 || count($requested) > count(self::SCOPES)) {
            throw new InvalidArgumentException('Select at least one access scope');
        }
        $scopes = array_values(array_unique($requested));
        foreach ($scopes as $scope) {
            if (!is_string($scope) || !in_array($scope, self::SCOPES, true)) {
                throw new InvalidArgumentException('Unknown access scope');
            }
        }
        return $scopes;
    }

    public static function generate(mysqli $db, int $uid, string $label, array $scopes, int $days): string
    {
        $label = trim($label);
        if ($uid < 1 || $label === '' || strlen($label) > 80 || $days < 1 || $days > 365) {
            throw new InvalidArgumentException('Invalid token parameters (expiry 1–365 days)');
        }
        $scopes = self::validScopes($scopes);
        $row = $db->execute_query(
            'SELECT COUNT(*) AS cnt FROM mcp_tokens WHERE user_id=? AND revoked_at IS NULL AND expires_at > NOW()',
            [$uid]
        )->fetch_assoc();
        if ((int)$row['cnt'] >= 5) {
            throw new InvalidArgumentException('Maximum of 5 active MCP tokens');
        }
        $plain = 'rbxmcp_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $db->execute_query(
            'INSERT INTO mcp_tokens (user_id, label, token_hash, token_prefix, scopes, expires_at) '
            . 'VALUES (?,?,?,?,?,DATE_ADD(NOW(), INTERVAL ? DAY))',
            [$uid, $label, $hash, substr($plain, 0, 15), implode(',', array_map(
                static fn(string $scope): string => $scope === 'remote:read' ? self::REMOTE_GRANT : $scope,
                $scopes
            )), $days]
        );
        return $plain;
    }

    /**
     * Reject malformed or missing Bearer credentials without a database lookup.
     * The database still validates token ownership, expiry and permissions later.
     */
    public static function bearerToken(): string
    {
        $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strcasecmp($key, 'Authorization') === 0) {
                    $header = (string)$value;
                    break;
                }
            }
        }

        if (!preg_match('/^Bearer (rbxmcp_[a-f0-9]{64})$/i', trim($header), $matches)) {
            throw new McpAccessException('Missing or invalid MCP bearer token', 401);
        }

        return $matches[1];
    }

    public static function authenticate(mysqli $db): array
    {
        $token = self::bearerToken();
        $result = $db->execute_query(
            'SELECT t.id AS token_id, t.user_id, t.scopes, u.user, u.s, u.mcp_enabled '
            . 'FROM mcp_tokens t JOIN users u ON u.id=t.user_id '
            . 'WHERE t.token_hash=? AND t.revoked_at IS NULL AND t.expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        $auth = $result->fetch_assoc();
        if (!$auth || (int)$auth['s'] <= 0 || (int)$auth['mcp_enabled'] !== 1) {
            throw new McpAccessException('MCP token expired, revoked or access disabled', 401);
        }
        $auth['user_id'] = (int)$auth['user_id'];
        $auth['token_id'] = (int)$auth['token_id'];
        // Legacy remote:read and remote:write values were ignored previously. Do not
        // silently grant new access to MCU settings on upgrading an existing token.
        $stored = explode(',', (string)$auth['scopes']);
        $scopes = array_values(array_intersect($stored, self::SCOPES));
        $scopes = array_values(array_diff($scopes, ['remote:read']));
        if (in_array(self::REMOTE_GRANT, $stored, true)) {
            $scopes[] = 'remote:read';
        }
        $auth['scopes'] = $scopes;
        if ($auth['scopes'] === []) {
            throw new McpAccessException('MCP token has no supported scopes', 401);
        }
        return $auth;
    }

    /** Convert database grants into safe public labels for the Settings page. */
    public static function displayedScopes(string $stored): array
    {
        $items = explode(',', $stored);
        $active = array_values(array_diff(array_intersect($items, self::SCOPES), ['remote:read']));
        if (in_array(self::REMOTE_GRANT, $items, true)) {
            $active[] = 'remote:read';
        }
        return $active;
    }

    public static function can(array $auth, array $required): bool
    {
        foreach ($required as $scope) {
            if (!in_array($scope, $auth['scopes'], true)) {
                return false;
            }
        }
        return true;
    }

    public static function requireScopes(array $auth, array $required): void
    {
        if (!self::can($auth, $required)) {
            throw new McpAccessException('MCP token missing required scope: ' . implode(', ', $required));
        }
    }

    public static function rateLimit(array $auth, ?RatelCache $cache): void
    {
        $id = (int)$auth['token_id'];
        // Configurable MCP limit; independent from device API rate limiting.
        $limit = max(1, min(1000, (int)($GLOBALS['mcp_max_requests_per_second'] ?? 15)));
        $allowed = true;
        if ($cache !== null && $cache->isConnected()) {
            try {
                $n = $cache->incrementWithTtl("mcp_rl_{$id}", 1, 1);
                if ($n !== false) {
                    $allowed = $n <= $limit;
                    if (!$allowed) {
                        throw new McpAccessException('Rate limit exceeded', 429);
                    }
                    return ;
                }
            } catch (McpAccessException $e) {
                throw $e;
            } catch (Throwable $ignored) {
                // Falls back to a file lock if Redis temporarily fails.
            }
        }
        $path = sys_get_temp_dir() . '/ratel_mcp_rl_' . $id;
        $fp = @fopen($path, 'c+');
        if ($fp === false || !flock($fp, LOCK_EX)) {
            throw new McpAccessException('Rate limiter unavailable', 503);
        }
        try {
            $current = (int)floor(microtime(true));
            rewind($fp);
            $stored = explode(':', (string)stream_get_contents($fp));
            $count = ((int)($stored[0] ?? 0) === $current) ? (int)($stored[1] ?? 0) + 1 : 1;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, $current . ':' . $count);
            fflush($fp);
            $allowed = $count <= $limit;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        if (!$allowed) {
            throw new McpAccessException('Rate limit exceeded', 429);
        }
    }

    /**
     * Permanently delete a token owned by the authenticated user.
     * The optional expired-only action also checks expiry under the row lock.
    */
    public static function revoke(mysqli $db, int $uid, int $tokenId, bool $expiredOnly = false): void
    {
        if ($uid < 1 || $tokenId < 1) {
            throw new InvalidArgumentException('Invalid token id');
        }
        $db->begin_transaction();
        try {
            $owned = $db->execute_query(
                'SELECT id,(expires_at<=NOW()) AS is_expired FROM mcp_tokens WHERE id=? AND user_id=? FOR UPDATE',
                [$tokenId, $uid]
            )->fetch_assoc();
            if (!$owned) {
                throw new InvalidArgumentException('Invalid token id');
            }
            if ($expiredOnly && (int)$owned['is_expired'] !== 1) {
                throw new InvalidArgumentException('Token has not expired');
            }
            $db->execute_query(
                'DELETE FROM mcp_tokens WHERE id=? AND user_id=?',
                [$tokenId, $uid]
            );
            if ($db->affected_rows !== 1) {
                throw new RuntimeException('Token deletion failed');
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}
