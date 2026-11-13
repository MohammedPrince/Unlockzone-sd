{{-- Applies the saved light/dark choice before the page paints, so there is no flash of the wrong theme. Dark is the default. --}}
<script>
    (function () {
        try { if (localStorage.getItem('theme') === 'light') { document.documentElement.setAttribute('data-theme', 'light'); } } catch (e) { /* storage blocked */ }
    })();
</script>
