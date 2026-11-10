<p align="center" width="100%">
<img width="10%" src="https://github.com/user-attachments/assets/40f72afc-300c-47f6-8ded-2898eeae8654">
</p>

![CI History](https://img.shields.io/github/actions/workflow/status/rootmax84/RedBox-Telemetry/.github/workflows/docker-image.yml?branch=main&label=build%20history&style=flat-round) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=rootmax84_RedBox-Telemetry&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=rootmax84_RedBox-Telemetry) [![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=rootmax84_RedBox-Telemetry&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=rootmax84_RedBox-Telemetry) [![CodeQL](https://github.com/rootmax84/RedBox-Telemetry/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/rootmax84/RedBox-Telemetry/actions/workflows/github-code-scanning/codeql)
# RedBox Telemetry
Forked from Open Torque Viewer. Refactored and adapted for RedBox Automotive devices. Can be used with Torque PRO and generic OBD devices too.

### Key features:
- True multiuser with separate data and global admin
- Data upload requires authorization with a bearer token
- Leaflet map provider
- Export to KML format
- Live data streaming and real-time tracking
- RedManage dashboards support
- Remote RedManage settings control
- Import logs from RedManage/Torque
- User data limits and quotas
- Light/Dark themes
- Maintenance mode
- Comprehensive admin panel
- Notifications via Telegram
- PWA support
- Dynamic layout and units conversion
- Map heatline visualization by selected data source
- Session sharing capabilities
- Ability to delete/export selected parts of sessions
- Customizable data point filtering before graph rendering
- Simple API for fetching latest metrics
- API exporter for Prometheus/Grafana integration
- Localized UI (EN/RU/ES/DE)
- Built-in MCP server (2026-07-28 spec): read-only telemetry, PID analytics, live metrics, session comparison, GPS and RedManage configuration diagnostics, with scoped Bearer tokens
- and more...

### Architecture
<details>
  <summary>Show</summary>
    
## General

```text
  RedBox / Torque / OBD devices       Web browser             AI / MCP client
                |                         |                           |
         telemetry upload           HTTPS web UI               HTTPS + Bearer
                |                         |                     (read-only MCP)
                +-------------------------+---------------------------+
                                          |
                                  TLS reverse proxy
                                          |
                              +-----------v-----------+
                              |          web          |
                              |     nginx + PHP-FPM   |
                              |                       |
                              | /api/upload /settings |
                              | /plot       /mcp      |
                              +-----+------------+----+
                                    |            |
                        SQL/storage |            | shared cache / queues
                                    |            |
                         +----------v----+  +----v--------------------------+
                         |    MariaDB    |  |           Redis               |
                         | (RocksDB /    |  |                                |
                         |   InnoDB)     |  | Application cache (RatelCache) |
                         | users, logs,  |  | Plot JSON / chunk cache        |
                         | sessions,     |  | MCP query cache & rate limits  |
                         | pids, tokens  |  | Streams: uploads, heavy tasks, |
                         +-------^-------+  |          notifications         |
                                 |          +----------------+---------------+
                                 |                           |
                                 | persist uploads / tasks   | XREADGROUP
                                 +---------------------------+
                                                             |
                                                        worker x N
```

- **MCP**: `/mcp` exposes 21 read-only tools: 16 telemetry/live/comparison/GPS tools and 5 RedManage configuration/boost/PID analysis tools, plus two resources and two prompts. Per-user token scopes: `telemetry:read`, `telemetry:live`, `location:read`, `remote:read`. **No MCP configuration writes or device-control commands are exposed.** RedManage controls remain available only in the web UI.
- **Data storage**: sessions, PID catalogs, telemetry logs and hashed MCP credentials are stored in MariaDB. MCP authentication checks the database, including after token deletion.
- **Redis (shared)**: backs the regular application cache (`RatelCache`, including session/profile/PID metadata), the chunked plot JSON cache, and MCP query results and per-token rate limiting. Redis Streams also handle telemetry uploads, heavy tasks and notifications. MCP telemetry reads fall back to MariaDB when the Redis cache is unavailable.
- **Worker**: processes upload, heavy-task and notification Streams; upload and relevant task results are persisted in MariaDB.

## Fast path vs inline fallback

```
FAST PATH (Redis available):

  Device ──POST/GET──▶ ul.php ──XADD──▶ Redis Stream ──XREADGROUP──▶ worker ──▶ MariaDB
                     │
                     └─◀── "OK!" (fast on high load rate)

INLINE FALLBACK (Redis disabled or unavailable):

  Device ──POST/GET──▶ ul.php ─────────────────────────────────────────────────▶ MariaDB
                     │                                             (processUpload inline)
                     └─◀── "OK!" (slow on high load rate)

```
</details>

### Standalone installation requirements:
- PHP8.2+
- php-mysql extension
- php-redis (OPTIONAL)
- redis (OPTIONAL)
- nginx with php-fpm(recommended) or Apache2 web-server(not tested) with proper SSL configuration
- Database:
  - MySQL 8.0+
     (Optional: MyRocks engine via Percona Server for MySQL, for using the RocksDB storage engine — not tested)
  - MariaDB 10.6+
     (Optional: ```mariadb-plugin-rocksdb``` for using the RocksDB storage engine)
  - <b>Recommended</b>: Use the RocksDB engine — it's pretty fast and storage-efficient

### Installation standalone:
1. Create database and user with all privileges on this database
2. Rename ./web/src/creds.php.example to creds.php
3. Open ./web/src/creds.php file
4. Fill MySQL settings and choose database engine (InnoDB or RocksDB if available)
5. Change admin login if needed - $admin variable
6. **Run the database migrations once from the `web` directory:** `cd web && php migrate.php --auto` (check the exit code; it must be zero). This creates the shared telemetry and MCP tables. Re-run after standalone upgrades; the migration is idempotent.
7. Create empty file with name 'install' in root folder of installation (Make sure web-server have write rights on folder)
8. Sign in with admin login and admin password (default password: admin) (Users table will be created while sign in)
9. Create new user in admin panel and change admin password
10. For upload data from Torque PRO/RedManage use URL - https://your.site/api/upload
11. Done!

**Docker installations:** do **not** run `php migrate.php --auto` manually; the container startup already runs migrations.

### Installation docker:
0. Install Docker Engine and docker-compose from your distro repository
1. Clone repo and ```docker-compose up -d``` (default http port 8080)
2. Configure reverse-proxy with SSL (nginx, traefik, etc) or configure SSL at you own inside web container ./docker/web
3. Sign in with admin login and admin password (default password: admin)
4. Create new user in admin panel and change admin password
5. For upload data from Torque PRO/RedManage use URL - https://your.site/api/upload
6. Done!

### Migrate standalone installation to docker:

1. Backup database from standalone installation: ```mariadb-dump --databases $database_name -uroot -p$password > /some/path/backup.sql```
2. Restore this backup to docker mariadb container: ```docker exec -i ratel_mariadb sh -c 'exec mariadb -uroot -p$password --database $database_name' < /some/path/backup.sql```

### Demo:
[Click it](https://demo.redbox.pw/ratel/)

### Screenshots:

<table>
  <tr>
    <td>
      <img width="100%" src="https://github.com/user-attachments/assets/c753ab4d-19cd-41ae-b172-0199c406ad83">
    </td>
    <td>
      <img width="100%" src="https://github.com/user-attachments/assets/7d208589-b02a-4606-9da6-2ef2bca6f5da">
    </td>
  </tr>
  <tr>
    <td>
      <img width="100%" src="https://github.com/user-attachments/assets/441089e5-c830-4687-9100-f8098e7ce332">
    </td>
    <td>
      <img width="100%" src="https://github.com/user-attachments/assets/7ea8c64f-cc12-4900-8e46-416b7e482321">
    </td>
  </tr>
</table>

### creds.php variables:

| Variable | Description | Default |
|----------|-------------|---------|
| $username | Current username (from session) | from session |
| $limit | Current user limit (from session) | from session |
| $db_host | Database host address (mariadb for Docker) | mariadb |
| $db_user | MySQL username | ratel |
| $db_pass | MySQL password | ratel |
| $db_port | MySQL port | 3306 |
| $db_name | Database name | ratel |
| $user_id | Current user ID (from session) | $_SESSION['uid'] ?? null |
| $db_engine | Table engine (ROCKSDB or INNODB) | ROCKSDB |
| $db_cache_ttl | Redis cache TTL in seconds | 3600 |
| $db_cache_meta_ttl | Redis metadata cache TTL (seconds) | 300 |
| $def_limit | Default user database size limit (session count) | 100 |
| $max_upload_requests_per_second | Upload rate limit (requests/sec) | 100 |
| $max_api_requests_per_second | API rate limit (requests/sec) | 10 |
| $mcp_require_https | Require HTTPS for remote MCP Bearer requests | true |
| $mcp_trust_forwarded_proto | Trust `X-Forwarded-Proto` only behind a trusted TLS reverse proxy | false |
| $mcp_max_requests_per_second | MCP requests/sec per token (Redis or file fallback; 1–1000) | 15 |
| $mcp_db_connect_timeout | MCP database connection timeout (seconds; 1–10) | 3 |
| $mcp_cache_enabled | Enable Redis MCP query cache, with direct-DB fallback | true |
| $mcp_cache_catalog_ttl | MCP PID catalog cache TTL (seconds) | 120 |
| $mcp_cache_sessions_ttl | MCP session list/metadata cache TTL (seconds) | 15 |
| $mcp_cache_analytics_ttl | MCP historical analytics including boost/PID analysis cache TTL (seconds) | 600 |
| $mcp_cache_live_ttl | MCP last-known live readings cache TTL (seconds) | 2 |
| $mcp_cache_max_bytes | Maximum uncompressed result payload stored in Redis (bytes) | 131072 |
| $mcp_cache_lock_ttl | MCP duplicate-computation lock TTL (seconds) | 15 |
| $redis_enabled | Master switch for Redis integration | true |
| $redis_host | Redis host address (redis for Docker) | redis |
| $redis_port | Redis port | 6379 |
| $redis_timeout | Redis connect timeout in seconds | 2.0 |
| $redis_password | Redis AUTH password (empty = no auth) | (empty) |
| $redis_db | Redis logical database index (0-15) | 0 |
| $redis_stream_enabled | Use Redis Streams for async upload processing | true |
| $redis_stream_key | Redis Stream key name | telemetry:uploads |
| $redis_stream_group | Consumer group name for worker | telemetry-workers |
| $redis_stream_maxlen | Max messages in Stream (~ MAXLEN, 0 = unlimited) | 50000 |
| $redis_cache_compression_enabled | compress (zstd->lz4->deflate) application cache | true |
| $heavy_tasks_enabled | Enable heavy async tasks | true |
| $redis_heavy_stream_key | Redis Stream key for heavy tasks | ratel:heavy_tasks |
| $redis_heavy_stream_maxlen | Max messages in heavy Stream (0 = unlimited) | 10000 |
| $heavy_task_ttl | TTL of heavy task status, seconds | 86400 |
| $heavy_chunk_size | Rows per DELETE chunk | 5000 |
| $heavy_chunk_pause_us | Pause between chunks, microseconds | 100000 |
| $worker_max_attempts | Transient errors before sending to DLQ | 3 |
| $redis_dlq_key | Redis Stream key for dead letters | ratel:dead_letters |
| $worker_dlq_maxlen | Max messages in DLQ Stream (0 = unlimited) | 10000 |
| $worker_attempt_ttl | TTL for message attempt counter in Redis | 86400 |
| $redis_notifications_stream_key | Notifications stream key | ratel:notifications |
| $redis_notifications_stream_maxlen | Max messages in notifications Stream (0 = unlimited) | 10000 |
| $salt['cost'] | Password hashing cost (4-31, higher = more CPU) | 10 |
| $admin | Admin username | admin |
| $admin_timeformat_12 | Use 12-hour time format (false = 24h) | false |
| $show_session_length | Display session length in UI | true |
| $max_load_avg | Server load limit for log uploads (0 = disabled) | 10 |
| $live_data_rate | Live data response interval (ms) | 1000 |
| $results_per_page | Items per page in pagination | 50 |
| $merge_max | Maximum merged session datapoints | 50000 |
| $tg_socks_proxy | SOCKS5 proxy for Telegram API | (empty) |
| $tg_api_url | Custom Telegram API gateway URL | (empty) |
| $tg_api_id | X-Connection-Id header for gateway auth | (empty) |

**MCP behind a reverse proxy:** leave `$mcp_require_https = true`. Set
`$mcp_trust_forwarded_proto = true` **only** when TLS terminates at a trusted
proxy that overwrites `X-Forwarded-Proto` and the PHP backend cannot be reached
directly from the internet. Configure the remaining MCP variables in your own
`web/src/creds.php`; do not overwrite that file during an upgrade.

### Typical nginx host configuration for standalone installation:
```
#vhost
server {
    listen 80;
    server_name your.site;
    return 301 https://$server_name$request_uri;
}

server {
    root /var/www/RedBox-Telemetry/web;

    #nginx < 1.25
    listen 443 ssl http2;

    #nginx 1.25+
    #listen 443 ssl;
    #http2 on;

    server_name your.site;
    aio threads;

    #GZIP compression settings
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript text/event-stream;

    #Brotli compression settings (if supported)
    #brotli_static   on;
    #brotli          on;
    #brotli_comp_level       6;
    #brotli_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript text/event-stream;

    ssl_certificate /etc/letsencrypt/live/your.site/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your.site/privkey.pem;
    ssl_dhparam /etc/letsencrypt/dhparam.pem;

    location ~ ^/\.well-known(/|$) {
        allow all;
        root /var/www/html;
    }

    location ~* \.(?:php|phtml|phar|inc|env|ini|log|sql|bak|old|swp|orig|yml|yaml|example)$ {
        return 404;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include /etc/nginx/fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~* \.(?:css|js|woff2|woff|ttf|webp|png|jpg|jpeg|gif|svg|ico|map|avif|json)$ {
        expires 30d;
        add_header Cache-Control "public";
        access_log off;
    }
}

#nginx.conf
http {
   client_max_body_size 50m;
   client_header_timeout 900;
   client_body_timeout 900;
   fastcgi_read_timeout 900;
}
```

### RocksDB configuration for standalone installation:
```
[mariadb]
plugin-load-add=ha_rocksdb.so
rocksdb-keep-log-file-num=2
rocksdb-flush-log-at-trx_commit=2
rocksdb-max-log-file-size=10M
rocksdb_max_total_wal_size=10485760

# RocksDB default CF options for small instances.
#Better compression
#rocksdb_default_cf_options=compression=kZSTD;bottommost_compression=kZSTD
#Better performance
#rocksdb_default_cf_options=compression=kLZ4Compression;bottommost_compression=kLZ4Compression
#Recommended
#rocksdb_default_cf_options=compression=kLZ4Compression;bottommost_compression=kZSTD

# RocksDB default CF options optimized for high-load production.
# Compression is tiered by LSM level to balance write throughput and disk space:
#   L0-L1: kNoCompression  -> fastest writes for hot, recently written data
#   L2-L3: kLZ4Compression -> fast compression for warm data
#   L4-L6: kZSTD           -> strong compression for cold, rarely read data
# bottommost_compression=kZSTD ensures the lowest level always uses ZSTD.
rocksdb_default_cf_options="compression_per_level=kNoCompression:kNoCompression:kLZ4Compression:kLZ4Compression:kZSTD:kZSTD:kZSTD;bottommost_compression=kZSTD;max_bytes_for_level_multiplier=4"
```

### PHP configuration for standalone installation:
```
#php.ini
post_max_size = 100M
upload_max_filesize = 50M
```

### MISC
* [Grafana Dashboard example](https://github.com/rootmax84/RedBox-Telemetry/wiki/Grafana-Dashboard-example)
* [API exporter stack example](https://github.com/rootmax84/RedBox-Telemetry/wiki/API-exporter-example)
* [Upload API](https://github.com/rootmax84/RedBox-Telemetry/wiki/Upload-API)
