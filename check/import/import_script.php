<?php
// DB接続設定 (既存の情報を利用)
$dsn  = 'mysql:host=localhost;dbname=medicine;charset=utf8mb4';
$user = 'root';
$pass = '';
$file_path = 'import_data.csv';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

     // ファイルを開く
    $handle = fopen($file_path, "r");
    if ($handle === FALSE) {
        die("CSVファイルを開けませんでした。");
    }

    // 日本語（SJISなど）対策のロケール設定
    setlocale(LC_ALL, 'ja_JP.UTF-8'); 

    // ★重要: ヘッダーが3行あるので、3回読み飛ばしてポインタを4行目へ進める
    fgetcsv($handle); // 1行目 (No)
    fgetcsv($handle); // 2行目 (販売名, JAN...)
    fgetcsv($handle); // 3行目 (有効成分名, 備考)

    $pdo->beginTransaction();

    $row_count = 0;
    while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
        // 空行をスキップ
        if (!array_filter($row)) continue;

        // var_dumpの構造から推測されるインデックス（実際のCSV列数に合わせて調整してください）
        $product_name = $row[1] ?? ''; // 2列目: 販売名
        $ingredient_list = $row[6] ?? ''; // 7列目: 有効成分名
        $memo = $row[7] ?? ''; // 8列目: 備考

        if (empty($product_name)) continue;

        // 1. medicines テーブルに薬品を挿入
        $stmt_med = $pdo->prepare("INSERT INTO medicines (name, effect, memo) VALUES (?, '市販薬（税制対象）', ?)");
        $stmt_med->execute([$product_name, $memo]);
        $medicine_id = $pdo->lastInsertId();

        // 2. 成分を解析し、ingredients テーブルに登録・紐付け
        if (!empty($ingredient_list)) {
            // カンマや「、」で成分を分割
            $ingredients = preg_split('/[、,，\s]+/', $ingredient_list, -1, PREG_SPLIT_NO_EMPTY);
            
            foreach ($ingredients as $ingredient_name) {
                // ingredients テーブルに成分が存在するか確認
                $stmt_ing_check = $pdo->prepare("SELECT id FROM ingredients WHERE type = ?");
                $stmt_ing_check->execute([$ingredient_name]);
                $ingredient_id = $stmt_ing_check->fetchColumn();

                if (!$ingredient_id) {
                    // 存在しなければ新しく登録
                    $stmt_ing_ins = $pdo->prepare("INSERT INTO ingredients (type) VALUES (?)");
                    $stmt_ing_ins->execute([$ingredient_name]);
                    $ingredient_id = $pdo->lastInsertId();
                }

                // 3. medicine_ingredients 中間テーブルに紐付けを登録
                $stmt_mi = $pdo->prepare("INSERT INTO medicine_ingredients (medicine_id, ingredient_id) VALUES (?, ?)");
                $stmt_mi->execute([$medicine_id, $ingredient_id]);
            }
        }
        $row_count++;
    }

    $pdo->commit();
    fclose($handle);
    echo "**データインポートが完了しました。** 合計 {$row_count} 件の薬品を登録しました。";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // エラー時はロールバック
    }
    die("エラーが発生しました: " . $e->getMessage());
}