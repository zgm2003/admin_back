FROM php:8.2-cli AS base

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libevent-dev \
    unzip \
    git \
    procps \
    && rm -rf /var/lib/apt/lists/*

# 安装 PHP 扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    mbstring \
    zip \
    gd \
    bcmath \
    pcntl \
    posix \
    sockets \
    curl \
    xml \
    opcache

# 安装 Redis 扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安装 event 扩展（提升异步 I/O 性能）
RUN printf '\n\n\n\n\n\n' | pecl install event \
    && docker-php-ext-enable --ini-name zz-event.ini event

# OPcache 生产配置
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# PHP 运行时配置
COPY docker/php.ini /usr/local/etc/php/conf.d/99-webman.ini

# 安装 Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ========== 依赖层（改代码不重装依赖） ==========
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# ========== 代码层 ==========
COPY . .

# 执行 post-install 脚本（如果有）
RUN composer dump-autoload --optimize --no-dev

# 创建运行时目录
RUN mkdir -p runtime/logs runtime/views \
    && chmod -R 777 runtime

# 健康检查
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "echo file_get_contents('http://127.0.0.1:8787/');" || exit 1

EXPOSE 8787 8788

CMD ["php", "start.php", "start"]