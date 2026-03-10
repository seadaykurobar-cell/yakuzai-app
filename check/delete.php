<?php
header('Content-Type: text/html; charset=UTF-8'); // これを追加
mb_internal_encoding("UTF-8");
$dsn = 'mysql:host=localhost;dbname=medicine;charset=utf8mb4';
$user = 'root'; $pass = '';
$message = ""; $status = "success";

if (isset($_GET['msg'])) { $message = $_GET['msg']; }

$items_per_page = 30;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$all = []; 
$total = 0;
$total_pages = 0;


try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (isset($_POST['all_delete_confirm'])) {
            $pdo->exec("DELETE FROM medicine_ingredients"); $pdo->exec("DELETE FROM medicines");
            header("Location: delete.php?msg=" . urlencode("全データをリセットしました。")); exit;
        }
        if (isset($_POST['bulk_delete']) && !empty($_POST['selected_ids'])) {
            $ids = $_POST['selected_ids']; $p = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("DELETE FROM medicine_ingredients WHERE medicine_id IN ($p)")->execute($ids);
            $pdo->prepare("DELETE FROM medicines WHERE id IN ($p)")->execute($ids);
            $message = count($ids) . " 件を一括削除しました。";
        }
        if (isset($_POST['delete_id'])) {
            $id = $_POST['delete_id'];
            $pdo->prepare("DELETE FROM medicine_ingredients WHERE medicine_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM medicines WHERE id = ?")->execute([$id]);
            $message = "1件削除しました。";
        }
    }
 $where = $search !== '' ? " WHERE m.name LIKE ? OR i.type LIKE ? OR m.effect LIKE ?" : "";
    $params = $search !== '' ? ["%$search%", "%$search%", "%$search%"] : [];

    // 1. 全件数取得
    $st_c = $pdo->prepare("SELECT COUNT(DISTINCT m.id) FROM medicines m LEFT JOIN medicine_ingredients mi ON m.id = mi.medicine_id LEFT JOIN ingredients i ON mi.ingredient_id = i.id" . $where);
    $st_c->execute($params); 
    $total = $st_c->fetchColumn();
    $total_pages = ceil($total / $items_per_page);

    // 2. データ取得 SQL
$sql = "SELECT m.id, m.name, m.risk_category, m.effect, m.memo, GROUP_CONCAT(i.type SEPARATOR '、') as ings 
        FROM medicines m LEFT JOIN medicine_ingredients mi ON m.id = mi.medicine_id 
        LEFT JOIN ingredients i ON mi.ingredient_id = i.id $where 
        GROUP BY m.id ORDER BY m.id DESC LIMIT ? OFFSET ?";
            
    $stmt = $pdo->prepare($sql);

    // 3. パラメータのバインド（順番が大事！）
    $idx = 1;
    foreach ($params as $v) { 
        $stmt->bindValue($idx++, $v); 
    }
    // LIMIT と OFFSET を最後に追加
    $stmt->bindValue($idx++, (int)$items_per_page, PDO::PARAM_INT);
    $stmt->bindValue($idx++, (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC); // ★ここで確実に $all に代入

} catch (Exception $e) {
    // エラーが起きた場合は空配列にしておく
    $all = [];
    $message = "❌ エラー: " . $e->getMessage();
    $status = "danger";
}

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>管理画面</title>
    <!-- [Bootstrap 4.5](https://getbootstrap.com) のCSSを適用 -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com">
</head>
<body class="bg-light">
    <?php include('navbar.php'); ?>
    <main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>管理画面 <span class="badge badge-primary">全<?php echo $total; ?>件</span></h4>
        <form class="form-inline" method="GET">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="薬品名・成分..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
    </div>

    <!-- メッセージ表示 -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $status; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-2">
            <button type="submit" name="bulk_delete" class="btn btn-danger btn-sm" onclick="return confirm('選択した薬品を削除しますか？')">選択削除</button>
            <button type="submit" name="all_delete_confirm" class="btn btn-dark btn-sm ml-2" onclick="return confirm('本当に全データをリセットしますか？')">全件リセット</button>
        </div>

        <table class="table table-bordered bg-white table-sm">
            <thead class="thead-light">
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="all"></th>
                    <th>薬品名</th>
                    <th>区分</th>
                    <th>成分</th>
                    <th>効能・効果</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all)): ?>
                    <tr><td colspan="4" class="text-center">該当する薬品はありません。</td></tr>
                <?php else: ?>
                    <?php foreach ($all as $m): ?>
                    <tr>
                        <td><input type="checkbox" name="selected_ids[]" value="<?php echo $m['id']; ?>" class="cb"></td>
                        <td><strong><?php echo htmlspecialchars($m['risk_category'] ??'未設定');; ?></strong></td>
                        <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars($m['ings'] ?: '成分未登録'); ?></small></td>
                        <td><small><?php echo htmlspecialchars($m['effect']); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- ページ送り（点が出ないスタイル） -->
    <nav>
        <div class="pagination pagination-sm justify-content-center">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a class="page-link <?php echo $i==$page ? 'active bg-primary text-white' : ''; ?>" 
                   href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </nav>
</main>

    <script>
 // 1. 全選択・解除機能
    document.getElementById('all').onclick = function() {
        document.querySelectorAll('.cb').forEach(c => c.checked = this.checked);
    }

    // 2. 画面内絞り込み（リロードしないからカーソルが消えない）
    const searchBox = document.querySelector('input[name="search"]');
    if (searchBox) {
        searchBox.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                // 画面内の行を隠すだけなので、リロードは発生せずカーソルも維持される
                row.style.display = text.indexOf(query) > -1 ? '' : 'none';
            });
        });
    }
    </script>
</body>
</html>