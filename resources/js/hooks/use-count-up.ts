import { useEffect, useRef, useState } from 'react';

/**
 * Animates from the previous value to `value` with an ease-out curve, using
 * requestAnimationFrame — no dependencies. Respects prefers-reduced-motion.
 */
export function useCountUp(value: number, duration = 900): number {
    const [display, setDisplay] = useState(value);
    const fromRef = useRef(0);

    useEffect(() => {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const from = fromRef.current;
        if (reduce || from === value) {
            setDisplay(value);
            fromRef.current = value;
            return;
        }

        const start = performance.now();
        let raf = 0;
        const tick = (now: number) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            setDisplay(from + (value - from) * eased);
            if (progress < 1) {
                raf = requestAnimationFrame(tick);
            } else {
                fromRef.current = value;
            }
        };
        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [value, duration]);

    return display;
}
