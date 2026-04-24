</main>
<footer class="footer">
    <p class="footer__text">Board Game Hut Collection</p>
</footer>
<?php $appVersion = @filemtime(dirname(__DIR__, 2) . '/public/assets/app.js') ?: time(); ?>
<script src="/assets/app.js?v=<?= $appVersion ?>"></script>
</body>
</html>
