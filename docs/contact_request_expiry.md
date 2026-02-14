# 好友请求过期机制

## 功能说明

为了避免好友请求永久处于"待确认"状态，系统实现了自动过期清理机制。

## 实现方案

### 1. 定时任务清理
- **任务名称**：`clean_expired_contact_request`
- **执行频率**：每天凌晨 2:00（可配置）
- **过期时间**：7 天（可配置）
- **处理逻辑**：将超过 7 天未处理的待确认好友请求标记为已删除（软删除）

### 2. 为什么选择定时任务而不是延迟队列？

**定时任务的优势：**
- 系统已有完善的定时任务框架（`BaseCronTask`）
- 批量处理，性能更好
- 统一管理，便于维护
- 有完整的日志记录

**延迟队列的劣势：**
- 需要为每个请求创建延迟任务，开销大
- 好友请求不是高实时性场景，不需要精确到秒
- 增加系统复杂度

## 部署步骤

### 1. 执行数据库迁移
```bash
mysql -u用户名 -p数据库名 < admin_back/migrations/add_contact_request_expiry.sql
```

### 2. 重启 Webman 服务
```bash
# Linux
php start.php restart

# Windows
# 关闭当前进程，重新运行
php windows.php
```

### 3. 验证任务是否注册成功
- 登录后台管理系统
- 进入"开发工具" -> "定时任务"
- 查看是否有"清理过期好友请求"任务
- 状态应为"启用"

## 配置说明

### 修改过期天数
编辑 `admin_back/app/process/CleanExpiredContactRequestTask.php`：
```php
protected int $expireDays = 7; // 改为你想要的天数
```

### 修改执行频率
在后台管理系统中修改 cron 表达式，或直接修改数据库：
```sql
UPDATE cron_task 
SET cron = '0 0 */6 * * *'  -- 改为每6小时执行一次
WHERE name = 'clean_expired_contact_request';
```

常用 cron 表达式（workerman/crontab 格式：秒 分 时 日 月 周）：
- `0 0 2 * * *` - 每天凌晨 2:00
- `0 0 */6 * * *` - 每 6 小时
- `0 0 * * * *` - 每小时
- `0 */30 * * * *` - 每 30 分钟

## 监控与日志

### 查看执行日志
```sql
SELECT * FROM cron_task_log 
WHERE task_name = 'clean_expired_contact_request' 
ORDER BY start_time DESC 
LIMIT 10;
```

### 查看清理统计
日志中的 `result` 字段会显示清理数量，例如：
```
清理了 15 条过期好友请求
```

## 注意事项

1. **软删除**：过期的请求只是标记为删除（`is_del=1`），不会物理删除数据
2. **双向记录**：好友请求是双向的（A加B会创建两条记录），清理时会同时处理
3. **已确认的不受影响**：只清理 `status=1`（待确认）的请求
4. **可恢复**：如需恢复，可以将 `is_del` 改回 2

## 用户体验优化建议

### 前端显示优化
可以在前端显示好友请求的剩余有效期：
```typescript
// 计算剩余天数
const daysLeft = 7 - Math.floor((Date.now() - new Date(request.created_at).getTime()) / 86400000)
// 显示：还有 X 天过期
```

### 过期提醒
可以在请求即将过期时（如剩余1天）发送通知提醒用户处理。
