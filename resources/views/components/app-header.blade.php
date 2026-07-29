@props(['mark', 'brand', 'tagline', 'sessionLabel', 'clearLabel', 'clearAction' => null, 'disabled' => false])

<header {{ $attributes->class(['flex h-[60px] flex-none items-center justify-between border-b border-[#e8eae4] bg-white px-5 sm:px-7']) }}>
    <div class="flex items-center gap-[11px]">
        <div class="flex size-[30px] items-center justify-center rounded-[9px] bg-[#2f7a55] font-display text-[19px] font-medium text-white">{{ $mark }}</div>
        <div class="flex flex-col leading-[1.05]"><span class="font-display text-xl font-medium tracking-[-0.01em]">{{ $brand }}</span><span class="text-[11px] font-medium tracking-[0.02em] text-[#8a9184]">{{ $tagline }}</span></div>
    </div>
    <div class="flex items-center gap-2 sm:gap-4"><span class="hidden items-center gap-[7px] text-[13px] text-[#6f7669] sm:flex"><span class="size-[7px] rounded-full bg-[#2f7a55]"></span>{{ $sessionLabel }}</span><button type="button" wire:loading.attr="disabled" @if ($clearAction) wire:click="{{ $clearAction }}" wire:island="" @endif @disabled($disabled) class="flex items-center gap-[7px] rounded-lg border border-[#e2e5dd] bg-white px-[10px] py-[7px] text-xs font-medium text-[#6f7669] disabled:cursor-not-allowed disabled:opacity-50 sm:px-[13px]"> <span class="text-[13px] leading-none">↺</span>{{ $clearLabel }}</button></div>
</header>
