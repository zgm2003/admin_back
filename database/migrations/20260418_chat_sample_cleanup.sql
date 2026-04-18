-- 手动样本清理脚本：
-- 仅用于清理当前 chat 业务的测试/样本数据，不建议在保留正式聊天记录的环境直接执行。

UPDATE chat_messages
SET
  is_del = 1,
  updated_at = NOW()
WHERE is_del = 2;

UPDATE chat_participants
SET
  is_del = 1,
  updated_at = NOW()
WHERE is_del = 2;

UPDATE chat_conversations
SET
  is_del = 1,
  updated_at = NOW()
WHERE is_del = 2;

UPDATE chat_contacts
SET
  is_del = 1,
  updated_at = NOW()
WHERE is_del = 2;
