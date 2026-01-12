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
    xml

# 安装 Redis 扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安装 event 扩展（可选，提升性能）
RUN pecl install event && docker-php-ext-enable --ini-name zz-event.ini event

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# 复制项目文件
COPY . .

# 安装依赖
RUN composer install --no-dev --optimize-autoloader

# 创建运行时目录
RUN mkdir -p runtime/logs && chmod -R 777 runtime

EXPOSE 8787 8788

CMD ["php", "start.php", "start"]
