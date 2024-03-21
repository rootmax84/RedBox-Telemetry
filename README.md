# RedBox Telemetry
Forked from Open Torque Viewer. Refactored and adapted for RedBox Automotive devices. Can be used with Torque PRO and generic OBD devices too.

### Key features:
- True multiuser with separate data and global admin
- Authentication on torque/redmange side by unique token for each user
- Leaflet MAP provider
- Export KML
- Live data streaming
- RedManage dashboards support
- Import data from RedManage logger
- Users data limits
- Light/Dark themes
- Maintenance mode
- Admin panel
- Notifications via Telegram
- PWA support
- Dynamic layout/units conversion
- and more ...

### Requirements:
- PHP8.2+
- php-gd extension
- php-mysql extension
- nginx with php-fpm(recommended) or Apache2 web-server(not tested) with proper SSL configuration
- Latest available MySQL/MariaDB (OPTIONAL: mariadb-plugin-rocksdb for using ROCKSDB engine)

### Installation:
1. Create database and user with all privileges on this database
2. Rename creds.php.example to creds.php
3. Open creds.php file
4. Fill MySQL settings and choose database engine (InnoDB or RocksDB if available)
5. Change admin login if needed - $admin variable
6. Create empty file with name 'install' in root folder of installation (Make sure web-server have write rights on folder)
7. Sign in with admin login and admin password (default password: admin) (Users table will be created while sign in)
8. Create new user in admin panel and change admin password
9. For upload data from Torque PRO/RedManage use URL - https://your.site/ul.php
10. Done!

### Screenshots:
![](https://redbox.pw/wp-content/uploads/2024/02/interface_main.png?1)

![](https://redbox.pw/wp-content/uploads/2024/02/interface_settings.png?2)

![](https://redbox.pw/wp-content/uploads/2024/02/interface_admin.png)

### Typical nginx host configuration:
```
server {
    listen 80;
    server_name your.site;
    return 301 https://$server_name$request_uri;
}

server {
    root /var/www/RedBox-Telemetry;

    #nginx < 1.25
    listen 443 ssl http2;

    #nginx 1.25+
    #listen 443 ssl;
    #http2 on;

    server_name your.site;
    aio threads;

    gzip on;
    gzip_disable "msie6";
    gzip_comp_level 6;
    gzip_min_length 1100;
    gzip_buffers 16 8k;
    gzip_proxied any;
    gzip_types
    text/plain
    text/css
    text/js
    text/xml
    text/javascript
    application/javascript
    application/x-javascript
    application/json
    application/xml
    application/rss+xml
    image/svg+xml/javascript;

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
```

### RocksDB compression configuration:
```
[mariadb]
plugin-load-add=ha_rocksdb.so
#Better compression
#rocksdb_default_cf_options=compression=kZSTDNotFinalCompression;bottommost_compression=kZSTDNotFinalCompression
#Better performance
rocksdb_default_cf_options=compression=kLZ4Compression;bottommost_compression=kLZ4Compression
#Recommended
rocksdb_default_cf_options=compression=kLZ4Compression;bottommost_compression=kZSTDNotFinalCompression
```
