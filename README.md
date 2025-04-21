# 智澜 AI 平台 — 后端（Webman）README

## 一、项目概述
“智澜”是一款面向企业级场景的全链路智能工作流管理与内容创作平台。后端基于 **Webman**（Workerman 驱动）自研分层框架（DEP → Controller → Model → Module），结合 Redis-Queue、Supervisor、GatewayWorker、腾讯云 COS、阿里云 SDK 等构建高并发、低延时、易扩展的服务能力。

主要功能包括：
- 动态权限自治（RBAC + 前后端双重拦截）
- 异步任务调度（Redis-Queue）
- 实时通信（GatewayWorker WebSocket）
- AI 流水线 Orchestration
- 云服务集成（COS 上传、阿里云语音合成／视频生成／大模型推理）

## 二、技术栈
- **PHP** >= 8.1
- **Webman** ^2.1（基于 Workerman 高性能常驻进程）
- **Redis**（消息队列与缓存）
- **GatewayWorker**（WebSocket 实时通信）
- **Composer**（依赖管理）
- **MySQL**（关系数据库）
- **Monolog + Jaeger**（日志与分布式追踪）
- **GuzzleHttp**（并发 HTTP 爬虫与分片上传）

## 三、环境准备
1. 安装 PHP 8.1 及扩展：
   ```bash
   sudo apt install php8.1 php8.1-cli php8.1-fpm php8.1-pdo php8.1-mbstring php8.1-xml php8.1-curl php8.1-redis
   ```
2. 安装 Composer：
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```
3. 安装并启动 Redis：
   ```bash
   sudo apt install redis-server
   sudo systemctl enable --now redis-server
   ```

## 四、项目安装
1. 克隆代码仓库：
   ```bash
   git clone https://gitee.com/zgm2003/admin_back
   cd admin_back
   ```
2. 安装依赖：
   ```bash
   composer install --no-dev -o
   ```
3. 复制环境配置：
   ```bash
   cp .env.example .env
   ```
4. 修改 `.env`，配置数据库、Redis、COS、阿里云等秘钥：
   ```ini
   # MySQL
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=zhilan
   DB_USERNAME=zhilan_user
   DB_PASSWORD=secret

   # Redis
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379

   # COS
   COS_REGION=ap-guangzhou
   COS_BUCKET=zhilan-bucket
   COS_SECRET_ID=YOUR_COS_SECRET_ID
   COS_SECRET_KEY=YOUR_COS_SECRET_KEY

   # 阿里云 SDK
   ALIBABA_CLOUD_ACCESS_KEY_ID=YOUR_AK_ID
   ALIBABA_CLOUD_ACCESS_KEY_SECRET=YOUR_AK_SECRET
   ```
5. 生成应用密钥（可选）：
   ```bash
   php bin/hyperf.php key:generate
   ```

## 五、目录结构
```
backend/
├── app/
│   ├── Controller/        # 控制器层
│   ├── Model/             # 实体与业务逻辑模型
│   ├── Module/            # 业务模块实现
│   ├── Service/           # 服务层（API 封装、SDK 调用）
│   ├── Middleware/        # 中间件（鉴权、日志等）
│   └── View/Components/   # 后端渲染组件
├── config/                # 配置文件
├── public/                # Web 入口
├── runtime/               # 运行时缓存、日志
├── support/               # 辅助函数与插件安装脚本
├── vendor/                # Composer 依赖
└── .env                   # 环境变量配置
```

## 六、核心模块说明
### 1. DEP → Controller → Model → Module 分层
- **DEP**：数据访问层，统一封装数据库操作，支持读写分离
- **Controller**：接收请求、初步校验、结果返回
- **Model**：实体类与领域逻辑
- **Module**：业务实现，按功能域划分，例如用户、权限、文章、AI 流水线模块

### 2. 动态权限与双端拦截
- 后端中间件 `AuthMiddleware` 校验 Token & RBAC
- 登录后生成角色-菜单-路由 JSON
- 前端基于该 JSON 动态注入路由，前端 `RouteGuard` 验证权限
- 支持优先级、重试、超时、幂等等策略

### 4. 实时通信
- GatewayWorker 作为 WebSocket 服务器
- Room 管理、心跳检测、消息 ACK 机制

### 5. 云服务集成
- **腾讯云 COS**：后端签发上传令牌，前端直传或服务端直传
- **阿里云 SDK**：百炼大模型、TTS、视频生成
- **GuzzleHttp**：并发爬虫与分片上传

## 七、运行与部署
启动 Webman 服务(已经封装跨域请求中间件)：
   ```bash
   php start.php start
   ```

## 八、常见问题
- **任务丢失**：请检查 Redis 连接与队列配置，确保 Supervisor 进程正常运行
- **WebSocket 断连**：检查 GatewayWorker 配置与防火墙、端口映射

## 九、参与贡献
1. Fork 本仓库并创建分支：`feat/your-feature`
2. 提交代码 & 完善测试
3. 提交 Pull Request，描述功能与变更点

欢迎提交 Issues 与建议，共同完善“智澜 AI 平台”！