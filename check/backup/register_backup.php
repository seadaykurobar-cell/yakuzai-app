<?php
$dsn = 'mysql:host=localhost;dbname=medicine;charset=utf8mb4';
$user = 'root'; $pass = '';
$message = ""; $status = "info";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     // --- A. CSVインポートの処理 ---
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
        
        $pdo->beginTransaction();
        try {
            $isFirstRow = true; // ★1行目判定用の旗を立てる
              $count = 0; // ★ カウンターを初期化

            while (($data = fgetcsv($handle)) !== false) {
                // ★1. 最初の1行目（見出し）なら、何もしないで次へ
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue; 
                }
 if (empty($data[0]) || empty(trim($data[0]))) continue;


    // 1列目に全部固まっている場合、スペースで分割を試みる
    $parts = preg_split('/\s+/u', trim($data[0]), 3); 
    
    // 分割できた数に応じて代入（足りない場合は空文字）
    $name   = $parts[0] ?? '不明な薬品';
    $ingRaw = $parts[1] ?? '';
    $effect = $parts[2] ?? '';

            
            // 成分を分割（カンマ、読点、スラッシュ、全角スペース、タブ等に対応）
            $ings = array_filter(array_map('trim', preg_split('/[,、| \t\/\s]+/u', $ingRaw)));
            $memo = "成分: " . implode("、", $ings);

            // 1. 薬品登録/更新
            $stmt = $pdo->prepare("INSERT INTO medicines (name, effect, memo) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE effect=VALUES(effect), memo=VALUES(memo)");
            $stmt->execute([$name, $effect, $memo]);

              $count++; // ★ 1件成功するごとにプラス

            // 2. ID取得
            $st_id = $pdo->prepare("SELECT id FROM medicines WHERE name = ?");
            $st_id->execute([$name]);
            $m_id = $st_id->fetchColumn();

            if ($m_id) {
                // 既存の紐付けをリセット
                $pdo->prepare("DELETE FROM medicine_ingredients WHERE medicine_id = ?")->execute([$m_id]);

                // 3. 成分紐付け
                foreach ($ings as $ingName) {
                    $pdo->prepare("INSERT IGNORE INTO ingredients (type) VALUES (?)")->execute([$ingName]);
                    $st_ing = $pdo->prepare("SELECT id FROM ingredients WHERE type = ?");
                    $st_ing->execute([$ingName]);
                    $i_id = $st_ing->fetchColumn();
                    if ($i_id) {
                        $pdo->prepare("INSERT IGNORE INTO medicine_ingredients (medicine_id, ingredient_id) VALUES (?, ?)")
                            ->execute([$m_id, $i_id]);

                           
                    }
                }
            }
        }
        fclose($handle);
        $pdo->commit();
        $message = "✅ インポート完了{$count}件の薬品を登録・更新しました";
        $status = "success";
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

        // --- B. 手動登録の処理 ---
        if (isset($_POST['new_medicine'])) {
            $newName    = trim($_POST['new_medicine']);
            $newEffect  = trim($_POST['new_effect'] ?? '');
            $ingRaw     = $_POST['new_ingredients'] ?? '';

            $ingredients = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $ingRaw))));
            $memoContent = "成分: " . implode("、", $ingredients);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO medicines (name, effect, memo) VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE effect=VALUES(effect), memo=VALUES(memo)");
                $stmt->execute([$newName, $newEffect, $memoContent]);
                
                $st_id = $pdo->prepare("SELECT id FROM medicines WHERE name = ?");
                $st_id->execute([$newName]);
                $medicine_id = $st_id->fetchColumn();

                if ($medicine_id) {
                    // 古い紐付けを削除（これで成分の入れ替えに対応）
                    $pdo->prepare("DELETE FROM medicine_ingredients WHERE medicine_id = ?")->execute([$medicine_id]);

                    foreach ($ingredients as $ingName) {
                        $pdo->prepare("INSERT IGNORE INTO ingredients (type) VALUES (?)")->execute([$ingName]);
                        $st_ing = $pdo->prepare("SELECT id FROM ingredients WHERE type = ?");
                        $st_ing->execute([$ingName]);
                        $ing_id = $st_ing->fetchColumn();
                        if ($ing_id) {
                            $pdo->prepare("INSERT IGNORE INTO medicine_ingredients (medicine_id, ingredient_id) VALUES (?, ?)")
                                ->execute([$medicine_id, $ing_id]);
                        }
                    }
                }
                $pdo->commit();
                $message = "✅ 「{$newName}」の内容を最新の状態に更新しました！";
                $status = "success";
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }
        }
    }
} catch (Exception $e) {
    $message = "❌ エラー: " . $e->getMessage();
    $status = "danger";
}
?>
<!doctype html>
<html lang="ja">

<head>
    <title>薬品登録</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com">
</head>

<body>
    <?php include('navbar.php'); // 既存のナビバーを再利用 ?>

    <main class="container mt-4">
        <!-- メッセージ表示部 -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $status; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- CSVアップロード専用フォーム（追加） -->
       <div class="card mt-4">
    <div class="card-header">CSV一括登録</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
          <input type="file" name="csv_file" class="form-control-file" accept=".csv,.txt">
            </div>
            <button type="submit" class="btn btn-success">アップロードして登録</button>
        </form>
    </div>
</div>
        <!-- 既存の手動登録フォーム -->
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">薬品データの新規登録</h5>
            </div>
            <div class="card-body">

<form method="POST">
    <div class="form-group">
        <label class="font-weight-bold">薬品名（商品名）</label>
        <input type="text" name="new_medicine" class="form-control" placeholder="例：パブロン" required>
    </div>
    <div class="form-group">
        <label class="font-weight-bold">効能・効果</label>
        <input type="text" name="new_effect" class="form-control" placeholder="例：のどの痛み、発熱">
    </div>
    <div class="form-group">
        <label class="font-weight-bold">成分名（1行にひとつずつ入力）</label>
        <textarea name="new_ingredients" class="form-control" rows="5" placeholder="アセトアミノフェン&#10;無水カフェイン"></textarea>
    </div>
    <button type="submit" class="btn btn-success btn-block shadow">マスターに追加登録</button>
</form>
                <hr>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">戻る</a>
            </div>
        </div>
    </main>

        <!-- [Bootstrap 4.5](https://getbootstrap.com) のデザインを適用 -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com">
        
        <!-- [jQuery](https://code.jquery.com) と Bootstrap用JSの読み込み -->
        <script src="https://code.jquery.com"></script>
        <script src="https://cdn.jsdelivr.net"></script>
        <script src="js/ul.js"></script>
    </body>
</html>