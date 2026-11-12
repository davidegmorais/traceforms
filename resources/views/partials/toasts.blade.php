<div class="fp-toasts" x-data="toasts()" @toast.window="push($event.detail)">
    <template x-for="t in items" :key="t.id">
        <div class="notification fp-toast" :class="t.type === 'error' ? 'is-danger' : (t.type === 'info' ? 'is-info is-light' : 'is-success')" x-show="t.show" x-transition.opacity>
            <button class="delete" @click="t.show = false"></button>
            <i class="fa-solid mr-2" :class="t.type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'"></i>
            <span x-text="t.text"></span>
        </div>
    </template>
</div>
<script>
function toasts() {
    return {
        items: [],
        init() {
            @if (session('success')) this.push({ text: @json(session('success')) }); @endif
            @if (session('error')) this.push({ text: @json(session('error')), type: 'error' }); @endif
            @if ($errors->any() && ! ($hideErrorToast ?? false)) this.push({ text: @json($errors->first()), type: 'error' }); @endif
        },
        push(t) {
            const item = { id: Date.now() + Math.random(), text: t.text, type: t.type || 'success', show: true };
            this.items.push(item);
            setTimeout(() => item.show = false, t.type === 'error' ? 7000 : 4500);
        },
    };
}
</script>
