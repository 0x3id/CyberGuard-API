<div class="logo-area">
    <div class="shield-icon">
        @include('mail.partials.shield-logo')
    </div>
    <div class="brand-name">
        <div style="display: flex; gap: 0;">
            <span class="cyber">Cyber</span><span class="guard">Guard</span>
        </div>
        <span class="tagline">Security Protocol Active</span>
    </div>
</div>

<div class="verify-icon-wrap">
    <div class="verify-icon-circle">
        @include('mail.partials.header-icon', ['icon' => $headerIcon ?? 'envelope'])
    </div>
</div>
