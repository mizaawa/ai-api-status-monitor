<?php
/**
 * 渠道管理
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

ensure_monitor_schema_columns();

$message = '';
$error = '';

// 处理表单
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$model) {
            $error = '请填写渠道名称和模型';
        } elseif ($groupId <= 0) {
            $error = '请选择分组';
        } else {
            $data = [
                'group_id' => $groupId,
                'name' => $name,
                'model' => $model,
                'is_active' => $isActive,
                'api_url' => null,
                'api_key' => null,
            ];
            if ($action === 'add') {
                db_insert('channels', $data);
                $message = '渠道创建成功';
            } else {
                $id = (int)$_POST['id'];
                db_update('channels', $data, 'id = ?', [$id]);
                $message = '渠道更新成功';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db_delete('channels', 'id = ?', [$id]);
        $message = '渠道已删除';
    }

    if ($action === 'check') {
        $id = (int)$_POST['id'];
        $channel = db_get('channels', 'id = ?', [$id]);
        if (!$channel) {
            $payload = ['ok' => false, 'message' => '渠道不存在或已删除'];
            if (($_POST['ajax'] ?? '') === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($payload, JSON_UNESCAPED_UNICODE);
                exit;
            }
            $error = $payload['message'];
        } else {
            $group = db_get('groups', 'id = ?', [(int)$channel['group_id']]);
            $groupName = $group['name'] ?? '未知分组';
            $result = check_ai_api(
                $channel['api_key'] ?? '',
                $channel['api_url'] ?? '',
                $channel['model'],
                (int)$channel['group_id']
            );
            db_update('channels', [
                'last_latency' => $result['latency'],
                'last_ping_latency' => $result['ping_latency'],
                'last_status' => $result['status_code'],
                'last_error' => $result['error'],
                'last_check_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            db_insert('monitor_logs', [
                'channel_id' => $id,
                'latency' => $result['latency'],
                'ping_latency' => $result['ping_latency'],
                'status_code' => $result['status_code'],
                'error_message' => $result['error'],
            ]);
            $message = ($result['success'] ? '检测通过' : '检测失败') . '：' . $groupName . ' / ' . $channel['name'] . '，对话延迟 ' . $result['latency'] . 'ms，端点 PING ' . ($result['ping_latency'] ?? '--') . 'ms';
            $payload = [
                'ok' => (bool)$result['success'],
                'message' => $message,
                'group' => $groupName,
                'channel' => $channel['name'],
                'latency' => $result['latency'],
                'ping_latency' => $result['ping_latency'],
                'status_code' => $result['status_code'],
                'error' => $result['error'],
            ];
            if (($_POST['ajax'] ?? '') === '1') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($payload, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    if ($action === 'check_all') {
        $channels = db_all('channels', 'is_active = 1');
        $results = ['total' => 0, 'success' => 0, 'failed' => 0];
        foreach ($channels as $ch) {
            $results['total']++;
            $result = check_ai_api(
                $ch['api_key'] ?? '',
                $ch['api_url'] ?? '',
                $ch['model'],
                (int)$ch['group_id']
            );
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
            db_update('channels', [
                'last_latency' => $result['latency'],
                'last_ping_latency' => $result['ping_latency'],
                'last_status' => $result['status_code'],
                'last_error' => $result['error'],
                'last_check_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$ch['id']]);
            db_insert('monitor_logs', [
                'channel_id' => $ch['id'],
                'latency' => $result['latency'],
                'ping_latency' => $result['ping_latency'],
                'status_code' => $result['status_code'],
                'error_message' => $result['error'],
            ]);
        }
        $message = "批量检测完成：共 {$results['total']} 个渠道，成功 {$results['success']} 个，失败 {$results['failed']} 个";
        if (($_POST['ajax'] ?? '') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => $message, 'results' => $results], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$groups = db_all('groups', '1=1', [], 'display_order ASC, id ASC');
$channels = db_all('channels', '1=1', [], 'group_id ASC, id ASC');

// 为每个渠道关联分组名
$groupMap = [];
foreach ($groups as $g) $groupMap[$g['id']] = $g['name'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>渠道管理 - <?= site_title() ?></title>
    <?= site_icon_tag() ?>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div id="toastRoot" class="fixed top-5 right-5 z-[9999] space-y-3 w-[min(420px,calc(100vw-32px))]"></div>
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- 侧边栏（同分组管理） -->
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
                <a href="<?= h(site_url('admin/groups.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 text-gray-300 hover:bg-gray-800 rounded-lg text-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span>分组管理</span>
                </a>
                <a href="<?= h(site_url('admin/channels.php')) ?>" class="flex items-center space-x-3 px-4 py-2.5 bg-blue-600 rounded-lg text-sm font-medium">
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
                    <h2 class="text-2xl font-bold text-gray-800">渠道管理</h2>
                    <p class="text-gray-500">管理各分组下的 AI API 渠道</p>
                </div>
                <div class="flex gap-2">
                    <form method="post" class="inline" id="checkAllForm">
                        <input type="hidden" name="action" value="check_all">
                        <button type="submit" class="bg-green-600 text-white px-5 py-2.5 rounded-lg hover:bg-green-700 font-medium transition text-sm shrink-0">🔍 一键检测</button>
                    </form>
                    <button onclick="openModal()" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium transition text-sm shrink-0">+ 新建渠道</button>
                </div>
            </div>

            <?php if ($message && ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'check')): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-600 text-sm"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
                <table class="w-full text-sm min-w-[720px]">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500">
                            <th class="text-left px-6 py-3 font-medium">分组</th>
                            <th class="text-left px-6 py-3 font-medium">名称</th>
                            <th class="text-left px-6 py-3 font-medium">模型</th>
                            <th class="text-left px-6 py-3 font-medium">状态</th>
                            <th class="text-left px-6 py-3 font-medium">最近延迟</th>
                            <th class="text-right px-6 py-3 font-medium">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if (empty($channels)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">暂无渠道</td></tr>
                        <?php else: ?>
                            <?php foreach ($channels as $ch): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-600"><?= h($groupMap[$ch['group_id']] ?? '未知分组') ?></td>
                                <td class="px-6 py-4 font-medium text-gray-800"><?= h($ch['name']) ?></td>
                                <td class="px-6 py-4 text-gray-600 font-mono text-xs"><?= h($ch['model']) ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded text-xs <?= $ch['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?= $ch['is_active'] ? '启用' : '禁用' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($ch['last_latency'] !== null): ?>
                                    <span class="font-mono <?= $ch['last_latency'] < 5000 ? 'text-green-600' : ($ch['last_latency'] < 20000 ? 'text-red-600' : 'text-yellow-600') ?>">
                                        <?= format_latency((int)$ch['last_latency']) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <form method="post" class="inline check-form">
                                        <input type="hidden" name="action" value="check">
                                        <input type="hidden" name="id" value="<?= $ch['id'] ?>">
                                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm">检测</button>
                                    </form>
                                    <button onclick="openModal(<?= htmlspecialchars(json_encode([
                                        'id' => $ch['id'],
                                        'group_id' => $ch['group_id'],
                                        'name' => $ch['name'],
                                        'api_url' => $ch['api_url'],
                                        'model' => $ch['model'],
                                        'is_active' => $ch['is_active'],
                                    ])) ?>)" class="text-blue-600 hover:text-blue-800 text-sm">编辑</button>
                                    <form method="post" class="inline" onsubmit="return confirm('确定删除该渠道？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $ch['id'] ?>">
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
    <div id="channelModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col mx-4">
            <div class="px-6 pt-6 pb-4 border-b border-gray-100 shrink-0">
                <h3 class="text-lg font-bold text-gray-800" id="modalTitle">新建渠道</h3>
            </div>
            <div class="px-6 py-4 overflow-y-auto flex-1">
                <form method="post" class="space-y-4" id="channelForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="0">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">所属分组</label>
                    <select name="group_id" id="formGroupId" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">请选择分组</option>
                        <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">渠道名称</label>
                    <input type="text" name="name" id="formName" required placeholder="例如：GPT-4o 主节点" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">模型</label>
                    <input type="text" name="model" id="formModel" required placeholder="例如：gpt-4o" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="mt-1 text-xs text-gray-400">API 接口和密钥将自动继承分组的默认配置。</p>
                </div>
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_active" id="formActive" checked class="rounded border-gray-300 text-blue-600">
                    <span class="text-sm text-gray-700">启用</span>
                </label>
            </form>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex space-x-3 shrink-0">
                <button type="button" onclick="closeModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">取消</button>
                <button type="submit" form="channelForm" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-medium transition">保存</button>
            </div>
        </div>
    </div>

    <script>
    function openModal(data) {
        document.getElementById('channelModal').classList.remove('hidden');
        document.getElementById('channelModal').classList.add('flex');
        if (data && data.id) {
            document.getElementById('modalTitle').textContent = '编辑渠道';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = data.id;
            document.getElementById('formGroupId').value = data.group_id;
            document.getElementById('formName').value = data.name;
            document.getElementById('formModel').value = data.model;
            document.getElementById('formActive').checked = data.is_active == 1;
        } else {
            document.getElementById('modalTitle').textContent = '新建渠道';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formId').value = 0;
            document.getElementById('formGroupId').value = '';
            document.getElementById('formName').value = '';
            document.getElementById('formModel').value = '';
            document.getElementById('formActive').checked = true;
        }
    }
    function closeModal() {
        document.getElementById('channelModal').classList.add('hidden');
        document.getElementById('channelModal').classList.remove('flex');
    }

    function showToast(message, ok = true) {
        const root = document.getElementById('toastRoot');
        const toast = document.createElement('div');
        toast.className = `rounded-xl border px-4 py-3 shadow-lg bg-white text-sm ${ok ? 'border-green-200 text-green-800' : 'border-red-200 text-red-700'}`;
        toast.innerHTML = `<div class="font-semibold">${ok ? '检测完成' : '检测异常'}</div><div class="mt-1 leading-relaxed">${escapeHtml(message)}</div>`;
        root.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-8px)';
            toast.style.transition = 'opacity .2s ease, transform .2s ease';
            setTimeout(() => toast.remove(), 220);
        }, 5200);
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char]));
    }

    async function runChannelCheck(event, form) {
        event.preventDefault();
        event.stopPropagation();
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = '检测中';
        try {
            const formData = new FormData(form);
            formData.set('ajax', '1');
            const res = await fetch(<?= json_encode(site_url('admin/channels.php')) ?>, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            showToast(data.message || '检测已完成', !!data.ok);
        } catch (e) {
            showToast('检测请求失败，请稍后重试', false);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
        return false;
    }

    document.querySelectorAll('.check-form').forEach(form => {
        form.addEventListener('submit', event => runChannelCheck(event, form));
    });

    // 一键检测
    document.getElementById('checkAllForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        const button = this.querySelector('button[type="submit"]');
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = '检测中...';
        
        try {
            const formData = new FormData(this);
            formData.set('ajax', '1');
            const res = await fetch(<?= json_encode(site_url('admin/channels.php')) ?>, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            showToast(data.message || '批量检测完成', !!data.ok);
            if (data.ok) {
                setTimeout(() => location.reload(), 2000);
            }
        } catch (e) {
            showToast('批量检测请求失败，请稍后重试', false);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    });
    </script>
</body>
</html>