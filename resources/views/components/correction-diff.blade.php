@props(['segments'])

<p {{ $attributes->class(['mb-4 text-[15.5px] leading-[1.65]']) }}>
    @foreach ($segments as $segment)
        @if ($segment['type'] === 'removed')
            <del class="text-[#c0392b] decoration-[#e0a9a2]">{{ $segment['original'] }}</del>
        @elseif ($segment['type'] === 'added')
            <ins class="rounded bg-[#e9f3ee] px-1 font-semibold text-[#2f7a55] no-underline">{{ $segment['replacement'] }}</ins>
        @else
            {{ $segment['original'] }}
        @endif
    @endforeach
</p>
