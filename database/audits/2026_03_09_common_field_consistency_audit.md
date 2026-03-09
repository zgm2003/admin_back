# Common Field Consistency Audit (2026-03-09)

## Baseline conventions

- Primary keys use `int unsigned` for normal business tables.
- High-churn append-only tables may keep `bigint unsigned`.
- `is_del` should use `tinyint unsigned` with `1 = deleted`, `2 = normal`.
- `status` should use `tinyint unsigned` when the domain state space is positive-only.
- `created_at` / `updated_at` should exist on business tables.

## Approved special cases

### 1. `address.parent_id` and `permission.parent_id`

- Historical state: both columns used signed `int` because root nodes were stored as `-1`.
- This follow-up removes the legacy sentinel and normalizes both roots to `0`.
- Applied in:
  - `database/migrations/2026_03_09_normalize_root_parent_contracts.sql`
- Code contracts now align on `0` as the only root parent id, while a small compatibility layer still tolerates legacy cached `-1` values during transition.

### 2. `goods.platform`

- Current type stays signed because `-1` is used as an unset/unknown default.
- If this enum is redesigned later, prefer `tinyint unsigned` plus an explicit nullable/unknown state.

### 3. `role.permission_id`

- Historical state: `varchar(255)` storing a JSON-encoded permission-id array.
- This is a legacy denormalized design, not a true common-field exception.
- This batch upgrades the storage contract to native `json` via `database/migrations/2026_03_09_upgrade_role_permission_payload_to_json.sql`.
- Remaining long-term direction:
  - keep `json` as a transitional contract, or
  - split to a pivot table such as `role_permissions`.

### 4. `bigint unsigned` append-only tables

These are acceptable as growth-oriented exceptions:

- `ai_messages`
- `ai_run_steps`
- `ai_runs`
- `chat_messages`
- `cron_task`
- `cron_task_log`
- `operation_logs`
- `system_settings`
- `user_sessions`
- `users_login_log`

### 5. `upload_*` identity chain

- `upload_driver`, `upload_rule`, and `upload_setting` were previously treated as bigint exceptions.
- Re-checking the code and live row counts shows they are configuration tables, not append-only growth tables.
- This batch normalizes them back to `int unsigned` via `database/migrations/2026_03_09_normalize_upload_identity_chain.sql`.

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


## Index redundancy follow-up (round 1)

Confirmed redundant and safe to remove in this round:

- `ai_assistant_tools.idx_assistant_id`
  - code paths always read either `assistant_id + is_del + status` or `assistant_id + tool_id`
  - covered by `idx_assistant_del_status` and `uniq_assistant_tool`
- `ai_tools.idx_is_del`
  - code paths already filter `is_del` together with `status`, or read by primary-key ordering
  - covered by `idx_del_status_id`
- `upload_setting.idx_driver`
  - duplicate-check and list query paths are satisfied by the left-prefix of `uniq_driver_rule`

Reviewed but intentionally kept for now:

- `permission.idx_platform`
  - logically prefix-covered by `uniq_platform_code(platform, code)`
  - not dropped in this batch because the composite unique index is materially wider than the single-column index
  - keep until production-like row counts or query evidence prove the narrow index has no value

This follow-up audit drives `database/migrations/2026_03_09_drop_redundant_prefix_indexes_round1.sql`.


## UTF-8 normalization follow-up

Remaining `utf8mb3` storage was concentrated in:

- `notification_task`
- `notifications`
- `permission`
- `role`
- `users`

This follow-up normalizes them to `utf8mb4` via `database/migrations/2026_03_09_normalize_utf8mb3_to_utf8mb4.sql`.

Notes:

- `notification_task` / `notifications` keep the `unicode_ci` family when moving to `utf8mb4`.
- `permission` / `role` / `users` keep the `general_ci` family when moving to `utf8mb4`.
- `role.permission_id` had a corrupted column comment after the JSON migration and is corrected in the same batch.


## Tinyint normalization follow-up

Positive-only signed `tinyint` / `tinyint(1)` columns were re-checked against live data and code usage.

This batch normalizes them to `tinyint unsigned` via `database/migrations/2026_03_09_normalize_positive_tinyint_flags.sql` for:

- `ai_prompts.is_favorite`
- `chat_participants.is_pinned`
- `notification_task.type`
- `notification_task.level`
- `notification_task.target_type`
- `notifications.type`
- `notifications.level`
- `notifications.is_read`
- `operation_logs.is_success`
- `permission.type`
- `permission.keep_alive`
- `permission.show_menu`
- `tauri_version.is_latest`
- `tauri_version.force_update`
- `test.type`
- `test.sex`
- `test.is_vip`
- `test.is_hot`
- `user_profiles.sex`
- `users_login_log.is_success`

Remaining signed tinyint exception after this batch:

- `goods.platform`
  - kept signed because the domain still uses `-1` as unknown/unset


## Nullable common-field and comment-encoding follow-up

A final consistency sweep found a small tail of common-field drift:

- `notification_task.status` and `notification_task.is_del` were nullable even though query paths treat them as required flags.
- `chat_messages.updated_at` and `tauri_version.created_at` / `tauri_version.updated_at` were nullable despite always being filled by defaults.
- Several schema comments had already become mojibake in the live database:
  - `address.is_del`
  - `cron_task_log.is_del`
  - `tauri_version.is_del`
  - `user_profiles.is_del`
  - `users_login_log.is_del`
  - `users_login_log.updated_at`

This follow-up normalizes them via `database/migrations/2026_03_09_normalize_nullable_common_fields_and_comment_encoding.sql`.

Post-check result:

- every base table now has `is_del`, `created_at`, and `updated_at`
- no common field in `status`, `is_del`, `created_at`, or `updated_at` remains nullable
- no mojibake schema comments remain in the live database
- the only remaining signed numeric exception in this sweep is `goods.platform`


## Root parent contract follow-up

This follow-up removes the last signed `parent_id` exceptions from the live schema.

Normalized via `database/migrations/2026_03_09_normalize_root_parent_contracts.sql`:

- `address.parent_id`
- `permission.parent_id`

What changed:

- live data root rows are rewritten from `-1` to `0`
- both columns are now `int unsigned not null default 0`
- code paths that build trees and upward paths now use `0` as the canonical root parent id
- a narrow compatibility layer still treats legacy `-1` as root if stale cached data is encountered before cache refresh

Post-check result:

- no signed numeric `id` / `_id` / `parent_id` columns remain in the live schema
- the remaining intentional signed exception in this area is only `goods.platform`
