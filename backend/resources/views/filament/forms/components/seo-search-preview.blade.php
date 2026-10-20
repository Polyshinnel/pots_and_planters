<div style="box-sizing: border-box; width: 100%; padding: 12px; border: 1px solid #dadce0; border-radius: 9px; background: #fff; font-family: Arial, sans-serif;" aria-live="polite">
    <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 4px; color: #202124; font-size: 12px; line-height: 16px; overflow-wrap: anywhere;">
        <span>{{ $displayUrl ?: 'https://example.com/' }}</span>
        <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" style="display: block; width: 12px; height: 12px; flex: 0 0 12px; color: #5f6368;" aria-hidden="true"><path d="M5.25 3.5a.75.75 0 0 1 .75-.75h6.25a.75.75 0 0 1 .75.75v6.25a.75.75 0 0 1-1.5 0V5.31L4.03 12.78a.75.75 0 1 1-1.06-1.06L10.44 4.25H6a.75.75 0 0 1-.75-.75Z"/></svg>
    </div>
    <div style="color: #1a0dab; font-size: 16px; line-height: 20px; overflow-wrap: anywhere;">{{ $title ?: 'Page title preview' }}</div>
    <div style="margin-top: 4px; color: #4d5156; font-size: 13px; line-height: 18px; overflow-wrap: anywhere;">{{ $description ?: 'Page description preview' }}</div>
    <div style="margin-top: 12px; color: #70757a; font-size: 12px; line-height: 16px;">Preview only — Google may rewrite titles and descriptions.</div>
</div>
