</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> &middot; Internet Programming group project</p>
</footer>
<script>
document.querySelectorAll('nav a').forEach(function(link) {
    var path = window.location.pathname;
    var href = link.getAttribute('href');
    if (href && (path.endsWith(href) || (href.indexOf('?') === -1 && path.indexOf(href) !== -1 && !href.endsWith('index.php')))) {
        link.classList.add('current');
    }
});
</script>
</body>
</html>
