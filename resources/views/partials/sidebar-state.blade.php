{{-- Aplica o estado do menu (recolhido/expandido) antes da página aparecer, sem "piscar". --}}
<script>
    window.TFSidebar = {
        key: 'tf_sidebar_collapsed',
        apply(c) { document.documentElement.classList.toggle('sb-collapsed', c); },
        toggle() { const c = !document.documentElement.classList.contains('sb-collapsed'); this.apply(c); try { localStorage.setItem(this.key, c ? '1' : '0'); } catch (e) {} },
    };
    try { TFSidebar.apply(localStorage.getItem(TFSidebar.key) === '1'); } catch (e) {}
</script>
