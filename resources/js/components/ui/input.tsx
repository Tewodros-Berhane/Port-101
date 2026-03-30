import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary/15 selection:text-foreground flex h-10 w-full min-w-0 rounded-[var(--radius-control)] border bg-card px-3.5 py-2 text-sm text-foreground shadow-[var(--shadow-xs)] transition-[border-color,box-shadow,background-color] duration-150 outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50",
        "focus-visible:border-[color:var(--border-strong)] focus-visible:ring-[3px] focus-visible:ring-ring/30",
        "aria-invalid:border-destructive aria-invalid:ring-destructive/15 dark:aria-invalid:ring-destructive/25",
        type === "date" &&
          "rounded-[calc(var(--radius-panel)+2px)] pr-11 [color-scheme:light] dark:[color-scheme:dark]",
        className
      )}
      {...props}
    />
  )
}

export { Input }
