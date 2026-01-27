FROM php:8.2-cli

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
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
RUN apt-get update && apt-get install -y libevent-dev && rm -rf /var/lib/apt/lists/* \
    && printf '\n\n\n\n\n\n' | pecl install event \
    && docker-php-ext-enable --ini-name zz-event.ini event

# OPcache 生产配置（显著提升 PHP 性能）
RUN echo 'opcache.enable=1' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.enable_cli=1' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.memory_consumption=256' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.interned_strings_buffer=16' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.max_accelerated_files=10000' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.validate_timestamps=0' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.save_comments=1' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.jit=1255' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.jit_buffer_size=128M' >> /usr/local/etc/php/conf.d/opcache.ini

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 复制项目文件
COPY . .

# 安装依赖（生产模式）
RUN composer install --no-dev --optimize-autoloader

# 创建运行时目录
RUN mkdir -p runtime/logs && chmod -R 777 runtime

EXPOSE 8787 8788

CMD ["php", "start.php", "start"]
