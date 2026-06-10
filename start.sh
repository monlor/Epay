#!/bin/bash

set -e

cat > /var/www/html/config.php <<-EOF
<?php
    /*数据库配置*/
    \$dbconfig=array(
        'host' => '${DB_HOST}', //数据库服务器
        'port' => ${DB_PORT:-3306}, //数据库端口
        'user' => '${DB_USERNAME}', //数据库用户名
        'pwd' => '${DB_PASSWORD}', //数据库密码
        'dbname' => '${DB_DATABASE}', //数据库名
        'dbqz' => 'pay' //数据表前缀
    );
EOF

LOCK_FILE=/var/www/html/install/install.lock

if [ -n "${DB_HOST}" ] && [ -n "${DB_USERNAME}" ]; then
    if php -r '
try {
    $pdo = new PDO(
        "mysql:host=" . getenv("DB_HOST") . ";port=" . (getenv("DB_PORT") ?: 3306) . ";dbname=" . getenv("DB_DATABASE"),
        getenv("DB_USERNAME"), getenv("DB_PASSWORD"),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->query("SELECT 1 FROM pay_config LIMIT 1");
    exit(0);
} catch (Exception $e) { exit(1); }
' > /dev/null 2>&1; then
        echo "检测到已安装，写入安装锁"
        echo "installed" > "$LOCK_FILE"
    fi
fi

chown -R www-data:www-data /var/www/html
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
