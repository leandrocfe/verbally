@props(['title', 'description', 'placeholder', 'value', 'countLabel', 'shortcutLabel'])

<section {{ $attributes->class(['flex flex-col bg-white p-6 sm:p-[30px_28px] lg:border-r lg:border-[#e8eae4]']) }}>
    <div class="mb-5 flex flex-col gap-[5px]">
        <h2 class="font-display text-[26px] font-medium tracking-[-0.015em]">{{ $title }}</h2>
        <p class="text-[13.5px] leading-[1.5] text-[#7a8175]">{{ $description }}</p>
    </div>
    <div class="flex min-h-[300px] flex-1 flex-col overflow-hidden rounded-[14px] border border-[#e2e5dd] bg-[#fbfcfa]">
        <textarea aria-label="{{ $title }}" placeholder="{{ $placeholder }}" class="min-h-0 flex-1 resize-none border-0 bg-transparent p-[18px] text-[15px] leading-[1.65] text-[#21251f] outline-none placeholder:text-[#a3a99c]">{{ $value }}</textarea>
        <div class="flex flex-none justify-end border-t border-[#eceee8] px-[14px] py-3"><span class="text-[11.5px] text-[#a3a99c]">{{ $countLabel }}</span></div>
    </div>
    <div class="mt-4 flex items-center justify-between gap-3">
        <span class="text-[12px] text-[#a3a99c]">{{ $shortcutLabel }}</span>
        {{ $action }}
    </div>
</section>
