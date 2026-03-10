<!doctype html>
<html lang="ja">

<head>
    <title>占い</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body id="top">

    <?php include('navbar.php'); ?>

        <div class="container text-center pb-0" style="margin-top: 80px;">
            <p style="color:#d8e5ff;" class="mb-3">気になるカードを１枚選んでください</p>

            <!-- 選んだカードの結果を表示する場所 -->
            <div id="result-display" class="mt-1 mb-3" style="display:none;">
                <h4 id="result-name" class="mt-0"></h4>
                <div id="result-image"></div>
                <button id="action-button" style="display:none;margin-top:5px" class="btn btn-outline-light"onclick="location.reload()">もう一度引く</button>
            </div>

            <!-- カードが並ぶ場所 -->
             <div id="card-field" class="d-flex flex-wrap justify-content-center mb-0"></div>
        </div>



    </main>
    <!-- index.php の本文（メイン部分）に追加 -->
    <script>

        // 1. シャッフル関数の定義
        Array.prototype.shuffle = function () {
            let i = this.length;
            while (i) {
                const j = Math.floor(Math.random() * i);
                const t = this[--i];
                this[i] = this[j];
                this[j] = t;
            }
            return this;
        };

        const cardData = [
            //正位置
            { name: "愚者_正位置", file: "tarot/amateras-blog-00-the-fool-waite.png", link: "pages/the-fool.php"},
            { name: "魔術師_正位置", file: "tarot/amateras-blog-01-the-magician-waite.png", link: "pages/the-magician.php"},
            { name: "女教皇_正位置", file: "tarot/amateras-blog-02-the-highpriestess-waite.png", link: "pages/the-high-priestess.php"},
            { name: "女帝_正位置", file: "tarot/amateras-blog-03-the-empress-waite.png", link: "pages/the-empress.php"},
            { name: "皇帝_正位置", file: "tarot/amateras-blog-04-the-emperor-waite.png", link: "pages/the-emperor.php"},
            { name: "教皇_正位置", file: "tarot/amateras-blog-05-the-hierophant-waite.png", link: "pages/the-hierophant.php"},
            { name: "恋人_正位置", file: "tarot/amateras-blog-06-the-lovers-waite_reverse.png", link: "pages/the-lovers.php"},
            { name: "戦車_正位置", file: "tarot/amateras-blog-07-the-chariot-waite.png", link: "pages/the-chariot.php"},
            { name: "力_正位置", file: "tarot/amateras-blog-08-strength-waite.png", link: "pages/Strength.php"},
            { name: "隠者_正位置", file: "tarot/amateras-blog-09-the-hermit-waite.png", link: "pages/the-hermit.php"},
            { name: "運命の輪_正位置", file: "tarot/amateras-blog-10-wheel-offortune-waite.png", link: "pages/wheel-of-fortune.php"},
            { name: "正義_正位置", file: "tarot/amateras-blog-11-justice-waite.png", link: "pages/Justice.php"},
            { name: "吊るされた男_正位置", file: "tarot/amateras-blog-12-the-hangedman-waite.png", link: "pages/TheHangedMan.php"},
            { name: "死神_正位置", file: "tarot/amateras-blog-13-death-waite.png", link: "pages/Death.php"},
            { name: "節制_正位置", file: "tarot/amateras-blog-14-temperance-waite.png", link: "pages/Temperance.php"},
            { name: "悪魔_正位置", file: "tarot/amateras-blog-15-the-devil-waite.png", link: "pages/TheDevil.php"},
            { name: "塔_正位置", file: "tarot/amateras-blog-16-the-tower-waite.png", link: "pages/TheTower.php"},
            { name: "星_正位置", file: "tarot/amateras-blog-17-the-star-waite.png", link: "pages/TheStar.php"},
            { name: "月_正位置", file: "tarot/amateras-blog-18-the-moon-waite.png", link: "pages/TheMoon.php"},
            { name: "太陽_正位置", file: "tarot/amateras-blog-19-the-sun-waite.png", link: "pages/TheSun.php"},
            { name: "審判_正位置", file: "tarot/amateras-blog-20-judgement-waite.png", link: "pages/Judgement.php"},
            { name: "世界_正位置", file: "tarot/amateras-blog-21-the-world-waite.png", link: "pages/TheWorld.php"},

            //逆位置
            { name: "愚者_逆位置", file: "tarot/amateras-blog-00-the-fool-waite_reverse.png", link: "pages/the-fool.php"},
            { name: "魔術師_逆位置", file: "tarot/amateras-blog-01-the-magician-waite_reverse.png", link: "pages/the-magician.php"},
            { name: "女教皇_逆位置", file: "tarot/amateras-blog-02-the-highpriestess-waite_reverse.png", link: "pages/the-high-priestess.php"},
            { name: "女帝_逆位置", file: "tarot/amateras-blog-03-the-empress-waite_reverse.png", link: ".../pages/the-empress.php"},
            { name: "皇帝_逆位置", file: "tarot/amateras-blog-04-the-emperor-waite_reverse.png", link: "pages/the-emperor.php"},
            { name: "教皇_逆位置", file: "tarot/amateras-blog-05-the-hierophant-waite_reverse.png", link: "pages/the-hierophant.php"},
            { name: "恋人_逆位置", file: "tarot/amateras-blog-06-the-lovers-waite_reverse.png", link: "pages/the-lovers.php"},
            { name: "戦車_逆位置", file: "tarot/amateras-blog-07-the-chariot-waite_reverse.png", link: "pages/the-chariot.php"},
            { name: "力_逆位置", file: "tarot/amateras-blog-08-strength-waite_reverse.png", link: "pages/Strength.php"},
            { name: "隠者_逆位置", file: "tarot/amateras-blog-09-the-hermit-waite_reverse.png", link: "pages/the-hermit.php"},
            { name: "運命の輪_逆位置", file: "tarot/amateras-blog-10-wheel-offortune-waite_reverse.png", link: "pages/wheel-of-fortune.php"},
            { name: "正義_逆位置", file: "tarot/amateras-blog-11-justice-waite_reverse.png", link: "pages/Justice.php"},
            { name: "吊るされた男_逆位置", file: "tarot/amateras-blog-12-the-hangedman-waite_reverse.png", link: "pages/TheHangedMan.php"},
            { name: "死神_逆位置", file: "tarot/amateras-blog-13-death-waite_reverse.png", link: "pages/Death.php"},
            { name: "節制_逆位置", file: "tarot/amateras-blog-14-temperance-waite_reverse.png", link: "pages/Temperance.php"},
            { name: "悪魔_逆位置", file: "tarot/amateras-blog-15-the-devil-waite_reverse.png", link: "pages/TheDevil.php"},
            { name: "塔_逆位置", file: "tarot/amateras-blog-16-the-tower-waite_reverse.png", link: "pages/TheTower.php"},
            { name: "星_逆位置", file: "tarot/amateras-blog-17-the-star-waite_reverse.png", link: "pages/TheStar.php"},
            { name: "月_逆位置", file: "tarot/amateras-blog-18-the-moon-waite_reverse.png", link: "pages/TheMoon.php"},
            { name: "太陽_逆位置", file: "tarot/amateras-blog-19-the-sun-waite_reverse.png", link: "pages/TheSun.php"},
            { name: "審判_逆位置", file: "tarot/amateras-blog-20-judgement-waite_reverse.png", link: "pages/Judgement.php"},
            { name: "世界_逆位置", file: "tarot/amateras-blog-21-the-world-waite_reverse.png", link: "pages/TheWorld.php"}
        ];

        // 3. 22枚を準備する
        cardData.shuffle();
        const selectedCards = cardData.slice(0, 22);

        // 4. 画面に並べる
        const field = document.getElementById("card-field");
        const resultDisplay = document.getElementById("result-display");
        let hasPulled = false;//1枚引いたかどうかの判定用

        //両面に裏面で並べる
        selectedCards.forEach(card => {
            const div = document.createElement("div");
            div.className = "card-unit back";

            div.item = card; // データを保存
            div.onclick = flip;
            field.appendChild(div);
        });

        // 5. めくる処理
        function flip(e) {
            if(hasPulled)return;//すでに引いていたら何もしない
            hasPulled = true;

            const clickedCard = e.currentTarget;
            const card = clickedCard.item;

            //選んだカードに回転アニメーションをつける
            clickedCard.classList.add("flipping");

            //選んだカード以外を少し薄くする演出
            document.querySelectorAll('.card-unit').forEach(c => {
                if (c !== clickedCard) {
                    c.classList.add("card-fade-out"); // ここでCSSのアニメーションを発動させる
                }
            });

            //0.6秒待ってから結果を表示する
            setTimeout(() => {
                const field = document.getElementById("card-field");
                field.style.setProperty("display","none","important");

            //結果を表示エリアに出す
           resultDisplay.style.display="block";

           // 結果の名前と画像をセットする
            document.getElementById("result-name").innerText ="結果:"+card.name;

            // 結果画像と詳細リンクをまとめてセット（バッククォート内でHTMLを組み立てます）
            document.getElementById("result-image").innerHTML = `<a href="${card.link}">
            <img src="${card.file}" alt="${card.name}" style="max-width:150px; border-radius:15px; box-shadow: 0 0 20px gold;">
            <p class="detail-link-text" style="color:#d8e5ff; margin-top:10px
            ;margin-bottom:20px !important; text-decoration: underline;">このカードの意味を詳しく見る</p>
            </a>
            `;

            /*「もう一度引く」ボタンを表示 */
            document.getElementById("action-button").style.display = "inline-block";
   
              },600);

            console.log("あなたが引いたカード:", card.name);
        }
        
    </script>


    <!-- フッター -->
    <footer>
        <p class="copyright">引用元: amateras.blog </p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>