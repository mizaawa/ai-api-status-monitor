# 快速更新指南

## 更新内容

新增**分组默认配置**功能，允许在分组中统一设置 API 接口和密钥，渠道可继承使用。

## 更新步骤

### 1. 备份数据库（重要！）

```bash
# 使用命令行备份
mysqldump -u用户名 -p 数据库名 > backup_$(date +%Y%m%d_%H%M%S).sql

# 或者使用宝塔面板的数据库备份功能
```

### 2. 替换文件

将以下文件覆盖到服务器：

```
web/
├── includes/functions.php          (已修改)
├── admin/groups.php                (已修改)
├── admin/channels.php              (已修改)
└── migrations/
    └── 001_add_group_defaults.sql  (新增)
```

### 3. 执行数据库升级

#### 方式 A：自动升级（推荐）

直接访问后台任意页面，系统会自动添加新字段。

如果看到错误提示，复制提示中的 SQL 语句，在数据库中执行即可。

#### 方式 B：手动执行 SQL

打开 phpMyAdmin 或命令行，执行：

```sql
-- 请将 ai_monitor_ 替换为你的实际表前缀

ALTER TABLE `ai_monitor_groups` 
ADD COLUMN `default_api_url` VARCHAR(500) DEFAULT NULL;

ALTER TABLE `ai_monitor_groups` 
ADD COLUMN `default_api_key` VARCHAR(500) DEFAULT NULL;

ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_key` VARCHAR(500) DEFAULT NULL;

ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_url` VARCHAR(500) DEFAULT NULL;
```

### 4. 验证更新

1. 访问后台 → 分组管理，确认可以看到"默认 API 接口"和"默认 API 密钥"字段
2. 访问渠道管理，确认字段提示已更新为"留空则继承分组默认配置"
3. 测试创建新渠道，留空 API 配置，检测功能正常

## 如何使用

### 统一配置模式（推荐）

1. 在**分组管理**中设置默认配置：
   - 默认 API 接口：`https://api.openai.com/v1`
   - 默认 API 密钥：`sk-xxxxx`

2. 在**渠道管理**中只填写模型：
   - API Key：留空（继承分组）
   - API 接口：留空（继承分组）
   - 模型：`gpt-4o`（必填）

### 混合配置模式

某些渠道使用分组默认配置，某些渠道使用独立配置：

- 继承分组配置的渠道：API Key 和接口留空
- 独立配置的渠道：填写自己的 API Key 和接口

## 常见问题

**Q：更新后原有渠道还能正常工作吗？**  
A：完全正常，已有配置不受影响。

**Q：如何确认使用了哪个配置？**  
A：检测失败时会显示"缺少 API Key 或接口地址"，说明渠道和分组都没有配置。

**Q：修改分组配置后需要重启服务吗？**  
A：不需要，下次检测时自动生效。

## 获取表前缀

如果不确定表前缀，查看 `data/config.php` 文件：

```php
'db_prefix' => 'ai_monitor_',  // 这就是你的表前缀
```

## 问题排查

如果遇到问题：

1. 检查 `includes/functions.php` 中的 `ensure_monitor_schema_columns()` 函数是否包含新字段
2. 在数据库中执行 `SHOW COLUMNS FROM ai_monitor_groups;` 确认字段是否存在
3. 查看 PHP 错误日志：`/www/wwwlogs/` (宝塔面板)

## 技术支持

详细文档请参考 `UPGRADE_GUIDE.md`