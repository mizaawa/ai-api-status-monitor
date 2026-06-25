-- Migration: 在分组中添加默认 API 配置
-- 兼容已有数据：字段可为空，渠道优先使用自身配置，其次继承分组配置

-- 添加分组默认接口和密钥字段（可为空，向后兼容）
ALTER TABLE `ai_monitor_groups` 
ADD COLUMN `default_api_url` VARCHAR(500) DEFAULT NULL COMMENT '默认 API 接口' AFTER `provider_name`;

ALTER TABLE `ai_monitor_groups` 
ADD COLUMN `default_api_key` VARCHAR(500) DEFAULT NULL COMMENT '默认 API 密钥' AFTER `default_api_url`;

-- 渠道表的 api_key 和 api_url 改为可空（兼容继承模式）
ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_key` VARCHAR(500) DEFAULT NULL;

ALTER TABLE `ai_monitor_channels` 
MODIFY COLUMN `api_url` VARCHAR(500) DEFAULT NULL;