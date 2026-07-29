@props(['title', 'date'])

<section {{ $attributes->class(['flex min-h-0 flex-col bg-[#f6f7f4]']) }}>
    <div class="flex flex-none items-center justify-between border-b border-[#ebede7] px-6 pb-[14px] pt-[18px] sm:px-[34px]"><h3 class="text-[13px] font-semibold uppercase tracking-[0.06em] text-[#8a9184]">{{ $title }}</h3><span class="text-[12.5px] text-[#a3a99c]">{{ $date }}</span></div>
    <div data-conversation-scroll class="flex min-h-0 flex-1 flex-col gap-[30px] overflow-y-auto px-5 py-[26px] pb-10 sm:px-[34px]">{{ $slot }}</div>
</section>
