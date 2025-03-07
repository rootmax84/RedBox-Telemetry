<p align="center" width="100%">
<img width="10%" src="https://github.com/user-attachments/assets/b1d4299d-5d49-4f42-b2ea-83508b31928f">
</p>

# RedBox Telemetry
Forked from Open Torque Viewer. Refactored and adapted for RedBox Automotive devices. Can be used with Torque PRO and generic OBD devices too.

### Key features:
- True multiuser with separate data and global admin
- Data upload requires authorization with a bearer token
- Leaflet MAP provider
- Export KML
- Live data streaming and tracking
- RedManage dashboards support
- Import data from RedManage logger
- Users data limits
- Light/Dark themes
- Maintenance mode
- Admin panel
- Notifications via Telegram
- PWA support
- Dynamic layout/units conversion
- Localized UI (EN/RU/ES/DE)
- and more ...

### Standalone installation requirements:
- PHP8.2+
- php-mysql extension
- php-memcached (OPTIONAL)
- memcached (OPTIONAL)
- nginx with php-fpm(recommended) or Apache2 web-server(not tested) with proper SSL configuration
- Latest available MySQL/MariaDB (OPTIONAL: mariadb-plugin-rocksdb for using ROCKSDB engine)

### Installation standalone:
1. Create database and user with all privileges on this database
2. Rename ./web/creds.php.example to creds.php
3. Open ./web/creds.php file
4. Fill MySQL settings and choose database engine (InnoDB or RocksDB if available)
5. Change admin login if needed - $admin variable
6. Create empty file with name 'install' in root folder of installation (Make sure web-server have write rights on folder)
7. Sign in with admin login and admin password (default password: admin) (Users table will be created while sign in)
8. Create new user in admin panel and change admin password
9. For upload data from Torque PRO/RedManage use URL - https://your.site/ul.php
10. Done!

### Installation docker:
0. Install Docker Engine and docker-compose from your distro repository
1. Rename ./web/creds.php.example to creds.php
2. Open ./web/creds.php file
3. Fill MySQL settings as in docker-compose.yml and choose database engine (InnoDB or RocksDB)
4. Change admin login if needed - $admin variable
5. docker-compose up -d (default http port 8080)
6. Configure reverse-proxy with ssl or configure ssl inside container ./docker/nginx/default.conf
7. Sign in with admin login and admin password (default password: admin) (Users table will be created while sign in)
8. Create new user in admin panel and change admin password
9. For upload data from Torque PRO/RedManage use URL - https://your.site/upload
10. Done!

### Migrate standalone installation to docker:

1. Backup database from standalone installation: ```mariadb-dump --databases $database_name -uroot -p$password > /some/path/backup.sql```
2. Restore this backup to docker mariadb container: ```docker exec -i ratel_mariadb sh -c 'exec mariadb -uroot -p$passowrd --database $database_name' < /some/path/backup.sql```

### Demo:
[Click it](https://demo.redbox.pw/ratel/)

### Screenshots:

<table>
  <tr>
    <td>
      <img width="100%" src="https://github.com/user-attachments/assets/e3c733fe-1fb7-45f0-b14c-37f1ed6e4bcb">
    </td>
    <td>
      <img width="100%" src="https://github.com/user-attachments/assets/0b826e52-6db0-42f9-b990-36753544b015">
    </td>
  </tr>
</table>

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

    location ~ /.well-known {
        allow all;
        root /var/www/html;
    }

    location / {
        index index.php;
            location ~ ^/(.+\.php)$ {
               try_files $uri =404;
               fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
               fastcgi_index index.php;
               fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
               include /etc/nginx/fastcgi_params;
            }
        try_files $uri $uri/ /index.php;
    }

    location /upload {
        try_files $uri $uri/ /ul.php?$query_string;
    }

    location ~* .(?:css|js)$ {
        expires 1d;
        add_header Cache-Control "public";
    }
  error_page 404 =200 /;
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
#Better compression
#rocksdb_default_cf_options=compression=kZSTDNotFinalCompression;bottommost_compression=kZSTDNotFinalCompression
#Better performance
rocksdb_default_cf_options=compression=kLZ4Compression;bottommost_compression=kLZ4Compression
#Recommended
rocksdb_default_cf_options=compression=kLZ4Compression;bottommost_compression=kZSTDNotFinalCompression
```

### PHP configuration for standalone installation:
```
#php.ini
post_max_size = 100M
upload_max_filesize = 50M
```
