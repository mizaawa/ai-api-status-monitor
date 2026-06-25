# 升级指南：分组默认配置功能

## 功能说明

新版本支持在**分组管理**中设置默认 API 接口和密钥，渠道可以继承分组的配置，无需重复填写。

### 优势
- **简化配置**：同一分组下的多个渠道（如不同模型）共享同一套 API 配置
- **统一管理**：修改分组的默认配置，所有继承该配置的渠道自动生效
- **向后兼容**：已有渠道的配置不受影响，可以选择性迁移

## 数据库升级

### 方式 1：自动升级（推荐）

系统会自动检测并添加新字段。如果自动添加失败，会在页面顶部显示手动 SQL 语句。

访问后台任意页面（如分组管理），如果看到错误提示，按照提示执行 SQL 即可。

### 方式 2：手动执行 SQL

在 phpMyAdmin 或命令行中执行以下 SQL（请根据实际表前缀修改）：

```sql
-- 假设你的表前缀是 ai_monitor_，请替换为实际前缀

-- 为分组表添加默认配置字段
ALTER TABLE `ai_monitor_groups` 
ADD COLUMN `default_api_url` VARCHAR(500) DEFAULT NULL COMMENT '默认 API 接口';

ALTER TABLE `ai_monitor_groups` 
ADD COLUMN `default_api_key` VARCHAR(500) DEFAULT NULL COMMENT '默认 API 密钥';

-- 渠道表的字段改为可空（兼容继承模式）
ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_key` VARCHAR(500) DEFAULT NULL;

ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_url` VARCHAR(500) DEFAULT NULL;
```

**注意**：如果你的表前缀不是 `ai_monitor_`，请替换 SQL 中的表名。

查看表前缀的方法：
1. 打开 `data/config.php`
2. 查找 `'db_prefix'` 对应的值
3. 例如：`'db_prefix' => 'ai_'` 表示前缀是 `ai_`

## 使用方式

### 1. 在分组中设置默认配置

进入 **后台 → 分组管理**，编辑或新建分组：

- **默认 API 接口**：填写接口地址，如 `https://api.openai.com/v1`
- **默认 API 密钥**：填写 API Key，如 `sk-xxxxx`

保存后，该分组下的所有渠道都可以继承这个配置。

### 2. 在渠道中使用

进入 **后台 → 渠道管理**，新建或编辑渠道：

- **API Key**：留空则自动继承分组的默认密钥
- **API 接口**：留空则自动继承分组的默认接口
- **模型**：必填，填写具体模型名称（如 `gpt-4o`、`claude-3-5-sonnet`）

### 3. 优先级规则

检测时的配置优先级：
1. **渠道自身配置** - 如果渠道填写了 API Key 和接口，优先使用
2. **分组默认配置** - 如果渠道留空，则继承分组的默认配置
3. **检测失败** - 如果两者都没有，检测会失败并提示"缺少 API Key 或接口地址"

## 迁移建议

### 场景 1：新项目
直接在分组中设置默认配置，渠道只填写模型即可。

### 场景 2：已有项目
1. **保持现状**：不做任何修改，已有渠道的配置继续有效
2. **逐步迁移**：
   - 在分组中设置默认配置
   - 编辑渠道，清空 API Key 和接口字段
   - 保存后渠道会自动继承分组配置
3. **混合使用**：某些渠道使用特殊配置，保留其自身配置；其他渠道清空以继承分组配置

## 常见问题

### Q1：升级后现有渠道会受影响吗？
不会。已有渠道的配置完全保留，继续正常工作。

### Q2：如果分组和渠道都没有配置会怎样？
检测时会返回错误"缺少 API Key 或接口地址"，渠道状态显示为失败。

### Q3：可以部分渠道继承，部分渠道自定义吗？
可以。每个渠道独立决定是否继承分组配置。

### Q4：修改分组的默认配置后，渠道会立即生效吗？
会。下次检测时，继承该配置的渠道会使用新的分组配置。

### Q5：如何知道渠道使用的是哪个配置？
- 如果渠道的 API Key 和接口为空，则使用分组配置
- 如果渠道填写了配置，则使用自身配置

## 回滚方法

如果需要回退到旧版本：

```sql
-- 删除新增字段（谨慎操作）
ALTER TABLE `ai_monitor_groups` DROP COLUMN `default_api_url`;
ALTER TABLE `ai_monitor_groups` DROP COLUMN `default_api_key`;

-- 恢复渠道表字段为必填
ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_key` VARCHAR(500) NOT NULL;

ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_url` VARCHAR(500) NOT NULL DEFAULT 'https://api.openai.com/v1';
```

**警告**：回滚前请确保所有渠道都已填写自己的 API 配置，否则会导致字段约束错误。

## 技术细节

### 数据库变更
- `groups` 表新增 `default_api_url` 和 `default_api_key` 字段（可空）
- `channels` 表的 `api_key` 和 `api_url` 改为可空

### 代码变更
- `functions.php` 中的 `check_ai_api()` 函数支持分组配置继承
- `groups.php` 和 `channels.php` 表单增加/简化相应字段
- 自动字段检测逻辑已更新

### 兼容性
- 支持 MySQL 5.7+ / MariaDB 10.2+
- PHP 8.0+ 
- 完全向后兼容已有数据