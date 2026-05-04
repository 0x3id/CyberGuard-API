<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status | CyberGuard Pro</title>
    <!-- Use Poppins font to match the feel -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            bg: '#0F0E17', // The dark background
                            card: '#161520', // Status card background
                            primary: '#A37FFF', // Glowy Purple/Lavender
                            accent: '#3EDDFF', // Status Ring Blue
                            green: '#22C55E', // System status green
                            red: '#EF4444', // Payment failure red
                            textMain: '#FFFFFF', // Clean White
                            textSub: '#858494', // Grey text
                        }
                    },
                    boxShadow: {
                        'brand-glow': '0 0 15px 2px rgba(163, 127, 255, 0.4)', // The button glow
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-brand-bg font-sans text-brand-textMain antialiased">

    <!-- Header Section (Simulating the original) -->
    <header class="w-full max-w-7xl mx-auto px-6 py-5 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <!-- Simulating the shield logo icon -->
            <div class="p-1.5 border border-brand-primary/40 rounded-full text-brand-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <span class="text-xl font-bold">CyberGuard Pro</span>
        </div>
        <div class="flex items-center gap-6">
            <span class="text-brand-textSub text-sm">Dashboard</span>
            <span class="text-brand-textSub text-sm">Support</span>
            <button class="bg-brand-primary text-white px-5 py-2 rounded-lg text-sm font-semibold hover:opacity-90 shadow-brand-glow transition">
                Logout
            </button>
        </div>
    </header>

    <!-- Main Content Area (Layout mirrors the 2-column look) -->
    <main class="w-full max-w-7xl mx-auto px-6 pt-16 pb-24 grid grid-cols-1 md:grid-cols-[2fr,1fr] gap-12 items-center">
        
        <!-- Left Side: Status Text -->
        <div class="text-left">
            <h1 class="text-5xl md:text-6xl font-bold leading-tight max-w-lg">
                Your <span class="text-brand-primary">Payment</span> Status. Redefined.
            </h1>
            <p class="text-brand-textSub text-lg mt-6 mb-10 max-w-xl">
                {{ $subTitle }}
            </p>

            <a href="/dashboard" class="inline-flex items-center gap-2 bg-brand-primary text-white px-6 py-3.5 rounded-xl font-semibold hover:opacity-90 shadow-brand-glow transition-all">
                Return to Dashboard
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </a>
        </div>

        <!-- Right Side: The Status Card (Styled like the original) -->
        <div class="bg-brand-card p-8 rounded-3xl shadow-xl w-full max-w-md mx-auto md:mx-0">
            <!-- Card Header: Status -->
            <div class="flex items-center gap-2 mb-8 text-sm">
                @if($isSuccess)
                    <div class="w-2.5 h-2.5 rounded-full bg-brand-green animate-pulse"></div>
                    <span class="text-brand-green font-semibold">TRANSACTION SUCCESS</span>
                @else
                    <div class="w-2.5 h-2.5 rounded-full bg-brand-red animate-pulse"></div>
                    <span class="text-brand-red font-semibold">TRANSACTION FAILED</span>
                @endif
            </div>

            <!-- Central Icon (Recreating the Ring + Shield) -->
            <div class="flex items-center justify-center mb-10 relative">
                <!-- Outer Ring (Status color dependent) -->
                @php
                    $ringColor = $isSuccess ? 'stroke-brand-accent' : 'stroke-brand-red';
                    $iconColor = $isSuccess ? 'text-brand-accent' : 'text-brand-red';
                @endphp
                <svg class="w-32 h-32" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="54" fill="none" class="{{ $ringColor }}" stroke-width="4" />
                </svg>
                
                <!-- Inner Shield Icon (Status color dependent) -->
                <div class="absolute inset-0 flex items-center justify-center {{ $iconColor }}">
                    <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
            </div>

            <!-- Details Block (Mirrors the mini boxes) -->
            <div class="grid grid-cols-2 gap-4 text-center">
                <div class="border border-white/5 rounded-xl px-3 py-5 bg-black/20">
                    <p class="text-brand-textMain text-xl font-bold">{{ $transactionId }}</p>
                    <p class="text-brand-textSub text-xs mt-1">Transaction ID</p>
                </div>
                <div class="border border-white/5 rounded-xl px-3 py-5 bg-black/20">
                    <p class="text-brand-textMain text-xl font-bold">{{ $amount }} {{ $currency }}</p>
                    <p class="text-brand-textSub text-xs mt-1">Total Amount</p>
                </div>
            </div>

            <!-- Extra Details Row -->
            <div class="mt-4 border border-white/5 rounded-xl px-3 py-4 text-left bg-black/20 text-xs">
                <div class="flex justify-between items-center text-brand-textSub">
                    <span>Payment Method:</span>
                    <span class="text-brand-textMain font-semibold uppercase">{{ $cardType }} ({{ $cardLast4 }})</span>
                </div>
            </div>

        </div>
    </main>

</body>
</html>