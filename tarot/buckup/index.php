<!doctype html>
<html lang="ja">
    <head>
        <title>タロットカード占い</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
        <link rel="stylesheet" href="css/style.css">
</head>
<body id="top">

    <?php include('navbar.php'); ?>
  
    <!-- 1. ここを container-fluid (横全開) にして padding を 0 にします -->
    <main role="main" class="container-fluid" style="padding:0;">
        
        <!-- ヘッダー（動画）エリア -->
        <header class="header" style="width: 100%;">
            <div class="logo" style="width: 100%;">
                <a href="index.php" style="position: relative; display: block; width: 100%; overflow: hidden; height: 250px;">
                    <video 
                        src="tarot/700_F_392161820_qOXZH9qokkKvwvniqPd3OwaqOeuelJnR_ST.mp4" 
                        autoplay loop muted playsinline 
                        style="width: 100%; height: 100%; object-fit: cover; display: block;">
                    </video>
                </a>
            </div>
        </header>

            <!--　本文ここまで　-->
        </main>
    <!-- フッター -->
    <footer>
        <p class="copyright">引用元: amateras.blog </p>
    </footer>

    <!-- ★重要：全てのタグの外側（body直前）に出すことで右端への固定が可能になります ★ -->
    <p id="pagetop"><a href="#top">ページの先頭へ戻る</a></p>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

