# AI API Status Monitor

一个轻量级的 OpenAI-Compatible 接口状态监控面板，适合监控多个渠道的可用性、延迟、端点连通性以及最近检测结果。

- 项目仓库：https://github.com/mizaawa/ai-api-status-monitor
- 在线演示：https://check.zakuzaku.cc
- API 中转站：https://ai.zakuzaku.cc/

## 项目特性

- 支持多分组管理，方便按业务线、供应商或环境分类
- 支持多渠道监控，适配 OpenAI-Compatible 接口
- 自动检测接口可用性、响应延迟、端点连通性
- 支持前台状态展示与后台管理
- 支持安装向导，首次访问自动完成基础部署
- 支持旧数据兼容升级
- 无需复杂构建流程，上传即用，适合宝塔、Nginx、Apache、虚拟主机环境

## 技术栈

- 后端：PHP 8.0+
- 数据库：MySQL 5.7+ / MariaDB 10.3+
- 前端：原生 PHP 模板 + Tailwind CDN
- 执行方式：Web 请求 + Cron 定时任务

## 目录结构

```text
web/
├── admin/                后台管理页
├── api/                  对外接口与监控入口
├── includes/             公共函数、数据库、认证
├── migrations/           数据升级脚本
├── config.php            配置加载器与安装状态判断
├── index.php             前台首页
├── install.php           安装向导
├── README.md             项目说明
└── .htaccess             Apache 伪静态与路由规则
```

运行后还会生成：

```text
data/                     配置文件与安装锁
uploads/                  上传目录（如有图标上传）
```

## 功能说明

### 前台

- 展示各分组下的渠道状态
- 显示最近检测结果、延迟、错误信息
- 提供分组聚合视图，方便快速查看整体健康情况

### 后台

- 管理分组
- 管理渠道
- 编辑站点设置
- 编辑首页内容
- 管理登录入口

### 监控

- `api/check.php`：手动触发检测
- `api/status.php`：查询状态数据
- `api/monitor.php`：定时监控入口，适合 Cron 调用

## 环境要求

- PHP 8.0 或更高版本
- MySQL 5.7+ 或 MariaDB 10.3+
- PHP 扩展：
  - `pdo`
  - `pdo_mysql`
  - `curl`
  - `mbstring`
  - `openssl`
  - `gd`（如需图片上传/处理）
- Web 服务器：Nginx 或 Apache

## 安装方式

### 方式一：宝塔面板部署

1. 在宝塔中新建站点，PHP 版本建议选择 8.0 及以上。
2. 上传本项目到站点根目录。
3. 确保 PHP 已开启以下扩展：`pdo_mysql`、`curl`、`mbstring`、`openssl`、`gd`。
4. 创建 MySQL 数据库，并准备好数据库账号。
5. 确保站点目录具有写入权限，尤其是：
   - `data/`
   - `uploads/`
6. 访问 `https://你的域名/install.php` 完成安装。

### 方式二：传统服务器部署

1. 克隆仓库或上传代码到网站目录。
2. 配置 PHP 运行环境与数据库。
3. 配置 Web 服务器伪静态规则。
4. 访问安装向导完成初始化。

## 宝塔部署步骤

### 1. 新建站点

- 域名：`check.zakuzaku.cc`
- 运行目录：项目根目录
- PHP 版本：8.0+

### 2. 上传代码

将仓库文件上传到站点根目录，保持以下结构：

```text
/public 不存在，当前项目为扁平结构
index.php
install.php
admin/
api/
includes/
config.php
.htaccess
```

### 3. 配置伪静态

如果使用 Apache，可直接使用仓库自带 `.htaccess`。

如果使用 Nginx，可参考：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

location ^~ /data/ {
    deny all;
    return 404;
}
```

请按你的服务器实际 PHP-FPM sock 或端口修改 `fastcgi_pass`。

### 4. 设置目录权限

```bash
mkdir -p data uploads
chmod -R 775 data uploads
```

如果你的 PHP-FPM 用户不是 `www`，请按实际用户修改属主。

### 5. 访问安装向导

打开：

```text
https://check.zakuzaku.cc/install.php
```

按页面提示填写数据库信息和管理员账号。

安装完成后会生成：

```text
data/config.php
data/install.lock
```

如果服务器不允许自动写入配置文件，安装页会提示手动创建 `data/config.php`。

## 安装页面填写项

- 数据库主机：通常为 `127.0.0.1`
- 数据库端口：通常为 `3306`
- 数据库名：你创建的数据库名
- 数据库用户名：数据库账号
- 数据库密码：数据库密码
- 表前缀：默认 `ai_`，可按需修改
- 管理员账号：后台登录用户名
- 管理员密码：后台登录密码

## 定时任务配置

如果你希望“完全无人访问也照样定时跑”，需要配置系统级 Cron，调用 `api/monitor.php`。

### Linux / Debian / Ubuntu

编辑当前用户的计划任务：

```bash
crontab -e
```

加入：

```bash
*/1 * * * * curl -fsS --connect-timeout 10 --max-time 30 https://check.zakuzaku.cc/api/monitor.php >/dev/null 2>&1
```

如果你的服务器不方便直接请求公网域名，也可以本机调用：

```bash
*/1 * * * * /usr/bin/curl -fsS --connect-timeout 10 --max-time 30 https://check.zakuzaku.cc/api/monitor.php >/dev/null 2>&1
```

### 宝塔面板定时任务

1. 打开宝塔面板
2. 进入「计划任务」
3. 新建任务类型选择「Shell脚本」
4. 执行周期选择「每分钟」
5. 脚本内容填写：

```bash
curl -fsS --connect-timeout 10 --max-time 30 https://check.zakuzaku.cc/api/monitor.php >/dev/null 2>&1
```

> 说明：脚本执行后不会在终端显示内容，这是正常的。

## 更新与升级

如果你是从旧版本升级，可以参考仓库中的：

- `README_UPDATE.md`
- `UPGRADE_GUIDE.md`

一般流程是：

1. 备份数据库
2. 替换代码文件
3. 打开后台或访问相关页面触发自动升级
4. 如有提示，手动执行对应 SQL

## 常见问题

### 1. 安装页打不开

确认以下项目：

- 域名解析是否正确
- 站点根目录是否指向项目目录
- `.htaccess` 是否生效（Apache）
- Nginx 是否已配置伪静态

### 2. 数据库连接失败

检查：

- 数据库主机、端口、用户名、密码是否正确
- 数据库用户是否有建表权限
- 数据库服务是否正常运行

### 3. 后台登录后又跳回登录页

检查：

- `data/` 是否可写
- PHP session 是否正常工作
- 浏览器是否禁用了 Cookie
- 是否通过 HTTPS/代理导致 Cookie 作用域异常

### 4. 定时任务没有执行

检查：

- Cron 是否已启动
- 任务是否写入成功
- 服务器是否允许外部 HTTP 请求
- `api/monitor.php` 是否能在浏览器中正常访问

### 5. 页面样式异常

检查：

- CDN 是否可访问
- 浏览器控制台是否有报错
- PHP 是否正确输出页面内容

## 安全建议

- 不要把 `data/config.php` 提交到仓库
- 不要把真实数据库密码提交到仓库
- 不要把真实 API Key 明文写进公开代码
- 生产环境建议开启 HTTPS
- 后台建议增加强密码和访问限制
- 建议限制 `data/` 目录的外部访问

## License

MIT License

## 作者

- 项目维护：mizaawa
- 仓库地址：https://github.com/mizaawa/ai-api-status-monitor