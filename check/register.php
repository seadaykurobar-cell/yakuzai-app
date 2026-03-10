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
                $isFirstRow = true;
                $count = 0;
       // --- A. CSVインポートの処理（register.php内の該当箇所を差し替え） ---
              while (($data = fgetcsv($handle)) !== false) {
                    if ($isFirstRow) { $isFirstRow = false; continue; }
                    if (empty($data[0]) || empty(trim($data[0]))) continue;

                    // 1. スペースやタブで最大4分割（名、区分、成分、効能）
                    // 分割順序を「名前 区分 成分 効能」の順に想定します
                    $parts = preg_split('/\s+/u', trim($data[0]), 4); 
                    
                    $name   = $parts[0] ?? '不明な薬品';
                    $risk   = $parts[1] ?? '第2類';
                    $ingRaw = $parts[2] ?? ''; // ★ここが有効成分
                    $effect = $parts[3] ?? ''; // ★ここが効能

                    // 2. 成分を「、」で繋ぎ直してメモ用の文字列を作る
                    $ings = array_filter(array_map('trim', preg_split('/[,、| \t\/\s]+/u', $ingRaw)));
                    $memo = "成分: " . implode("、", $ings);

                    // 3. SQL実行：正しいカラムに値を放り込む
                    // name, risk_category, effect, memo の順番を厳守
                    $stmt = $pdo->prepare("INSERT INTO medicines (name, risk_category, effect, memo) VALUES (?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE 
                                           risk_category=VALUES(risk_category), 
                                           effect=VALUES(effect), 
                                           memo=VALUES(memo)");
                    $stmt->execute([$name, $risk, $effect, $memo]);

                    $count++;

                    // --- (以下、成分テーブルへの紐付け処理：変更なし) ---
                    $st_id = $pdo->prepare("SELECT id FROM medicines WHERE name = ?");
                    $st_id->execute([$name]);
                    $m_id = $st_id->fetchColumn();
                    if ($m_id) {
                        $pdo->prepare("DELETE FROM medicine_ingredients WHERE medicine_id = ?")->execute([$m_id]);
                        foreach ($ings as $ingName) {
                            $pdo->prepare("INSERT IGNORE INTO ingredients (type) VALUES (?)")->execute([$ingName]);
                            $st_ing = $pdo->prepare("SELECT id FROM ingredients WHERE type = ?");
                            $st_ing->execute([$ingName]);
                            $i_id = $st_ing->fetchColumn();
                            if ($i_id) {
                                $pdo->prepare("INSERT IGNORE INTO medicine_ingredients (medicine_id, ingredient_id) VALUES (?, ?)")->execute([$m_id, $i_id]);
                            }
                        }
                    }
                }
                fclose($handle);
                $pdo->commit();
                $message = "✅ インポート完了 {$count}件の薬品を登録・更新しました";
                $status = "success";
              } catch (Exception $e) { 
                $pdo->rollBack(); 
                $message = "❌ インポートエラー: " . $e->getMessage();
                $status = "danger";
            }
        }
        //--- B. 手動登録 of 手動登録の処理 ---
        if (isset($_POST['new_medicine'])) {
            $newName    = trim($_POST['new_medicine']);
            $newEffect  = trim($_POST['new_effect'] ?? '');
            $newRisk    = $_POST['new_risk'] ?? ''; // 初期値は空にする
            $ingRaw     = $_POST['new_ingredients'] ?? '';

            if (empty($newRisk)) {
                $message = "❌ リスク区分を選択してください。";
                $status = "danger";
            } else {
                // 正常な場合のみ、成分の整形とDB登録を開始
                $ingredients = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $ingRaw))));
                $memoContent = "成分: " . implode("、", $ingredients);

                $pdo->beginTransaction();
                try {
                    // risk_category を含めて保存
                    $stmt = $pdo->prepare("INSERT INTO medicines (name, effect, memo, risk_category) VALUES (?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE effect=VALUES(effect), memo=VALUES(memo), risk_category=VALUES(risk_category)");
                    $stmt->execute([$newName, $newEffect, $memoContent, $newRisk]);
                    
                    $st_id = $pdo->prepare("SELECT id FROM medicines WHERE name = ?");
                    $st_id->execute([$newName]);
                    $medicine_id = $st_id->fetchColumn();

                    if ($medicine_id) {
                        // 成分紐付けの更新
                        $pdo->prepare("DELETE FROM medicine_ingredients WHERE medicine_id = ?")->execute([$medicine_id]);
                        foreach ($ingredients as $ingName) {
                            $pdo->prepare("INSERT IGNORE INTO ingredients (type) VALUES (?)")->execute([$ingName]);
                            $st_ing = $pdo->prepare("SELECT id FROM ingredients WHERE type = ?");
                            $st_ing->execute([$ingName]);
                            $ing_id = $st_ing->fetchColumn();
                            if ($ing_id) {
                                $pdo->prepare("INSERT IGNORE INTO medicine_ingredients (medicine_id, ingredient_id) VALUES (?, ?)")->execute([$medicine_id, $ing_id]);
                            }
                        }
                    }
                    $pdo->commit();
                    $message = "✅ 「{$newName}」の内容を登録・更新しました！";
                    $status = "success";
                } catch (Exception $e) { 
                    $pdo->rollBack(); 
                    $message = "❌ DBエラー: " . $e->getMessage();
                    $status = "danger";
                }
            } // end else (empty($newRisk))
        } // end if (isset($_POST['new_medicine']))
    } // end if ($_SERVER['REQUEST_METHOD'] === 'POST')
} catch (Exception $e) {
    $message = "❌ 接続エラー: " . $e->getMessage();
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
        <label class="font-weight-bold">薬品名（商品名）<br></label>
        <input type="text" name="new_medicine" class="form-control" placeholder="例：パブロン" required>
    </div>
<div class="form-group">
    <label class="font-weight-bold">リスク区分<br> </label>
    <select name="new_risk" class="form-control" required>
        <option value="" disabled selected>-- 区分を選択してください --</option>
        <option value="第1類">第1類医薬品</option>
        <option value="指定第2類">指定第2類医薬品</option>
        <option value="第2類">第2類医薬品</option>
        <option value="第3類">第3類医薬品</option>
    </select>
</div>
    <div class="form-group">
        <label class="font-weight-bold">効能・効果<br></label>
        <input type="text" name="new_effect" class="form-control" placeholder="例：のどの痛み、発熱">
    </div>
    <div class="form-group">
        <label class="font-weight-bold">成分名（1行にひとつずつ入力）<br></label>
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