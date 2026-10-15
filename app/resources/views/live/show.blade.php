<x-dynamic-component :component="auth()->check() ? 'layout' : 'public'" :title="'Live '.$competition->name">
<a class="back-link" href="/live">← Live score</a>
<livewire:live-board :competition-id="$competition->id"/>
</x-dynamic-component>
