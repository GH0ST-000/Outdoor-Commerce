import * as React from "react";
import { Input } from "@/components/ui/input";

export const NumberInput = React.forwardRef<
  HTMLInputElement,
  Omit<React.ComponentProps<typeof Input>, "type">
>(function NumberInput(props, ref) {
  return <Input ref={ref} type="number" inputMode="numeric" {...props} />;
});
