#!/bin/bash

set -e

if [ -n "${INSTALLED:-}" ]; then
    echo "使用环境变量设置安装状态..."
    if [ "${INSTALLED}" = "true" ]; then
        echo "安装锁" > /var/www/html/install/install.lock
    else
        rm -rf /var/www/html/install/install.lock
    fi
else
    echo "使用挂载卷保存安装状态..."

    if [ ! -d /data/install ]; then
        mkdir -p /data/install
        cp -a /opt/install-template/. /data/install/
    fi

    if [ ! -L /var/www/html/install ]; then
        rm -rf /var/www/html/install
        ln -sf /data/install /var/www/html/install
    fi
fi

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

chown -R www-data:www-data /var/www/html
chown -R www-data:www-data /data
apache2-foreground
