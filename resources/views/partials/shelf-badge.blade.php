{{-- Home shelves (owner, 2026-11-08): say whether a piece is new or from the archive. --}}
<span class="absolute right-3 top-3 z-10 rounded-full px-2.5 py-0.5 text-xs font-extrabold {{ in_array($p->id, $freshIds ?? [], true) ? 'bg-red text-white' : 'border border-navy bg-paper text-navy' }}">{{ in_array($p->id, $freshIds ?? [], true) ? 'নতুন' : 'আর্কাইভ থেকে' }}</span>
