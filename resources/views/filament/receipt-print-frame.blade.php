<script>
    window.printReceiptFrame = function (url) {
        let iframe = document.getElementById('receipt-print-frame');
        if (!iframe) {
            iframe = document.createElement('iframe');
            iframe.id = 'receipt-print-frame';
            iframe.style.display = 'none';
            document.body.appendChild(iframe);
        }
        iframe.src = url;
    };
</script>
