<?php
// DB接続設定
$dsn  = 'mysql:host=localhost;dbname=medicine;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. 登録されている薬品一覧を取得
  $sql = "SELECT m.id, m.name, GROUP_CONCAT(i.type SEPARATOR '、') as ings 
            FROM medicines m 
            LEFT JOIN medicine_ingredients mi ON m.id = mi.medicine_id 
            LEFT JOIN ingredients i ON mi.ingredient_id = i.id 
            GROUP BY m.id 
            ORDER BY m.id DESC";

    $stmt = $pdo->query($sql);
    // fetchAll は一度だけ実行するようにします
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $duplicated_ingredients = [];
    $selected_ids = [];
    $restricted_drugs = []; // ⚠️ 12歳未満注意の薬品を格納する配列

    // 2. 比較ボタンが押された時の処理
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['compare'])) {
        $selected_ids = $_POST['medicine_ids'] ?? [];

             if (count($selected_ids) > 0) {
            // --- A. 12歳未満注意（コデイン・トラマドール等）のチェック ---
            // 厚生労働省・PMDAの注意喚起に基づくキーワード
            $caution_keywords = [
                'コデイン', 'トラマドール', 'トラマール', 'トラムセット', 
                'ワントラム', 'フスコデ', 'カフコデ', 'セキコデ', 
                'ライトゲン', 'サリパラ', 'ニチコデ', 'クロフェドリン'
            ];
            
            // 選択された各薬品の名前をチェック
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
$sql = "SELECT m.name, GROUP_CONCAT(i.type) as ingredient_list
        FROM medicines m
        LEFT JOIN medicine_ingredients mi ON m.id = mi.medicine_id
        LEFT JOIN ingredients i ON mi.ingredient_id = i.id
        WHERE m.id IN ($placeholders)
        GROUP BY m.id";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_values($selected_ids)); 
$selected_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($selected_data as $row) {
      $found_keys = []; // その薬で見つかったNG成分を貯める
    foreach ($caution_keywords as $key) {
        // 商品名(name) または 成分(ingredient_list) にキーワードが含まれるか
        if (mb_strpos($row['name'], $key) !== false || mb_strpos($row['ingredient_list'], $key) !== false) {
            $found_keys[] = $key;
        }
    }
    if (!empty($found_keys)) {
        // 薬名とヒットした成分キーワードをセットで保存（重複を除去）
        $restricted_drugs[] = [
            'name' => $row['name'],
            'hits' => array_unique($found_keys)
        ];
    }
}


        if (count($selected_ids) > 1) {
            // 中間テーブルを利用して、選択された薬品間で重複する成分(type)を特定
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $sql = "SELECT i.type, COUNT(*) as count 
                    FROM medicine_ingredients mi
                    JOIN ingredients i ON mi.ingredient_id = i.id
                    WHERE mi.medicine_id IN ($placeholders)
                    GROUP BY i.type
                    HAVING count > 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($selected_ids);
            $duplicated_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    }
} catch (PDOException $e) {
    exit("エラー: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="ja">
<head>
    <title>成分比較 - 商品チェック</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com">
</head>
<body>
    <?php include('navbar.php'); ?>

    <main class="container">
        <h2 class="mb-4">商品比較・成分チェック</h2>
<!-- ⚠️ 12歳未満注意アラート表示 -->
<?php if (!empty($restricted_drugs)): ?>
    <div class="alert alert-warning shadow-sm border-danger" style="border-width: 2px;">
        <h4 class="alert-heading text-danger">🚫 12歳未満 服用厳禁</h4>
        <p class="mb-2">以下の薬品には小児に禁忌の成分が含まれています：</p>
        
        <div class="bg-white p-3 rounded border shadow-sm">
            <ul class="mb-0 list-unstyled">
                <?php foreach ($restricted_drugs as $item): ?>
                    <li class="mb-3 border-bottom pb-2 last-child-no-border">
                        <div class="text-danger h5 font-weight-bold mb-1">
                            <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="d-flex align-items-center">
                            <small class="text-muted mr-2">該当成分:</small>
                            <?php foreach ($item['hits'] as $hit): ?>
                                <span class="badge badge-danger mr-1"><?php echo htmlspecialchars($hit, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <hr>
    </div>
<?php endif; ?>

        <!-- 重複アラート表示 -->
        <?php if (!empty($duplicated_ingredients)): ?>
            <div class="alert alert-danger shadow">
                <h4 class="alert-heading">⚠️ 成分の重複警告！</h4>
                <p>以下の成分が複数の薬品に含まれています。過剰摂取に注意してください：</p>
                
                <ul class="mb-0">
                    <?php foreach ($duplicated_ingredients as $row): ?>
                        <li><strong><?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?></strong> 
                            (<?php echo $row['count']; ?>製品に含有)</li>
                    <?php endforeach; ?>
                </ul>
                <hr>
            </div>
        <?php elseif (isset($_POST['compare']) && count($selected_ids) > 1): ?>
            <div class="alert alert-success">成分の重複は見つかりませんでした。</div>
        <?php endif; ?>
<!-- 検索窓（これを追加） -->
<div class="mb-4">
    <div class="input-group shadow-sm">
        <div class="input-group-prepend">
            <span class="input-group-text bg-primary text-white border-primary">
                <i class="fas fa-search"></i> 🔍 絞り込み
            </span>
        </div>
        <input type="text" id="drugSearch" class="form-control form-control-lg border-primary" 
               placeholder="薬品名、成分、効能（のど・熱など）を入力..." 
               autofocus>
    </div>
    <div class="d-flex justify-content-between mt-1">
        <small class="text-muted ml-1">※文字を入力すると即座にリストを絞り込みます</small>
        <small id="matchCount" class="text-primary font-weight-bold"></small>
    </div>
</div>

<form method="POST">
    <div class="card shadow-sm">
        <div class="card-header bg-light font-weight-bold">商品を選択（2つ以上で重複チェック）</div>
        <div class="card-body">
            <div class="row" id="drugList">
                <?php foreach ($medicines as $m): ?>
                    <!-- 各薬品を drug-item クラスで囲む -->
                    <div class="col-md-4 mb-3 drug-item"> 
                        <div class="custom-control custom-checkbox p-2 border rounded bg-white h-100 shadow-sm">
                            <input type="checkbox" name="medicine_ids[]" value="<?php echo $m['id']; ?>" 
                                   class="custom-control-input" id="check<?php echo $m['id']; ?>"
                                   <?php if(in_array($m['id'], $selected_ids)) echo 'checked'; ?>>
                            <label class="custom-control-label d-block cursor-pointer" for="check<?php echo $m['id']; ?>" style="cursor: pointer;">
                                <strong><?php echo htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <br>
                                <!-- インポートした効能を表示 -->
                                <small class="text-primary d-block font-weight-bold">
                                    <?php echo htmlspecialchars($m['effect'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                                <!-- 成分を表示 -->
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($m['ings'] ?: '成分未登録', ENT_QUOTES, 'UTF-8'); ?>
                                </small>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer bg-white">
            <button type="submit" name="compare" class="btn btn-primary btn-lg btn-block shadow">選択した商品をチェックする</button>
        </div>
    </div>
</form>

    </main>
 
 <script>
    // 外部ライブラリを使わず、ブラウザの標準機能だけで検索を実行します
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('drugSearch');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                const items = document.querySelectorAll('.drug-item');

                items.forEach(function(item) {
                    const text = item.textContent.toLowerCase();
                    // 入力した文字が含まれているか判定
                    if (text.indexOf(value) > -1) {
                        item.style.display = ''; // 表示
                    } else {
                        item.style.display = 'none'; // 非表示
                    }
                });

                // ヒット件数の表示更新
                const visibleCount = Array.from(items).filter(i => i.style.display !== 'none').length;
                const matchCountDisp = document.getElementById('matchCount');
                if (matchCountDisp) {
                    matchCountDisp.textContent = value !== "" ? visibleCount + " 件ヒット" : "";
                }
            });
        }
    });
    </script>
</body>
</html>