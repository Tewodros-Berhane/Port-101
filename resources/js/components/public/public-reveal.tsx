import { motion, useReducedMotion } from 'framer-motion';
import type { PropsWithChildren } from 'react';
import { cn } from '@/lib/utils';

export default function PublicReveal({
    children,
    className,
    delay = 0,
    y = 18,
}: PropsWithChildren<{
    className?: string;
    delay?: number;
    y?: number;
}>) {
    const prefersReducedMotion = useReducedMotion();

    if (prefersReducedMotion) {
        return <div className={className}>{children}</div>;
    }

    return (
        <motion.div
            className={cn(className)}
            initial={{ opacity: 0, y }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{
                duration: 0.55,
                delay,
                ease: [0.22, 1, 0.36, 1],
            }}
        >
            {children}
        </motion.div>
    );
}
