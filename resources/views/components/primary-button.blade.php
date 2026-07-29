@props(['label'])

<button type="button" wire:loading.attr="disabled" {{ $attributes->class(['flex items-center gap-2 rounded-[10px] bg-[#2f7a55] px-[18px] py-[11px] text-[14.5px] font-semibold text-white shadow-[0_1px_2px_rgba(47,122,85,.25)] disabled:cursor-not-allowed disabled:opacity-50']) }}>{{ $label }} <span class="text-[15px] leading-none">→</span></button>
