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
- Dynamic layout
- and more ...

### Requirements:
- PHP8.2+
- php-gd extension
- php-mysql extension
- nginx with php-fpm(recommended) or Apache2 web-server(not tested) with proper SSL configuration
- Latest available MySQL/MariaDB

### Installation:
1. Create database and user with all privileges on this database
2. Rename creds.php.example to creds.php
3. Open creds.php file
4. Fill MySQL settings and choose database engine (InnoDB or RocksDB if available)
5. Change admin login if needed - $admin variable
6. Create empty file with name 'install' in root folder of installation (Make sure web-server have write rights on folder)
7. Sign in with admin login and admin password (default password: admin) (Users table will be created while sign in)
8. Create new user in admin panel and change admin password
9. Done!

### Screenshots:
![](https://redbox.pw/wp-content/uploads/2024/02/interface_main.png)

![](https://redbox.pw/wp-content/uploads/2024/02/interface_admin.png)