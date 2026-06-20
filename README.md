# AI Checker PHP

一个轻量级 AI API 状态监控面板，用于集中监控多个 OpenAI-Compatible 接口渠道的可用性、对话延迟、端点 PING、近期检测轨迹和分组状态。

项目基于 PHP + MySQL 构建，不依赖复杂前端构建流程，适合部署在宝塔、Apache、Nginx、虚拟主机或普通 PHP 环境中。

## 功能特性

- 多分组管理：按供应商或业务维度管理渠道，例如 ChatGPT、Claude、Gemini 等。
- 多渠道监控：每个分组下可配置多个 API Key、API 地址和模型名。
- OpenAI-Compatible 检测：通过 `/chat/completions` 接口检测模型可用性。
- 低 token 消耗：检测消息默认只发送 `hi`，并限制 `max_tokens = 1`。
- 双延迟指标：
  - 对话延迟：真实模型接口响应耗时。
  - 端点 PING：API 入口基础连通耗时。
- 首页状态面板：展示总渠道数、健康数、异常数、最近同步时间。
- 分组折叠：手机端和电脑端都支持分组折叠/展开。
- 状态小点概览：折叠状态下也能快速查看渠道是否正常、延迟或异常。
- 最近检测轨迹：使用红、黄、绿、灰状态条展示近期检测结果。
- 后台管理：支持分组、渠道、站点设置、首页内容编辑。
- 分组图标：支持上传图片，也支持填写 `uploads/...` 站内路径或外部图片 URL。
- 自动安装向导：首次访问自动进入安装流程，生成数据库表和配置文件。
- 兼容旧版本升级：内置数据库字段补齐逻辑。

## 环境要求

- PHP 8.0 或更高版本
- MySQL 5.7+ / MariaDB 10.3+
- PHP 扩展：
  - PDO
  - pdo_mysql
  - cURL
  - GD，上传并裁剪分组图标时需要
- Web Server：
  - Apache，支持 `.htaccess` 更方便
  - Nginx，需要自行配置伪静态或直接访问 PHP 文件

## 快速开始

### 1. 下载项目

```bash
git clone https://github.com/your-name/ai-checker-php.git
cd ai-checker-php
```

也可以直接下载 ZIP 后上传到服务器站点目录。

### 2. 配置运行目录权限

项目会在运行时生成 `data/` 配置目录，也会在上传图标时使用 `uploads/` 目录。

在 Linux 服务器上通常可以执行：

```bash
mkdir -p data uploads/groups
chown -R www:www data uploads
chmod -R 775 data uploads
```

如果你的 PHP-FPM 用户不是 `www`，请改成实际用户，例如 `www-data`、`nginx` 或宝塔面板中的运行用户。

### 3. 创建数据库

安装向导可以自动创建数据库表。你只需要提前准备一个 MySQL 用户，并确保它有目标数据库的建表权限。

推荐创建一个独立数据库，例如：

```sql
CREATE DATABASE ai_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. 访问安装向导

浏览器访问：

```text
https://你的域名/install.php
```

按页面提示填写：

- 数据库地址
- 数据库端口
- 数据库名
- 数据库用户名
- 数据库密码
- 表前缀
- 管理员账号

安装完成后会生成：

```text
data/config.php
data/install.lock
```

如果服务器无法自动写入配置文件，安装向导会显示一段配置代码，按提示手动创建 `data/config.php` 即可。

## 宝塔面板部署建议

1. 新建 PHP 网站。
2. 上传项目到网站根目录。
3. PHP 版本选择 8.0 或更高。
4. 确认 PHP 扩展已启用：`pdo_mysql`、`curl`、`gd`。
5. 给 `data/` 和 `uploads/` 目录写入权限。
6. 访问 `install.php` 完成安装。

如果分组图标上传提示目录不可写，可以执行：

```bash
mkdir -p /www/wwwroot/你的站点目录/uploads/groups
chown -R www:www /www/wwwroot/你的站点目录/uploads
chmod -R 775 /www/wwwroot/你的站点目录/uploads
```

## Apache 配置

项目包含 `.htaccess`，Apache 环境一般可以直接使用。

如果 `.htaccess` 不生效，请确认 Apache 已开启：

```apache
AllowOverride All
```

## Nginx 配置参考

如果使用 Nginx，可以先使用最简单的 PHP 站点配置，直接访问实际 PHP 文件。

示例：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

请根据你的服务器 PHP-FPM sock 或端口调整 `fastcgi_pass`。

## 目录结构

```text
.
├── admin/                 # 后台管理页面
│   ├── channels.php       # 渠道管理
│   ├── editor.php         # 首页内容编辑
│   ├── groups.php         # 分组管理
│   ├── index.php          # 后台首页
│   ├── login.php          # 登录
│   ├── logout.php         # 退出登录
│   └── settings.php       # 网站设置
├── api/                   # 前端和定时检测接口
│   ├── check.php          # 执行检测
│   ├── monitor.php        # 监控接口
│   └── status.php         # 状态数据
├── includes/              # 公共逻辑
│   ├── auth.php           # 登录认证
│   ├── db.php             # 数据库封装
│   └── functions.php      # 工具函数、检测逻辑、建表升级
├── config.php             # 配置加载器
├── index.php              # 首页状态面板
├── install.php            # 安装向导
├── .htaccess              # Apache rewrite 和访问控制
└── .gitignore             # Git 忽略配置
```

运行后会生成：

```text
data/                   # 本地配置和安装锁，不应提交到 Git
uploads/                # 上传文件，不建议提交到 Git
```

## 检测机制

每个渠道需要配置：

- API Key
- API 地址，例如 `https://api.openai.com/v1`
- 模型名，例如 `gpt-4o-mini`

检测时会请求：

```text
{api_url}/chat/completions
```

请求内容为：

```json
{
  "model": "你的模型名",
  "messages": [
    { "role": "user", "content": "hi" }
  ],
  "max_tokens": 1
}
```

这样可以在确认模型可用性的同时尽量降低 token 消耗。

## 状态颜色说明

首页状态使用以下语义：

- 绿色：正常、稳定
- 黄色：延迟偏高，需要关注
- 红色：异常、超时、不可用
- 灰色：暂无数据

折叠分组中的渠道小点也使用同样语义。

## 后台入口

安装完成后访问：

```text
/admin/login.php
```

登录后可以管理：

- 分组
- 渠道
- 网站设置
- 首页 Hero 文案
- 首页自定义内容

## 安全建议

- 不要把 `data/config.php` 提交到 GitHub。
- 不要把真实 API Key 提交到仓库。
- 不建议把 `uploads/` 上传内容提交到仓库。
- 生产环境建议关闭 PHP 错误直接输出。
- 建议给后台路径配置 HTTPS。
- 如果部署在公网，请使用强密码保护管理员账号。

## Git 忽略建议

项目已经忽略运行期数据，建议保持以下内容不提交：

```text
data/
uploads/
*.lock
```

## 开源协议

本项目遵守 MIT 开源协议。

你可以自由使用、复制、修改、合并、发布、分发、再授权或销售本项目副本，但需要保留原始版权声明和许可声明。

## License

MIT License