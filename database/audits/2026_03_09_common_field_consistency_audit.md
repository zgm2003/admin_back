# Common Field Consistency Audit (2026-03-09)

## Baseline conventions

- Primary keys use `int unsigned` for normal business tables.
- High-churn append-only tables may keep `bigint unsigned`.
- `is_del` should use `tinyint unsigned` with `1 = deleted`, `2 = normal`.
- `status` should use `tinyint unsigned` when the domain state space is positive-only.
- `created_at` / `updated_at` should exist on business tables.

## Approved special cases

### 1. `address.parent_id`

- `parent_id` remains signed because root nodes use `-1` as the sentinel.
- Code dependency:
  - `app/service/AddressService.php` stops upward traversal on `parent_id === -1`.
- This can only be normalized after a coordinated data + code refactor to `NULL` or `0` roots.

### 2. `permission.parent_id`

- Current type remains signed because root permissions also use `-1`.
- Code dependency:
  - `app/module/Permission/PermissionModule.php`
  - `app/dep/Permission/PermissionDep.php`
- Same rule as `address.parent_id`: do not force unsigned before removing the sentinel design.

### 3. `goods.platform`

- Current type stays signed because `-1` is used as an unset/unknown default.
- If this enum is redesigned later, prefer `tinyint unsigned` plus an explicit nullable/unknown state.

### 4. `role.permission_id`

- Current type is `varchar(255)` because it stores a JSON-encoded permission-id array.
- This is a legacy denormalized design, not a true common-field exception.
- Best long-term direction:
  - migrate to `json`, or
  - split to a pivot table such as `role_permissions`.

### 5. `bigint unsigned` append-only tables

These are acceptable as growth-oriented exceptions:

- `ai_messages`
- `ai_run_steps`
- `ai_runs`
- `chat_messages`
- `cron_task`
- `cron_task_log`
- `operation_logs`
- `system_settings`
- `upload_driver`
- `upload_rule`
- `upload_setting`
- `user_sessions`
- `users_login_log`

Rationale:
- they are append-heavy or history-like tables,
- they are safer to keep wide than to later widen under production load.

## Concrete issues found in this batch

- `users_quick_entry.permission_id = 0` had live dirty data.
- Several tables still used signed `tinyint` / `tinyint(1)` for `status` and `is_del`.
- Several key chains still used signed `int` although the referenced ids are non-negative only.
- `notification_task.idx_status_send` did not include `is_del`, while code always filters by it.
- `users_quick_entry` lacked an index matching the duplicate-check query path.

## Batch outcome

This audit drives `database/migrations/2026_03_09_normalize_common_flags_and_reference_keys.sql` and `database/migrations/2026_03_09_normalize_address_identity_and_timestamps.sql`.
