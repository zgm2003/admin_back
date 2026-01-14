### 🧠 智澜全链路智能工作流平台 · 后端服务（Webman）

“智澜” 是一款专为企业级内容创作与协作场景打造的智能工作流平台，后端基于高性能常驻进程框架 Webman 搭建，具备极强的扩展性与并发处理能力。服务端以模块化、可插拔、异步优先为核心原则，支撑从 CMS 内容管理到 AI 全流程生成的全链路能力。

---

### 🚀 技术栈

- **核心框架**：Webman（PHP）
- **任务调度**：webman/redis-queue + Supervisor
- **权限管理**：基于 Token 的鉴权机制（类似 Laravel Sanctum）
- **数据库**：MySQL / Redis
- **上传服务**：腾讯云 COS
- **AI 能力接入**：SiliconFlow API、阿里云 API、腾讯云API、百度API

---

### 🧱 项目结构（分层架构）

```
├── app/
│   ├── command/       任务调度
│   ├── controller/    控制器层（HTTP 接口）
│   ├── dep/           数据访问层（封装 SQL 操作）
│   ├── enum/          枚举类
│   ├── lib/           API 封装、SDK 调用
│   ├── middleware/    中间件
│   ├── model/         实体模型层（数据结构定义）
│   ├── module/        业务逻辑层（组合逻辑、服务层）
│   ├── process/       WebSocket / 异步处理常驻进程
│   ├── queue/         队列任务目录（任务调度 + AI 流程）
│   └── service/       服务层（cos服务，字典服务）
├── config/            配置文件（如数据库、COS、邮件等）
├── plugin/            webman插件(gateway)
├── public/            公共入口（index.php）
├── routes/            路由文件
├── runtime/           日志文件
├── support/           帮助函数
├── vendor/            Composer 依赖
└── .env               env文件

```

---

### 🔐 鉴权机制说明

- 用户登录成功后，生成并返回唯一 Token，保存在用户表中
- 后续所有请求通过请求头附带 Token
- 系统统一中间件校验该 Token 是否有效（对比数据库）
- 类似 Laravel 的 `Auth::guard()->check()` 鉴权方式
- 支持 Token 失效控制、登录设备限制（可扩展）
- 鉴权完毕全局存放用户信息$request->user()

---

### 🧩 功能模块概览

#### 1. 系统权限模块
- ✅ 菜单管理：可视化新增菜单 + 自动生成路由 + 组件位置
- ✅ 角色管理：支持资源级 + 字段级权限控制
- ✅ 用户管理：注册/登录（邮件验证码登录）+ 角色分配 + 日志审计
- ✅ 动态路由：前后端动态注入 + 防越权直连 + 路由守卫拦截

#### 2. 内容创作模块
- 📝 文章管理：支持 AI Prompt 工程生成内容
- 🏷️ 分类 / 标签管理：支持文章分类/标签归类
- 💬 留言 / 评论 / 相册 / 音乐模块：博客展示所用，支持前后端交互

#### 3. AI 工具模块
- 🔎 582 条 AI 工具库，分类最全
- 🗣️ 语音合成：阿里云 TTS 音色试听与播放
- 🛍️ 电商工作流：选品爬虫 + 商品识别 + AI 卖点生成 + 语音播报
- 🖼️ 生图 / 视频工作流：模板驱动生成任务，分布式执行 + COS 上传

#### 4. 聊天模块
- 💬 WebSocket 实时通信（基于 GatewayWorker）
- 🔒 支持房间加密 / 审核 / 禁言 / 锁房等控制
- 🔄 聊天记录与消息队列管理，拟微信体验

---

### 🧠 高并发任务引擎

- 所有耗时任务（邮件、生成、上传等）全部接入 `redis-queue`
- 支持：
   - ✅ 并发分片
   - ✅ 失败重试
   - ✅ 幂等保障
   - ✅ Supervisor 多进程池化

---

### 📊 压测与性能指标

| 场景        | 并发数 | 响应时间     | 资源占用   |
|-------------|--------|--------------|------------|
| 普通接口     | 2000+  | < 50ms       | CPU 40%，内存 50% |
| AI 生成任务 | 1000+  | < 200ms（含调度） | 无堆积     |
| 压测峰值     | 5000+  | 全程无丢包   | CPU 60%，内存 60% |

---

### 🔧 本地开发

```bash
# 安装依赖
composer install

# 复制配置文件
cp .env.example .env

# 配置 .env 数据库、Redis、COS 等信息

# Windows 启动
php windows.php

# Linux 启动
php start.php start
```

---

### 🌐 生产部署

#### Nginx 配置示例

```nginx
server {
    listen 80;
    listen 443 ssl;
    listen 443 quic;
    listen [::]:443 ssl;
    listen [::]:443 quic;
    http2 on;
    listen [::]:80;
    
    server_name api.your-domain.com;
    
    index index.php index.html;
    root /www/wwwroot/admin_back/public;

    # SSL 配置
    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers EECDH+CHACHA20:EECDH+AES128:RSA+AES128:EECDH+AES256:RSA+AES256;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # HSTS + HTTP/3
    add_header Strict-Transport-Security "max-age=31536000";
    add_header Alt-Svc 'h3=":443"; h3-29=":443"';
    
    error_page 497 https://$host$request_uri;

    # ========== Webman 反向代理 ==========
    location ^~ / {
        proxy_set_header Host $http_host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        
        # 非静态文件转发到 Webman（端口 8787）
        if (!-f $request_filename) {
            proxy_pass http://127.0.0.1:8787;
        }
    }

    # 拒绝直接访问 PHP 文件（安全加固）
    location ~ \.php$ {
        return 404;
    }

    # 禁止访问敏感文件
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|LICENSE|README.md) {
        return 404;
    }

    # 静态资源缓存
    location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$ {
        expires 30d;
        access_log off;
    }

    location ~ .*\.(js|css)?$ {
        expires 12h;
        access_log off;
    }

    access_log  /www/wwwlogs/backend.log;
    error_log   /www/wwwlogs/backend.error.log;
}
```

#### Supervisor 进程管理

```ini
[program:webman]
command=php /www/wwwroot/admin_back/start.php start
directory=/www/wwwroot/admin_back
autostart=true
autorestart=true
stderr_logfile=/var/log/webman.err.log
stdout_logfile=/var/log/webman.out.log
user=www
```

#### 部署步骤

1. 上传代码到服务器
2. 安装依赖：`composer install --no-dev`
3. 配置 `.env` 文件
4. 配置 Nginx 反向代理
5. 配置 Supervisor 管理 Webman 进程
6. 启动服务：`supervisorctl start webman`

---

### 🌐 项目前端地址

- 后台管理系统：[admin_front_ts](../admin_front_ts)
- 展示博客前台：https://gitee.com/zgm2003/admin_blog

---
