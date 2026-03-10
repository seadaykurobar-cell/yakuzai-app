const input = document.getElementById('ingredient_input');

input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const start = this.selectionStart;
        const value = this.value;
        
        // 現在の行のテキストを取得
        const lastNewLine = value.lastIndexOf('\n', start - 1);
        const currentLine = value.substring(lastNewLine + 1, start);

        // 空行（・のみ）での連打防止
        if (currentLine.trim() === '・' || currentLine.trim() === '') {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        this.value = value.substring(0, start) + "\n・" + value.substring(this.selectionEnd);
        this.selectionStart = this.selectionEnd = start + 2;
    }
});

input.addEventListener('focus', function() {
    if (this.value.trim() === '') {
        this.value = '・';
    }
});