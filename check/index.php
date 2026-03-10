<?php
// DB接続設定
$dsn  = 'mysql:host=localhost;dbname=medicine;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 1. 【全件取得】画面表示用 (risk_categoryを追加)
    $sql = "SELECT m.id, m.name, m.risk_category, m.effect, GROUP_CONCAT(i.type SEPARATOR '、') as  ings
            FROM medicines m 
            LEFT JOIN medicine_ingredients mi ON m.id = mi.medicine_id 
            LEFT JOIN ingredients i ON mi.ingredient_id = i.id 
            GROUP BY m.id 
            ORDER BY m.id DESC";

    $stmt = $pdo->query($sql);
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $duplicated_ingredients = [];
    $selected_ids = [];
    $restricted_drugs = []; 

    // 2. 比較ボタンが押された時の処理
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['compare'])) {
        $selected_ids = $_POST['medicine_ids'] ?? [];

        if (count($selected_ids) > 0) {
            // --- A. 12歳未満注意チェック ---
            $caution_keywords = ['コデイン', 'トラマドール', 'トラマール', 'トラムセット', 'ワントラム', 'フスコデ', 'カフコデ', 'セキコデ', 'ライトゲン', 'サリパラ', 'ニチコデ', 'クロフェドリン','ジヒドロコデイン','ジヒドロコデインリン酸塩','コデインリン酸塩水和物'];
            
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            
            // ★修正：WHERE句で選択された薬品だけに絞り込む
            $sql = "SELECT m.name, GROUP_CONCAT(i.type SEPARATOR '、') as ingredient_list
                    FROM medicines m
                    LEFT JOIN medicine_ingredients mi ON m.id = mi.medicine_id
                    LEFT JOIN ingredients i ON mi.ingredient_id = i.id
                    WHERE m.id IN ($placeholders)
                             GROUP BY m.id"; // 名前の重複を考慮してIDでグループ化

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($selected_ids)); 
            $selected_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

       foreach ($selected_data as $row) {
    $found_keys = [];
    // 成分リストを配列に変換
    $ingredients = explode('、', $row['ingredient_list'] ?? '');

    foreach ($ingredients as $ing) {
        foreach ($caution_keywords as $key) {
            // 成分名の中にキーワードが含まれているかチェック
            if (mb_strpos($ing, $key) !== false) {
                // キーワードではなく「成分名そのもの」を保存！
                $found_keys[] = $ing; 
            }
        }
    }

    if (!empty($found_keys)) {
        $restricted_drugs[] = [
            'name' => $row['name'],
            'hits' => array_unique($found_keys)
        ];
    }
}

            // --- B. 成分重複チェック ---
        // 選択された医薬品IDが複数ある場合のみ重複チェックを実行
if (count($selected_ids) > 1) {
    // 選択されたIDのプレースホルダーを作成
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

    // 重複成分とその成分を含む商品名を取得するSQL
    $sql = "SELECT i.type, COUNT(DISTINCT mi.medicine_id) as count, GROUP_CONCAT(m.name SEPARATOR '、') as drug_names
            FROM medicine_ingredients mi
            JOIN ingredients i ON mi.ingredient_id = i.id
            JOIN medicines m ON mi.medicine_id = m.id
            WHERE mi.medicine_id IN ($placeholders)
            GROUP BY i.type
            HAVING count > 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($selected_ids));
    $duplicated_ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // 選択された医薬品が1つ以下の場合は重複なしとみなす
    $duplicated_ingredients = [];
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
    <meta charset="UTF-8">
    <title>成分比較 - 商品チェック</title>

    <!-- 外部CSSが読み込めない時のための「自前」CSS -->
    <style>
        /* 【重要】もっと見る（5件制限）を動かすための設定 */
        .d-none {
            display: none !important;
        }

        /* リスク区分別の色設定 */
        .badge {
            display: inline-block;
            padding: .25em .6em;
            font-size: 70%;
            font-weight: 700;
            border-radius: .25rem;
            margin-bottom: 5px;
            color: brack;
        }

        /* DBの区分名に合わせたクラス設定 */
        .badge-risk-第1類 {
            background-color: #dc3545;
        }

        /* 赤 */
        .badge-risk-指定第2類 {
            background-color: #ff8c00;
        }

        /* オレンジ */
        .badge-risk-第2類 {
            background-color: #007bff;
        }

        /* 青 */
        .badge-risk-第3類 {
            background-color: #28a745;
        }

        /* 緑 */
        .badge-risk-default {
            background-color: #6c757d;
        }

        /* グレー */

        /* レイアウトのCSS */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }

        .col-md-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 15px;
            position: relative;
        }

        /* 3カラムレイアウトの設定 */
        /* ボタンがカードの横に入り込まないようにする */
        #loadMoreBtn {
            flex: 0 0 100%;
            /* 横幅いっぱい使う */
            margin: 20px 0;
            display: block;
            clear: both;
            /* 回り込み解除 */
        }

        /* 親要素の余白を調整 */
        #drugList {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            /* カードの高さを揃える */
        }

        .drug-item {
            flex: 0 0 25%;
            /* 33.3% から 25% に変更 */
            max-width: 25%;
            padding: 6px;
            /* 余白も少し詰めるとスッキリします */
            box-sizing: border-box;
        }

        /* モバイル対応（画面が狭いときは1列にする） */
        @media (max-width: 768px) {
            .drug-item {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* カードのデザイン */
        .drug-card {
            padding: 10px;
            /* 内側の余白を詰め、中身がはみ出さないように */
            font-size: 0.85rem;
        }

        .drug-card div {
            font-size: 0.9rem !important;
            /* 薬品名も少し小さく */
        }

        .drug-card:hover {
            background: #f8f9fa;
        }

        /* 非表示設定（もっと見る機能用） */
        .d-none {
            display: none !important;
        }

        /* チェックを入れた時にカードの色を変える */
        input:checked+label .drug-card {
            border-color: #007bff;
            background-color: #8abbff;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .btn-outline-primary {
            color: #007bff;
            border: 1px solid #007bff;
            background: transparent;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
        }
    </style>
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
    <li class="mb-3">
        <div class="text-danger h5 font-weight-bold">
            <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <div class="d-flex align-items-center">
            <small class="text-muted mr-2">該当成分:</small>
            <!-- ここで $item['hits'] を回す -->
            <?php foreach ($item['hits'] as $hit): ?>
                <span class="badge badge-danger mr-1">
                    <?php echo htmlspecialchars($hit, ENT_QUOTES, 'UTF-8'); ?>
                </span>
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
  <div class="alert alert-danger shadow mt-5">
    <h4 class="alert-heading">⚠️ 成分の重複警告！</h4>
    <p>以下の成分が複数の医薬品に含まれています。これらの医薬品を併用する際は注意が必要です。</p>

    <ul class="mb-0">
        <?php foreach ($duplicated_ingredients as $row): ?>
        <li class="mb-2">
            <strong class="text-danger" style="font-size: 1.1rem;">
                <?php echo htmlspecialchars($row['type'], ENT_QUOTES, 'UTF-8'); ?>
            </strong>
            <span class="badge badge-secondary ml-2" style="font-size: 1rem;"><?php echo $row['count']; ?>製品に含有</span>
            <div class="text-muted small mt-1">
                対象医薬品：<?php echo htmlspecialchars($row['drug_names'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <hr>

</div>
<?php elseif (isset($_POST['compare']) && count($selected_ids) > 1): ?>
    <!-- 比較対象が複数あり、重複成分がない場合 -->
    <div class="alert alert-success shadow mt-5">選択された医薬品間に、主要な成分の重複は見つかりませんでした。（全ての成分を網羅するものではありません）</div>
<?php elseif (isset($_POST['compare']) && count($selected_ids) <= 1): ?>
    <!-- 比較対象が1つ以下の場合 -->
     <div class="alert alert-info shadow mt-5">医薬品を複数選択すると、成分の重複チェックができます。</div>
<?php endif; ?>


   <div class="sticky-top bg-white pt-3 pb-3 border-bottom shadow-sm" style="z-index: 1020;">
    <div class="container">
        <div class="input-group">
            <!-- 左側のラベル -->
            <div class="input-group-prepend">
                <span class="input-group-text bg-primary text-white border-primary font-weight-bold"></span>
            </div>
            
            <!-- 検索入力欄 -->
            <input type="text" id="drugSearch" class="form-control border-primary form-control-lg" 
                   placeholder="🔍薬品名、成分、効能" autofocus>
            
            <!-- 右側に虫眼鏡アイコンとボタンを配置 -->
            <div class="input-group-append">
                <!-- 虫眼鏡アイコン -->
                <span class="input-group-text bg-white border-primary border-left-0">
                    <i class="fas fa-search text-primary"></i>
                </span>
                <!-- チェック実行ボタン -->
                <button type="submit" form="medicineForm" name="compare" id="topCompareBtn"
                        class="btn btn-secondary font-weight-bold px-4" disabled>
                    選択した0件をチェック
                </button>
            </div>
        </div>
        <div id="matchCount" class="text-primary small font-weight-bold mt-1" style="height: 1.2em;"></div>
    </div>
    </div>

<main class="container mt-4">
    <!-- フォームにIDを付与 -->
    <form method="POST" id="medicineForm">
    <!-- 既存の card 構造 -->
            <div class="card shadow-sm">
                <div class="card-header bg-light font-weight-bold">商品を選択（2つ以上で重複チェック）</div>
                <div class="card-body">
                    <div id="drugList"> <!-- CSSで横3列になるエリア -->
                        <?php foreach ($medicines as $m): ?>
                        <div class="drug-item">
                            <input type="checkbox" name="medicine_ids[]" value="<?= $m['id'] ?>"
                                id="check<?= $m['id'] ?>" class="d-none">
                            <label for="check<?= $m['id'] ?>" style="width: 100%; height: 100%;">
                   <div class="drug-card">
    <!-- リスク区分バッジ -->
    <div class="mb-1">
        <span class="badge badge-risk-<?= htmlspecialchars($m['risk_category'] ?? 'default', ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($m['risk_category'] ?? '区分未設定', ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <!-- 製品名（太字で大きく） -->
    <div style="font-weight: bold; font-size: 1.1rem; color: #333;">
        <?= htmlspecialchars($m['name'], ENT_QUOTES, 'UTF-8') ?>
    </div>

    <!-- 効能（あれば表示） -->
    <?php if (!empty($m['effect'])): ?>
        <div class="text-primary font-weight-bold" style="font-size: 0.8rem;">
            <?= htmlspecialchars($m['effect'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- 成分 -->
    <small class="text-muted d-block mt-1">
        <?= htmlspecialchars($m['ings'] ?: '成分未登録', ENT_QUOTES, 'UTF-8') ?>
    </small>
</div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- JavaScriptがここに自動で「もっと見る」ボタンを挿入します -->
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" name="compare"
                        class="btn btn-primary btn-lg btn-block shadow">選択した商品をチェックする</button>
                </div>
            </div>
        </form>

    </main>

 <script>
document.addEventListener('DOMContentLoaded', function () {
    const itemsPerPage = 30;
    const loadStep = 20;
    let currentVisible = itemsPerPage;

    const allItems = Array.from(document.querySelectorAll('.drug-item'));
    const drugList = document.getElementById('drugList');
    const searchInput = document.getElementById('drugSearch');
    // 名前属性でボタンを特定
    const submitBtn = document.querySelector('button[name="compare"]');

    function updateSubmitButton() {
        const checkedCount = document.querySelectorAll('input[name="medicine_ids[]"]:checked').length;
        if (submitBtn) {
            submitBtn.innerText = `選択した${checkedCount}件をチェック`;
            submitBtn.disabled = (checkedCount === 0);
            
            // 1件以上なら赤(danger)、0件ならグレー(secondary)に色を変える
            if (checkedCount > 0) {
                submitBtn.classList.replace('btn-secondary', 'btn-danger');
            } else {
                submitBtn.classList.replace('btn-danger', 'btn-secondary');
            }
        }
    }

    // 絞り込み表示の更新
    function updateDisplay() {
        const query = (searchInput.value || "").toLowerCase();
        let matchCount = 0;

        allItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            const isMatch = text.includes(query);
            if (isMatch) {
                matchCount++;
                if (!query && matchCount > currentVisible) {
                    item.classList.add('d-none');
                } else {
                    item.classList.remove('d-none');
                }
            } else {
                item.classList.add('d-none');
            }
        });
        document.getElementById('matchCount').textContent = query ? `${matchCount} 件ヒット` : "";
    }

    // イベント監視
    searchInput.addEventListener('input', updateDisplay);
    
    drugList.addEventListener('change', (e) => {
        if (e.target.name === 'medicine_ids[]') updateSubmitButton();
    });

    // 初期状態の反映
    updateSubmitButton();
    updateDisplay();
});
</script>
</body>

</html>