<template>
    <div class="loader-overlay">
        <div class="loader-wrapper">
            <div class="loader-ring"></div>
            <p class="loader-text">Loading</p>
        </div>
    </div>
</template>

<style lang="scss" scoped>
// We use the same variables from your main SASS
$primary: #4f46e5;

.loader-overlay {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;

    // Fix 1: Stop the overlay from intercepting clicks
    pointer-events: none;

    // Fix 2: Keep 150px for desktop, reduce for mobile
    min-height: 150px;

    background: transparent;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);

    // Responsive adjustment
    @media (max-width: 768px) {
        min-height: 80px; // Smaller footprint on mobile
    }
}

.loader-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.25rem;

    // Optional: Reduce gap on mobile to save more space
    @media (max-width: 768px) {
        gap: 0.5rem;
    }
}

.loader-ring {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    // Uses your brand primary with low opacity for the track
    border: 3px solid rgba($primary, 0.1);
    border-top-color: $primary;
    animation: spin 0.8s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

.loader-text {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b; // Matching your sidebar text colors

    &::after {
        content: "";
        animation: dots 1.5s steps(4, end) infinite;
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes dots {

    0%,
    20% {
        content: "";
    }

    40% {
        content: ".";
    }

    60% {
        content: "..";
    }

    80%,
    100% {
        content: "...";
    }
}
</style>