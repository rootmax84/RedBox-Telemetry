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
 * ── Селективное сжатие ────────────────────────────────────────
 *   Управляется флагом $redis_cache_compression_enabled из creds.php.
 *   По умолчанию включено. Сжимаются только «тяжёлые» значения,
 *   проходящие через set()/get() — session_data_*, gps_data_*,
 *   pids_mapping_*, pids_known_*, profiles_list_*, years_list_*.
 *   Это массивы с повторяющимися строками: 3–8x экономии RAM в Redis.
 *
 *   НЕ сжимается:
 *     - int — хранится как сырая ASCII-строка для INCR/INCRBY.
 *       increment()/incrementWithTtl() работают с ключом напрямую
 *       на сервере Redis и не касаются set()/get().
 *     - значения < COMPRESS_THRESHOLD (1 KB) — overhead не окупается.
 *     - «плохо сжимаемые» payloads — если сжатый вариант не меньше
 *       оригинала, сохраняем оригинал (никакого раздувания).
 *
 *   Формат сжатого payload: "\x00" + <algo> + <compressed bytes>.
 *   Первый байт "\x00" не может начинать serialize()-вывод (там всегда
 *   a:, s:, i:, d:, b:, N;, O:, C:, R:, r:, S:), поэтому старые
 *   несжатые ключи читаются как раньше — flush не обязателен.
 *   Флаг можно переключать на лету: get() всегда определяет формат
 *   по маркеру, а не по текущему значению флага.
 *
 *   Алгоритм резолвится один раз на инстанс по приоритету:
 *     zstd  → lz4  → gzdeflate (zlib встроен в PHP, есть всегда).
 *   Алгоритм «зашит» в сам payload (байт после маркера), поэтому даже
 *   после смены алгоритма на хосте старые ключи не сломаются: либо
 *   прочитаются, либо вернут кеш-мисс, но не бросят исключение.
 *
 * Использование:
 *   require_once __DIR__ . '/cache.php';
 *   $memcached = new RatelCache(get_redis_connection(), $redis_cache_compression_enabled ?? true);
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

    /** Минимальный размер serialize()-payload, при котором включается сжатие. */
    private const COMPRESS_THRESHOLD = 1024;

    /** Первый байт сжатого payload. Не встречается в serialize()-выводе. */
    private const MARKER = "\x00";

    /** Идентификаторы алгоритмов сразу после маркера. */
    private const ALGO_ZSTD    = 'Z';
    private const ALGO_LZ4     = 'L';
    private const ALGO_DEFLATE = 'D';

    /** @var Redis|null */
    private $redis;

    private int $lastResultCode = self::RES_SUCCESS;

    /** Включено ли сжатие (передаётся из creds.php через db.php). */
    private bool $compressionEnabled;

    /**
     * Кэш выбранного алгоритма сжатия.
     * null + algoResolved=false  -> ещё не резолвили
     * null + algoResolved=true   -> сжатие недоступно (нет ни одного расширения)
     * 'Z'|'L'|'D'                -> выбранный алгоритм
     */
    private ?string $algo = null;
    private bool $algoResolved = false;

    public function __construct($redis, bool $compressionEnabled = true)
    {
        $this->redis              = $redis;
        $this->compressionEnabled = $compressionEnabled;
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

        // Сжатый payload — маркер в первом байте (см. set/compress).
        // Определяем формат по маркеру, а не по флагу: так переключение
        // $redis_cache_compression_enabled на лету не ломает уже записанные ключи.
        // Битый или несовместимый payload трактуем как кеш-мисс.
        if (is_string($raw) && $raw !== '' && $raw[0] === self::MARKER) {
            $raw = $this->decompress($raw);
            if ($raw === false) {
                return false;
            }
        }

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
        // НЕ сжимается ни при каких условиях — иначе Redis не сможет
        // интерпретировать значение как число в INCRBY.
        if (is_int($value)) {
            $raw = (string)$value;
        } else {
            $raw = serialize($value);

            // Сжимаем только крупные payloads и только если реально меньше.
            // Если алгоритм недоступен или сжатие не дало выигрыша —
            // сохраняем оригинал (никакого раздувания).
            if ($this->compressionEnabled && strlen($raw) >= self::COMPRESS_THRESHOLD) {
                $packed = $this->compress($raw);
                if ($packed !== false && strlen($packed) < strlen($raw)) {
                    $raw = $packed;
                }
            }
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

    /** Non-blocking distributed lock for MCP analytics. Returns an ownership token. */
    public function acquireLock(string $key, int $ttl = 15): ?string
    {
        if (!$this->redis instanceof Redis) return null;
        try {
            $owner = bin2hex(random_bytes(16));
            return $this->redis->set($key, $owner, ['nx', 'ex' => max(1, $ttl)]) ? $owner : null;
        } catch (Throwable $e) {
            error_log('RatelCache lock error: ' . $e->getMessage());
            return null;
        }
    }

    /** Only the lock holder may release a distributed lock. */
    public function releaseLock(string $key, string $owner): void
    {
        if (!$this->redis instanceof Redis) return;
        try {
            $this->redis->eval(
                "if redis.call('GET',KEYS[1])==ARGV[1] then return redis.call('DEL',KEYS[1]) else return 0 end",
                [$key, $owner], 1
            );
        } catch (Throwable $e) {
            error_log('RatelCache unlock error: ' . $e->getMessage());
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
     * значением offset и БЕЗ TTL.
     *
     * Вызывающие:
     *   - rate limit (ul.php, stream_json.php) — сначала set() при miss,
     *     потом increment() в пределах окна;
     *   - cache_flush() — increment() без предварительного set(),
     *     полагаясь на auto-create со значением 1.
     *
     * Работает напрямую на сервере Redis, не проходит через set()/get(),
     * поэтому селективное сжатие на него не влияет. Значение счётчика
     * всегда остаётся сырой ASCII-строкой.
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

    /* ════════════════════════════════════════════════════════════
     * COMPRESSION INTERNALS
     *
     * Все методы ниже — private. Публичный API (get/set/delete/
     * increment/…) не менялся: сжатие прозрачно для вызывающего кода.
     * ════════════════════════════════════════════════════════════ */

    /**
     * Резолвит лучший доступный алгоритм один раз на инстанс.
     *
     * Приоритет: zstd (лучший коэффициент при высокой скорости) ->
     * lz4 (максимальная скорость) → gzdeflate (zlib встроен в PHP,
     * доступен всегда как безопасный fallback).
     *
     * @return string|null null, если ни одно расширение не найдено.
     */
    private function resolveAlgo(): ?string
    {
        if ($this->algoResolved) {
            return $this->algo;
        }

        if (function_exists('zstd_compress') && function_exists('zstd_uncompress')) {
            $this->algo = self::ALGO_ZSTD;
        } elseif (function_exists('lz4_compress') && function_exists('lz4_uncompress')) {
            $this->algo = self::ALGO_LZ4;
        } elseif (function_exists('gzdeflate') && function_exists('gzinflate')) {
            $this->algo = self::ALGO_DEFLATE;
        } else {
            $this->algo = null;
        }

        $this->algoResolved = true;
        return $this->algo;
    }

    /**
     * Сжимает serialize()-payload.
     *
     * @return string|false  MARKER + algo + compressed bytes, либо false
     *                       при ошибке или отсутствии алгоритма.
     */
    private function compress(string $raw)
    {
        $algo = $this->resolveAlgo();
        if ($algo === null) {
            return false;
        }

        try {
            switch ($algo) {
                case self::ALGO_ZSTD:
                    // level 3 — компромисс скорость/сжатие; под нагрузкой
                    // разница с дефолтным уровнем незначительна по размеру,
                    // но заметна по CPU.
                    $out = zstd_compress($raw, 3);
                    break;
                case self::ALGO_LZ4:
                    $out = lz4_compress($raw);
                    break;
                case self::ALGO_DEFLATE:
                    // level 3: ~2x быстрее дефолтного 6, потеря сжатия
                    // минимальна для типичного JSON-подобного serialize().
                    $out = gzdeflate($raw, 3);
                    break;
                default:
                    return false;
            }
        } catch (Throwable $e) {
            error_log('RatelCache::compress error: ' . $e->getMessage());
            return false;
        }

        if (!is_string($out) || $out === '') {
            return false;
        }

        return self::MARKER . $algo . $out;
    }

    /**
     * Разжимает payload, созданный compress().
     *
     * @return string|false  Разжатый serialize()-payload, либо false при
     *                       любой ошибке (битый формат, неизвестный алгоритм,
     *                       отсутствует функция распаковки).
     */
    private function decompress(string $raw)
    {
        if (strlen($raw) < 3 || $raw[0] !== self::MARKER) {
            return false;
        }

        $algo = $raw[1];
        $body = substr($raw, 2);

        try {
            switch ($algo) {
                case self::ALGO_ZSTD:
                    if (!function_exists('zstd_uncompress')) return false;
                    $out = zstd_uncompress($body);
                    break;
                case self::ALGO_LZ4:
                    if (!function_exists('lz4_uncompress')) return false;
                    $out = lz4_uncompress($body);
                    break;
                case self::ALGO_DEFLATE:
                    if (!function_exists('gzinflate')) return false;
                    // @ — gzinflate() генерирует warning на битом payload,
                    // а мы всё равно трактуем это как кеш-мисс.
                    $out = @gzinflate($body);
                    break;
                default:
                    return false;
            }
        } catch (Throwable $e) {
            error_log('RatelCache::decompress error: ' . $e->getMessage());
            return false;
        }

        return is_string($out) ? $out : false;
    }
}
