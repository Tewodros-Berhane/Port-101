import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full border px-2.5 py-1 text-[11px] font-semibold tracking-[0.01em] whitespace-nowrap transition-[background-color,border-color,color,box-shadow] duration-150 [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:border-destructive aria-invalid:ring-destructive/15 dark:aria-invalid:ring-destructive/25",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary/12 text-[color:var(--action-primary-soft-foreground)] [a&]:hover:bg-primary/18",
        secondary:
          "border-transparent bg-muted text-secondary-foreground [a&]:hover:bg-muted/85",
        destructive:
          "border-transparent bg-destructive/12 text-[color:var(--status-danger-foreground)] [a&]:hover:bg-destructive/18 focus-visible:ring-destructive/25",
        outline:
          "border-border bg-transparent text-secondary-foreground [a&]:hover:bg-muted [a&]:hover:text-foreground",
        success:
          "border-transparent bg-[var(--status-success-soft)] text-[color:var(--status-success-foreground)]",
        warning:
          "border-transparent bg-[var(--status-warning-soft)] text-[color:var(--status-warning-foreground)]",
        danger:
          "border-transparent bg-[var(--status-danger-soft)] text-[color:var(--status-danger-foreground)]",
        info:
          "border-transparent bg-[var(--status-info-soft)] text-[color:var(--status-info-foreground)]",
        neutral:
          "border-transparent bg-[var(--status-neutral-soft)] text-[color:var(--status-neutral-foreground)]",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
