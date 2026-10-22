<article style="max-width: 920px; overflow: hidden; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; color: #202124;">
    @if ($coverUrl)
        <img src="{{ $coverUrl }}" alt="" style="display: block; width: 100%; max-height: 360px; object-fit: contain; background: #f7f7f4;">
    @endif
    <div style="padding: 24px;">
        <p style="margin: 0 0 10px; color: #73766a; font-size: 13px;">
            {{ $category ?: 'Category' }}
            @if ($publishedAt) · {{ \Illuminate\Support\Carbon::parse($publishedAt)->format('j F Y') }} @endif
        </p>
        <h2 style="margin: 0; font-size: 28px; line-height: 1.25;">{{ $title ?: 'Article title' }}</h2>
        @if ($excerpt !== '')
            <p style="margin: 16px 0 0; color: #666; line-height: 1.65;">{{ $excerpt }}</p>
        @endif
        <div style="margin-top: 20px; font-size: 16px; line-height: 1.8; overflow-wrap: anywhere;">
            {!! $body !!}
        </div>
        <p style="margin: 20px 0 0; color: #777; font-size: 13px;">{{ $url }}</p>
    </div>
</article>
