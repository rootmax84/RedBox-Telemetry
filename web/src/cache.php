<?php
declare(strict_types=1);

/**
 * RatelCache — Memcached-совместимая обёртка над Redis.
 *
 * Эмулирует подмножество API php-memcached (get/set/delete/increment/
 * getResultCode/getAllKeys), которое использовалось в проекте.
 *
 * Особенности:
 *   - Значения сериализуются через PHP serialize() — совпадает по
 *     поведению с php-memcached (сохраняются типы массивов).
 *   - Целые числа (int) хранятся как raw-строки, чтобы работал incrBy.
 *     Это единственный тип, для которого важна совместимость с INCR.
 *   - getResultCode() эмулирует RatelCache::RES_SUCCESS / RES_NOTFOUND.
 *   - getAllKeys() реализован через SCAN (не KEYS). Используется только
 *     в редком пути cache_flush(null, $keyname) — на практике не
 *     вызывается (там всегда передаётся точный ключ).
 *
 * Использование:
 *   require_once __DIR__ . '/cache.php';
 *   $memcached = new RatelCache(get_redis_connection());
 *   $memcached_connected = $memcached->isConnected();
 *
 * Все существующие вызовы $memcached->get()/set()/delete()/increment()
 * и проверки $memcached->getResultCode() === RatelCache::RES_SUCCESS
 * продолжают работать без изменений в бизнес-логике.
 */
final class RatelCache
{
    /** Совместимость с RatelCache::RES_SUCCESS */
    public const RES_SUCCESS  = 0;
    /** Совместимость с Memcached::RES_NOTFOUND */
    public const RES_NOTFOUND = 16;

    /** @var Redis|null */
    private $redis;

    private int $lastResultCode = self::RES_SUCCESS;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function isConnected(): bool
    {
        return $this->redis instanceof Redis;
    }

    /**
     * @return mixed|false false при miss, иначе десериализованное значение.
     */
    public function get(string $key)
    {
        if (!$this->redis instanceof Redis) {
            $this->lastResultCode = self::RES_NOTFOUND;
            return false;
        }

        try {
            $raw = $this->redis->get($key);
        } catch (Throwable $e) {
            error_log('RatelCache::get error: ' . $e->getMessage());
            $this->lastResultCode = self::RES_NOTFOUND;
            return false;
        }

        if ($raw === false) {
            $this->lastResultCode = self::RES_NOTFOUND;
            return false;
        }

        $this->lastResultCode = self::RES_SUCCESS;

        // Int храним как raw-число (см. set()). Это позволяет incrBy.
        if (is_string($raw) && preg_match('/^-?\d+$/', $raw)) {
            return (int)$raw;
        }

        $decoded = @unserialize($raw, ['allowed_classes' => false]);
        if ($decoded === false && $raw !== 'b:0;') {
            // Повреждённые данные → ведём себя как при miss.
            return false;
        }
        return $decoded;
    }

    /**
     * @param int $ttl 0 = без TTL (как в Memcached)
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        if (!$this->redis instanceof Redis) {
            return false;
        }

        // Int — единственный тип, который должен поддерживать INCR.
        // Храним без сериализации, чтобы incrBy работал.
        if (is_int($value)) {
            $raw = (string)$value;
        } else {
            $raw = serialize($value);
        }

        try {
            return $ttl > 0
                ? (bool)$this->redis->setex($key, $ttl, $raw)
                : (bool)$this->redis->set($key, $raw);
        } catch (Throwable $e) {
            error_log('RatelCache::set error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool
    {
        if (!$this->redis instanceof Redis) {
            return false;
        }
        try {
            $this->redis->del($key);
            return true;
        } catch (Throwable $e) {
            error_log('RatelCache::delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return int|false новое значение, либо false при ошибке.
     *
     * ВАЖНО: phpredis::incrBy на несуществующем ключе создаёт его со
     * значением offset и БЕЗ TTL. Все вызовы в проекте сначала делают
     * set($key, 1, $ttl) при miss, так что на практике ключ существует.
     */
    public function increment(string $key, int $offset = 1)
    {
        if (!$this->redis instanceof Redis) {
            return false;
        }
        try {
            return $this->redis->incrBy($key, $offset);
        } catch (Throwable $e) {
            error_log('RatelCache::increment error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Атомарный INCRBY + EXPIRE через Lua-скрипт.
     *
     * Решает проблему "залипшего TTL": если между отдельными INCR и EXPIRE
     * оборвётся соединение, ключ останется без TTL, и счётчик будет расти
     * вечно — пользователь получит 429 перманентно.
     *
     * Скрипт исполняется на сервере Redis целиком, без сетевых вызовов
     * между INCR и EXPIRE. Промежуточного состояния не существует.
     *
     * TTL ставится только на первом инкременте (когда результат равен
     * $offset — то есть это первая операция над только что созданным
     * ключом). На последующих вызовах TTL не трогается, окно не продлевается.
     *
     * @return int|false новое значение, либо false при ошибке.
     */
    public function incrementWithTtl(string $key, int $ttl, int $offset = 1)
    {
        if (!$this->redis instanceof Redis) {
            return false;
        }
        try {
            $script =
                "local c = redis.call('INCRBY', KEYS[1], ARGV[1]) " .
                "if c == tonumber(ARGV[1]) then " .
                "  redis.call('EXPIRE', KEYS[1], ARGV[2]) " .
                "end " .
                "return c";

            // phpredis::eval($script, $args, $num_keys)
            // $num_keys = 1 → KEYS[1] = $key, ARGV[1] = $offset, ARGV[2] = $ttl
            $result = $this->redis->eval($script, [$key, $offset, $ttl], 1);

            return $result === false ? false : (int)$result;
        } catch (Throwable $e) {
            error_log('RatelCache::incrementWithTtl error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Установить TTL на существующий ключ.
     * Возвращает true, если TTL установлен (ключ существует).
     */
    public function expire(string $key, int $ttl): bool
    {
        if (!$this->redis instanceof Redis) {
            return false;
        }
        try {
            return (bool)$this->redis->expire($key, $ttl);
        } catch (Throwable $e) {
            error_log('RatelCache::expire error: ' . $e->getMessage());
            return false;
        }
    }

    public function getResultCode(): int
    {
        return $this->lastResultCode;
    }

    /**
     * Эмуляция Memcached::getAllKeys через SCAN.
     * В горячем пути не используется.
     *
     * @return string[]
     */
    public function getAllKeys(): array
    {
        if (!$this->redis instanceof Redis) {
            return [];
        }
        $keys = [];
        $it = null;
        try {
            do {
                $batch = $this->redis->scan($it, '*', 500);
                if ($batch === false) break;
                foreach ($batch as $k) $keys[] = $k;
            } while ((int)$it > 0);
        } catch (Throwable $e) {
            error_log('RatelCache::getAllKeys error: ' . $e->getMessage());
        }
        return $keys;
    }
}
