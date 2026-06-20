<?php
/**
 * 分组管理
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

ensure_monitor_schema_columns();

function group_upload_permission_message(string $rootPath): string {
    $uploadPath = rtrim($rootPath, '/\\') . '/uploads';
    $groupPath = $uploadPath . '/groups';
    return "上传目录无法写入，系统已尝试自动创建和修复权限。\n" .
        "请在服务器执行：\n" .
        "mkdir -p {$groupPath}\n" .
        "chown -R www:www {$uploadPath}\n" .
        "chmod -R 775 {$uploadPath}\n" .
        "如果 PHP-FPM 用户不是 www，请替换为实际运行用户（如 www-data 或 nginx）。";
}

function ensure_group_upload_dir(): array {
    $root = dirname(__DIR__);
    $uploadRoot = $root . '/uploads';
    $groupDir = $uploadRoot . '/groups';

    foreach ([$uploadRoot, $groupDir] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return ['ok' => false, 'error' => group_upload_permission_message($root)];
        }
        if (is_dir($dir)) {
            @chmod($dir, 0775);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            @chmod($dir, 0777);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return ['ok' => false, 'error' => group_upload_permission_message($root)];
        }
    }

    $denyFile = $uploadRoot . '/.htaccess';
    if (!is_file($denyFile) && is_writable($uploadRoot)) {
        @file_put_contents($denyFile, "php_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n");
    }

    return ['ok' => true, 'dir' => $groupDir];
}

function create_group_icon_image(string $tmpName, int $type) {
    return match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpName),
        IMAGETYPE_PNG => @imagecreatefrompng($tmpName),
        IMAGETYPE_GIF => @imagecreatefromgif($tmpName),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpName) : false,
        default => false,
    };
}

function save_group_icon_original(array $file, string $dir, string $ext): array {
    $filename = 'group_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => '图标保存失败'];
    }
    @chmod($target, 0644);
    return ['ok' => true, 'url' => 'uploads/groups/' . $filename, 'warning' => '服务器未启用 GD 图像库，已保存原图，未自动裁剪。'];
}

function save_group_icon_square(array $file, string $dir, int $type, string $fallbackExt): array {
    if (!extension_loaded('gd')) {
        return ['ok' => false, 'error' => '服务器未启用 GD 图像库，无法自动裁剪图标。请安装或启用 php-gd 后重试。'];
    }

    $source = create_group_icon_image($file['tmp_name'], $type);
    if (!$source) {
        return ['ok' => false, 'error' => '当前服务器不支持裁剪该图片格式，请换用 JPG 或 PNG 后重试。'];
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $side = min($width, $height);
    $srcX = (int)(($width - $side) / 2);
    $srcY = (int)(($height - $side) / 2);
    $size = 256;

    $targetImage = imagecreatetruecolor($size, $size);
    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);
    $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
    imagefilledrectangle($targetImage, 0, 0, $size, $size, $transparent);

    imagecopyresampled($targetImage, $source, 0, 0, $srcX, $srcY, $size, $size, $side, $side);

    $useWebp = function_exists('imagewebp');
    $ext = $useWebp ? 'webp' : 'png';
    $filename = 'group_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $dir . '/' . $filename;
    $saved = $useWebp ? @imagewebp($targetImage, $target, 90) : @imagepng($targetImage, $target, 6);

    imagedestroy($source);
    imagedestroy($targetImage);

    if (!$saved) {
        return ['ok' => false, 'error' => '图标裁剪后保存失败，请检查上传目录权限。'];
    }

    @chmod($target, 0644);
    return ['ok' => true, 'url' => 'uploads/groups/' . $filename, 'warning' => ''];
}

function upload_group_icon(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'url' => '', 'warning' => ''];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => '图标上传失败'];
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => '图标不能超过 2MB'];
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        return ['ok' => false, 'error' => '请上传有效图片'];
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $type = $info[2] ?? 0;
    if (!isset($allowed[$type])) {
        return ['ok' => false, 'error' => '图标仅支持 JPG、PNG、GIF、WEBP'];
    }

    $dir = ensure_group_upload_dir();
    if (!$dir['ok']) {
        return ['ok' => false, 'error' => $dir['error']];
    }

    return save_group_icon_square($file, $dir['dir'], $type, $allowed[$type]);
}

function normalize_icon_url(string $url): string {
    $url = trim(str_replace('\\', '/', $url));
    if ($url === '') return '';
    if (preg_match('/^https?:\/\//i', $url)) return $url;
    if (str_starts_with($url, '../uploads/')) return substr($url, 3);
    if (str_starts_with($url, '/uploads/')) return ltrim($url, '/');
    if (str_starts_with($url, 'uploads/') && !str_contains($url, '..')) return $url;
    return '';
}

$message = '';
$error = '';
$warning = '';

// 处理表单
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $providerName = trim($_POST['provider_name'] ?? '');
        $iconUrl = normalize_icon_url($_POST['icon_url'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $upload = upload_group_icon($_FILES['icon_file'] ?? []);
        if (!$upload['ok']) {
            $error = $upload['error'];
        } elseif ($upload['url'] !== '') {
            $iconUrl = $upload['url'];
            $warning = $upload['warning'] ?? '';
        }

        if ($error === '' && !$name) {
            $error = '分组名称不能为空';
        } elseif ($error === '' && $iconUrl === '' && trim($_POST['icon_url'] ?? '') !== '') {
            $error = '分组图标链接必须是 http/https 地址，或 uploads/ 开头的站内图片路径';
        } elseif ($error === '') {
            ensure_monitor_schema_columns();
            $missingColumns = get_missing_monitor_schema_columns();
            if ($missingColumns) {
                $error = "数据库缺少新版字段，且程序没有权限自动补齐。请在数据库执行：\n" . monitor_schema_fix_sql();
            }
        }

        if ($error === '') {
            if ($action === 'add') {
                db_insert('groups', [
                    'name' => $name,
                    'description' => $description,
                    'provider_name' => $providerName,
                    'icon_url' => $iconUrl,
                    'display_order' => $displayOrder,
                    'is_active' => $isActive,
                ]);
                $message = '分组创建成功';
            } else {
                $id = (int)$_POST['id'];
                db_update('groups', [
                    'name' => $name,
                    'description' => $description,
                    'provider_name' => $providerName,
                    'icon_url' => $iconUrl,
                    'display_order' => $displayOrder,
                    'is_active' => $isActive,
                ], 'id = ?', [$id]);
                $message = '分组更新成功';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db_delete('groups', 'id = ?', [$id]);
        $message = '分组已删除';
    }
}

$groups = db_all('groups', '1=1', [], 'display_order ASC, id ASC');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分组管理 - <?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- 侧边栏 -->
        <aside class="w-full md:w-64 bg-gray-900 text-white shrink-0">
            <div class="p-5 border-b border-gray-800 flex items-center justify-between">
                <h1 class="text-lg font-bold">🤖 管理后台</h1>
                <button type="button" onclick="document.getElementById('adminNav').classList.toggle('hidden')" class="md:hidden p-2 -mr-2 text-gray-300 hover:text-white" aria-label="切换菜单">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            <nav id="adminNav" class="hidden md:block p-3 md:p-4 space-y-1">
                <a href="<?= h(site_url('admin/index.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>仪表盘</span>
                </a>
                <a href="<?= h(site_url('admin/groups.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 bg-blue-600 rounded-lg text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>分组管理</span>
                </a>
                <a href="<?= h(site_url('admin/channels.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>渠道管理</span>
                </a>
                <a href="<?= h(site_url('admin/settings.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>网站设置</span>
                </a>
                <a href="<?= h(site_url('admin/editor.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>首页编辑</span>
                </a>
                <hr class="my-3 border-gray-800">
                <a href="<?= h(site_url('index.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>返回首页</span>
                </a>
                <a href="<?= h(site_url('admin/logout.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>退出登录</span>
                </a>
            </nav>
        </aside>

        <main class="flex-1 min-w-0 p-4 md:p-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">分组管理</h2>
                    <p class="text-gray-500">创建和管理 AI 分组（如 ChatGPT、Claude 等）</p>
                </div>
                <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium transition text-sm shrink-0">+ 新建分组</button>
            </div>

            <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($warning): ?>
            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm whitespace-pre-line"><?= h($warning) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm whitespace-pre-line"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
                <table class="w-full text-sm min-w-[720px]">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500">
                            <th class="text-left px-6 py-3 font-medium">排序</th>
                            <th class="text-left px-6 py-3 font-medium">图标</th>
                            <th class="text-left px-6 py-3 font-medium">名称</th>
                            <th class="text-left px-6 py-3 font-medium">供应商</th>
                            <th class="text-left px-6 py-3 font-medium">描述</th>
                            <th class="text-left px-6 py-3 font-medium">状态</th>
                            <th class="text-right px-6 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($groups)): ?>
                        <tr><td colspan="7" class="px-6 py-8 text-center text-gray-400">暂无分组，点击「新建分组」创建</td></tr>
                        <?php else: ?>
                            <?php foreach ($groups as $g): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-400"><?= (int)$g['display_order'] ?></td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($g['icon_url'])): ?>
                                    <img src="<?= h(str_starts_with($g['icon_url'], 'uploads/') ? '../' . $g['icon_url'] : $g['icon_url']) ?>" class="w-8 h-8 rounded-full object-cover border border-gray-100" alt="">
                                    <?php else: ?>
                                    <span class="inline-flex w-8 h-8 rounded-lg bg-gray-100 text-gray-400 items-center justify-center text-xs">无</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-800"><?= h($g['name']) ?></td>
                                <td class="px-6 py-4">
                                    <?php if (!empty($g['provider_name'])): ?>
                                    <span class="inline-flex px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700 text-xs font-semibold shadow-sm"><?= h($g['provider_name']) ?></span>
                                    <?php else: ?>
                                    <span class="text-gray-300">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-500"><?= h($g['description'] ?? '') ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded text-xs <?= $g['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $g['is_active'] ? '启用' : '禁用' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button onclick="openModal(<?= htmlspecialchars(json_encode($g)) ?>)" class="text-blue-600 hover:text-blue-800 text-sm">编辑</button>
                                    <form method="post" class="inline" onsubmit="return confirm('确定删除该分组？关联的渠道也会被删除。')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-sm">删除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="groupModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-bold text-gray-800 mb-4" id="modalTitle">新建分组</h3>
            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="0">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">名称</label>
                    <input type="text" name="name" id="formName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">描述</label>
                    <textarea name="description" id="formDescription" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">供应商名称</label>
                    <input type="text" name="provider_name" id="formProviderName" placeholder="OpenAI / Anthropic / Google" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-400">会显示在首页模型卡片的灰色圆角标签里。</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">分组图标链接</label>
                    <input type="text" name="icon_url" id="formIconUrl" placeholder="uploads/groups/icon.webp 或 https://example.com/icon.png" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-400">支持图床链接，也支持已上传文件路径，例如 uploads/groups/xxx.webp；如果同时上传图片，优先使用上传图片。</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">上传分组图标</label>
                    <input type="file" name="icon_file" id="formIconFile" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full text-sm text-gray-500 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <div id="iconPreviewWrap" class="mt-2 hidden items-center space-x-2 text-xs text-gray-500">
                        <img id="iconPreview" src="" class="w-10 h-10 rounded-full object-cover border border-gray-100" alt="">
                        <span>当前图标，上传后会自动居中裁剪为 1:1</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">排序值</label>
                    <input type="number" name="display_order" id="formOrder" value="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_active" id="formActive" checked class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">启用</span>
                </label>
                <div class="flex space-x-3 pt-2">
                    <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">取消</button>
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openModal(data) {
        document.getElementById('groupModal').classList.remove('hidden');
        document.getElementById('groupModal').classList.add('flex');
        if (data && data.id) {
            document.getElementById('modalTitle').textContent = '编辑分组';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = data.id;
            document.getElementById('formName').value = data.name;
            document.getElementById('formDescription').value = data.description || '';
            document.getElementById('formProviderName').value = data.provider_name || '';
            document.getElementById('formIconUrl').value = data.icon_url || '';
            updateIconPreview(data.icon_url || '');
            document.getElementById('formOrder').value = data.display_order || 0;
            document.getElementById('formActive').checked = data.is_active == 1;
        } else {
            document.getElementById('modalTitle').textContent = '新建分组';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formId').value = 0;
            document.getElementById('formName').value = '';
            document.getElementById('formDescription').value = '';
            document.getElementById('formProviderName').value = '';
            document.getElementById('formIconUrl').value = '';
            document.getElementById('formIconFile').value = '';
            updateIconPreview('');
            document.getElementById('formOrder').value = 0;
            document.getElementById('formActive').checked = true;
        }
    }

    function iconSrc(url) {
        if (!url) return '';
        return url.startsWith('uploads/') ? '../' + url : url;
    }

    function updateIconPreview(url) {
        const wrap = document.getElementById('iconPreviewWrap');
        const img = document.getElementById('iconPreview');
        if (!url) {
            wrap.classList.add('hidden');
            wrap.classList.remove('flex');
            img.src = '';
            return;
        }
        img.src = iconSrc(url);
        wrap.classList.remove('hidden');
        wrap.classList.add('flex');
    }

    document.getElementById('formIconFile').addEventListener('change', function() {
        const file = this.files && this.files[0];
        if (!file) return;
        updateIconPreview(URL.createObjectURL(file));
    });

    function closeModal() {
        document.getElementById('groupModal').classList.add('hidden');
        document.getElementById('groupModal').classList.remove('flex');
    }
    </script>
</body>
</html>