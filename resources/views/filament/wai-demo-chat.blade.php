<div class="space-y-3 max-h-[65vh] overflow-y-auto pr-1">
    @forelse($messages as $message)
        <div class="flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[85%] rounded-2xl px-4 py-3 {{ $message->role === 'user' ? 'bg-success-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-950 dark:text-white' }}">
                <div class="text-xs font-semibold opacity-70 mb-1">
                    {{ $message->role === 'user' ? 'Müşteri' : 'Yapay Zeka' }} · {{ $message->created_at?->format('d.m.Y H:i:s') }}
                </div>
                <div class="whitespace-pre-wrap text-sm leading-relaxed">{{ $message->message }}</div>
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-500 py-8 text-center">Bu demo için henüz Test Sohbeti mesajı kaydedilmemiş.</div>
    @endforelse
</div>
