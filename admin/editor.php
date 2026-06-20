<?php
/**
 * 首页内容编辑 - 可视化 HTML 编辑器
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';

    try {
        $exists = db_get('home_content', 'content_type = ?', ['html']);
        if ($exists) {
            db_update('home_content', ['content' => $content], 'content_type = ?', ['html']);
        } else {
            db_insert('home_content', ['content_type' => 'html', 'content' => $content]);
        }
        $message = '首页内容已更新';
    } catch (Exception $e) {
        $error = '保存失败：' . $e->getMessage();
    }
}

$currentContent = get_home_content();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>首页编辑 - <?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #preview { min-height: 200px; }
        .editor-pane { transition: all 0.2s; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex min-h-screen">
        <!-- 侧边栏 -->
        <aside class="w-64 bg-gray-900 text-white">
            <div class="p-5 border-b border-gray-800">
                <h1 class="text-lg font-bold">🤖 管理后台</h1>
            </div>
            <nav class="p-4 space-y-1">
                <a href="index.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span>仪表盘</span>
                </a>
                <a href="groups.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>分组管理</span>
                </a>
                <a href="channels.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>渠道管理</span>
                </a>
                <a href="settings.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>网站设置</span>
                </a>
                <a href="editor.php" class="flex items-center space-x-3 px-4 py-2.5 bg-blue-600 rounded-lg text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>首页编辑</span>
                </a>
                <hr class="my-3 border-gray-800">
                <a href="../index.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>返回首页</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>退出登录</span>
                </a>
            </nav>
        </aside>

        <main class="flex-1 p-8">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">首页内容编辑</h2>
                    <p class="text-gray-500">编辑展示在分组监控上方的自定义内容区块</p>
                </div>
                <div class="flex space-x-2">
                    <button onclick="switchMode('code')" id="codeBtn" class="px-4 py-2 rounded-lg text-sm font-medium border transition <?= /* default */ 'bg-blue-600 text-white border-blue-600' ?>" style="display:none">代码</button>
                    <button onclick="switchMode('preview')" id="previewBtn" class="px-4 py-2 rounded-lg text-sm font-medium border transition bg-gray-100 text-gray-600 border-gray-300">预览</button>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex space-x-2">
                        <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded">HTML</span>
                        <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded">CSS 内联</span>
                    </div>
                    <span class="text-xs text-gray-400">支持任意 HTML + Tailwind CSS</span>
                </div>
                <form method="post" id="editorForm">
                    <div id="editorArea">
                        <textarea name="content" id="codeEditor" rows="16" class="w-full p-4 font-mono text-sm border-0 focus:ring-0 resize-y bg-gray-50" placeholder="输入 HTML 内容..."><?= h($currentContent) ?></textarea>
                        <div id="livePreview" class="p-6 border-t border-gray-100 hidden">
                            <div id="preview" class="prose max-w-none"><?= $currentContent ?: '<p class="text-gray-400 text-center py-8">暂无内容，在代码编辑器中输入 HTML 即可预览</p>' ?></div>
                        </div>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50 flex justify-end">
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium transition text-sm">保存内容</button>
                    </div>
                </form>
            </div>

            <!-- 快捷模板 -->
            <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">快捷插入模板</h3>
                <div class="flex flex-wrap gap-2">
                    <button onclick="insertTemplate('hero')" class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs hover:bg-blue-100 transition">标题横幅</button>
                    <button onclick="insertTemplate('stats')" class="px-3 py-1.5 bg-green-50 text-green-600 rounded-lg text-xs hover:bg-green-100 transition">统计卡片</button>
                    <button onclick="insertTemplate('notice')" class="px-3 py-1.5 bg-yellow-50 text-yellow-600 rounded-lg text-xs hover:bg-yellow-100 transition">公告栏</button>
                    <button onclick="insertTemplate('features')" class="px-3 py-1.5 bg-purple-50 text-purple-600 rounded-lg text-xs hover:bg-purple-100 transition">功能列表</button>
                </div>
            </div>
        </main>
    </div>

    <script>
    // 模式切换
    function switchMode(mode) {
        const editor = document.getElementById('codeEditor');
        const preview = document.getElementById('livePreview');
        const codeBtn = document.getElementById('codeBtn');
        const previewBtn = document.getElementById('previewBtn');
        
        if (mode === 'code') {
            editor.classList.remove('hidden');
            preview.classList.add('hidden');
            codeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white border border-blue-600 transition';
            previewBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 border border-gray-300 transition';
        } else {
            editor.classList.add('hidden');
            preview.classList.remove('hidden');
            // 刷新预览
            document.getElementById('preview').innerHTML = editor.value || '<p class="text-gray-400 text-center py-8">暂无内容</p>';
            previewBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white border border-blue-600 transition';
            codeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 border border-gray-300 transition';
        }
    }

    // 实时预览
    document.getElementById('codeEditor')?.addEventListener('input', function() {
        document.getElementById('preview').innerHTML = this.value || '<p class="text-gray-400 text-center py-8">暂无内容</p>';
    });

    // 插入模板
    function insertTemplate(type) {
        const editor = document.getElementById('codeEditor');
        const templates = {
            hero: `<div class="text-center py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-2">AI 服务状态监控</h1>
    <p class="text-gray-500">实时监控各大 AI 服务的可用性与响应延迟</p>
</div>`,
            stats: `<div class="grid grid-cols-3 gap-4 text-center">
    <div class="bg-blue-50 rounded-xl p-4">
        <div class="text-2xl font-bold text-blue-600">7</div>
        <div class="text-sm text-gray-500">在线渠道</div>
    </div>
    <div class="bg-green-50 rounded-xl p-4">
        <div class="text-2xl font-bold text-green-600">100%</div>
        <div class="text-sm text-gray-500">可用率</div>
    </div>
    <div class="bg-purple-50 rounded-xl p-4">
        <div class="text-2xl font-bold text-purple-600">128ms</div>
        <div class="text-sm text-gray-500">平均延迟</div>
    </div>
</div>`,
            notice: `<div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start space-x-3">
    <span class="text-yellow-500 text-lg">📢</span>
    <div>
        <p class="font-medium text-yellow-800">系统公告</p>
        <p class="text-sm text-yellow-700">部分渠道正在维护中，预计很快恢复。</p>
    </div>
</div>`,
            features: `<div class="grid grid-cols-2 gap-4">
    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-xl">
        <span class="text-xl">⚡</span>
        <div>
            <p class="font-medium text-gray-800 text-sm">实时检测</p>
            <p class="text-xs text-gray-500">每秒监测 API 响应状态</p>
        </div>
    </div>
    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-xl">
        <span class="text-xl">📊</span>
        <div>
            <p class="font-medium text-gray-800 text-sm">延迟图表</p>
            <p class="text-xs text-gray-500">可视化历史响应时间</p>
        </div>
    </div>
</div>`
        };
        
        const textarea = editor;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);
        textarea.value = before + '\n' + templates[type] + '\n' + after;
        textarea.selectionStart = textarea.selectionEnd = start + templates[type].length + 2;
        textarea.focus();
        textarea.dispatchEvent(new Event('input'));
    }

    // 初始化：预览模式默认展示 code 模式
    document.addEventListener('DOMContentLoaded', function() {
        if (!document.getElementById('codeEditor').value) {
            switchMode('code');
        }
        document.getElementById('codeBtn').style.display = 'block';
        document.getElementById('previewBtn').style.display = 'block';
    });
    </script>
</body>
</html>