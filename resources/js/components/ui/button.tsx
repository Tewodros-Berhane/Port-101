import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[var(--radius-control)] border text-[13px] font-medium tracking-[-0.01em] shadow-[var(--shadow-xs)] transition-[background-color,border-color,color,box-shadow,transform] duration-150 ease-out disabled:pointer-events-none disabled:opacity-50 disabled:shadow-none [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/35 focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:ring-destructive/15 dark:aria-invalid:ring-destructive/25 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary text-primary-foreground hover:bg-[var(--action-primary-hover)]",
        destructive:
          "border-transparent bg-destructive text-destructive-foreground hover:bg-[var(--action-danger-hover)] focus-visible:ring-destructive/25",
        outline:
          "border-input bg-card text-foreground hover:border-[color:var(--border-strong)] hover:bg-muted/80",
        secondary:
          "border-transparent bg-muted text-secondary-foreground hover:bg-[var(--bg-surface-muted)]",
        ghost:
          "border-transparent bg-transparent text-[color:var(--text-secondary)] shadow-none hover:bg-muted hover:text-foreground",
        link: "border-transparent bg-transparent text-primary shadow-none underline-offset-4 hover:underline",
      },
      size: {
        default: "h-10 px-4 py-2 has-[>svg]:px-3.5",
        sm: "h-9 px-3.5 has-[>svg]:px-3",
        lg: "h-11 px-5 has-[>svg]:px-4",
        icon: "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
