<div style="max-width: 340px; overflow: hidden; border: 1px solid #e2e3db; border-radius: 10px; background: #fff; font-family: Lato, Arial, sans-serif; color: #222;">
    <div style="position: relative; aspect-ratio: 1 / 1; background: #fafaf8;">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $name }}" style="width: 100%; height: 100%; object-fit: cover;">
        @else
            <div style="height: 100%; display: grid; place-items: center; color: #777; font-size: 13px;">
                {{ $imagePending ? 'Image available after save and processing' : 'No ready main image' }}
            </div>
        @endif
    </div>
    <div style="min-height: 190px; padding: 16px; display: flex; flex-direction: column; gap: 10px; box-sizing: border-box;">
        @if (filled($sku))
            <p style="margin: 0; color: #666; font-size: 12px; line-height: 1.5; overflow-wrap: anywhere;">Model: {{ $sku }}</p>
        @endif
        <h3 style="margin: 0; color: #222; font-size: 16px; line-height: 1.45; font-weight: 600; overflow-wrap: anywhere;">{{ $name }}</h3>
        <p style="min-height: 1.5em; margin: 0; color: #666; font-size: 13px; line-height: 1.5; overflow-wrap: anywhere;">{{ $specifications ?: ' ' }}</p>
        <div style="display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; column-gap: 8px; row-gap: 4px; margin-top: auto; padding-top: 8px;">
            <p style="margin: 0; font-size: 16px; font-weight: 700;">{{ filled($price) ? 'AED '.number_format((float) $price, 2) : 'Price on enquiry' }}</p>
            <span style="font-size: 14px; font-weight: 600; text-decoration: underline; text-underline-offset: 4px;">View product ↗</span>
        </div>
    </div>
</div>
